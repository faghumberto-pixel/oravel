<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\ChecklistGroup;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentMovement;
use App\Models\FleetDriver;
use App\Models\HorimeterReading;
use App\Models\InternalUnit;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\StorageLocation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Pedido do usuário 29/09/2026: dataset de demonstração completo pro tenant
 * do próprio faghumberto@gmail.com, cobrindo fluxo de dados do Ativo de
 * ponta a ponta (Informações Gerais, Localização, Histórico de Trabalho,
 * Logs de Auditoria, Checklists, Plano de Manutenção Preventiva vinculado,
 * Categoria/Grupo, Dossiê, Planta Baixa) -- 5 equipamentos em 3 frentes
 * (Terraplenagem, Içamento/Guincho, Transporte), motorista vinculado ao
 * caminhão, e 3 Contratos (um por modalidade de cobrança: hora/diária/mês).
 *
 * Idempotente por checagem simples: se a categoria "Terraplenagem" já
 * existir pro tenant, aborta (a não ser que --force seja passado), pra não
 * duplicar o dataset em reexecuções acidentais.
 */
class SeedAtivoDemoData extends Command
{
    protected $signature = 'demo:seed-ativo-frota {email=faghumberto@gmail.com} {--force : Roda mesmo se já existir dado do seed anterior}';

    protected $description = 'Cria um dataset de demonstração completo (Ativos, categorias, checklists, plano preventivo, contratos) pro tenant do usuário informado';

    public function handle(): int
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("Usuário {$email} não encontrado.");

            return self::FAILURE;
        }

        $tenantId = $user->tenant_id;

        if (! $tenantId) {
            $this->error("Usuário {$email} não tem tenant_id.");

            return self::FAILURE;
        }

        if (! $this->option('force') && AssetCategory::where('tenant_id', $tenantId)->where('name', 'Terraplenagem')->exists()) {
            $this->warn('Dataset já parece existir (categoria "Terraplenagem" encontrada). Use --force pra rodar de novo mesmo assim.');

            return self::SUCCESS;
        }

        // Causer real nos Logs de Auditoria (Asset/Contract usam LogsActivity,
        // que resolve o causer a partir do guard de auth quando não há
        // ->causedBy() explícito -- ver Spatie\Activitylog\Support\CauserResolver).
        Auth::login($user);

        $this->info("Seedando dataset de demonstração pro tenant de {$user->name} ({$email})...");

        DB::transaction(function () use ($tenantId, $user) {
            $internalUnit = InternalUnit::create([
                'tenant_id' => $tenantId,
                'name' => 'Pátio Principal',
                'code' => 'PATIO-01',
                'is_active' => true,
            ]);

            $categories = $this->createCategoriesAndGroups($tenantId);
            $clients = $this->createClients($tenantId);

            $escavadeira = $this->createAsset($tenantId, $categories['terraplenagem'], $internalUnit, [
                'name' => 'Escavadeira Hidráulica CAT 320',
                'fabricante' => 'Caterpillar',
                'tag' => 'TAG-0101',
                'serial_number' => 'CAT320-SN-884321',
                'capacity_value' => 20,
                'capacity_unit' => 'toneladas',
                'acquisition_value' => 780000.00,
                'acquisition_date' => now()->subYears(2)->subMonths(3),
                'horimetro_inicial' => 0,
                'last_horimetro' => 1284.50,
                'status' => Asset::STATUS_DISPONIVEL,
            ]);

            $retroescavadeira = $this->createAsset($tenantId, $categories['terraplenagem'], $internalUnit, [
                'name' => 'Retroescavadeira 4x4',
                'fabricante' => 'JCB',
                'tag' => 'TAG-0102',
                'serial_number' => 'JCB3CX-SN-552091',
                'capacity_value' => 8,
                'capacity_unit' => 'toneladas',
                'acquisition_value' => 420000.00,
                'acquisition_date' => now()->subYear(),
                'horimetro_inicial' => 0,
                'last_horimetro' => 612.00,
                'status' => Asset::STATUS_DISPONIVEL,
            ]);

            $guindaste = $this->createAsset($tenantId, $categories['icamento'], $internalUnit, [
                'name' => 'Guindaste Munck 15t',
                'fabricante' => 'Hyva',
                'tag' => 'TAG-0201',
                'serial_number' => 'HYVA15-SN-330187',
                'capacity_value' => 15,
                'capacity_unit' => 'toneladas',
                'acquisition_value' => 950000.00,
                'acquisition_date' => now()->subMonths(18),
                'status' => Asset::STATUS_LOCADO,
            ]);

            $guincho = $this->createAsset($tenantId, $categories['icamento'], $internalUnit, [
                'name' => 'Guincho de Reboque Pesado',
                'fabricante' => 'Rotter',
                'tag' => 'TAG-0202',
                'serial_number' => 'ROTTER-SN-119045',
                'capacity_value' => 8,
                'capacity_unit' => 'toneladas',
                'acquisition_value' => 210000.00,
                'acquisition_date' => now()->subMonths(9),
                'status' => Asset::STATUS_DISPONIVEL,
            ]);

            $caminhao = $this->createAsset($tenantId, $categories['transporte'], $internalUnit, [
                'name' => 'Caminhão Truck 6x2 Carroceria',
                'fabricante' => 'Volvo',
                'tag' => 'TAG-0301',
                'serial_number' => 'VOLVOFH-SN-778234',
                'acquisition_value' => 480000.00,
                'acquisition_date' => now()->subMonths(6),
                'status' => Asset::STATUS_DISPONIVEL,
            ]);

            // Horímetro com leituras recentes -- pedido explícito pra "pelo
            // menos uma" das duas de terraplenagem.
            $this->seedHorimeterReadings($tenantId, $escavadeira, $user);

            // Plano de Manutenção Preventiva vinculado por grupo (cobre as
            // duas de terraplenagem de uma vez) + um por frente, pra "grupo
            // também com alguns dados".
            $this->createMaintenancePlan($tenantId, [
                'checklist_group_id' => $categories['terraplenagem']->checklistGroupId,
                'name' => 'Troca de Óleo e Filtros — 250h',
                'interval_hours' => 250,
                'is_critical' => true,
            ]);
            $this->createMaintenancePlan($tenantId, [
                'checklist_group_id' => $categories['icamento']->checklistGroupId,
                'name' => 'Inspeção de Cabos e Talhas — 90 dias',
                'interval_days' => 90,
                'is_critical' => true,
            ]);
            $this->createMaintenancePlan($tenantId, [
                'checklist_group_id' => $categories['transporte']->checklistGroupId,
                'name' => 'Revisão Geral — 10.000km / 90 dias',
                'interval_days' => 90,
                'is_critical' => false,
            ]);

            // Histórico de Trabalho (Ordens de Serviço concluídas) -- alimenta
            // o Dossiê do Ativo de cada equipamento.
            $this->seedWorkHistory($tenantId, $escavadeira, 'Troca de óleo hidráulico e filtros conforme plano preventivo.', MaintenanceOrder::TYPE_PREVENTIVE);
            $this->seedWorkHistory($tenantId, $guindaste, 'Inspeção de cabos de aço e sistema de talhas.', MaintenanceOrder::TYPE_PREVENTIVE);
            $this->seedWorkHistory($tenantId, $caminhao, 'Revisão geral de 10.000km — óleo, filtros e freios.', MaintenanceOrder::TYPE_PREVENTIVE);

            // Planta Baixa (Pátio de Ativos) -- uma posição por equipamento.
            $this->seedStorageLocations($tenantId, $internalUnit, [
                $escavadeira->id => ['row' => 1, 'column' => 1, 'code' => 'P-A1'],
                $retroescavadeira->id => ['row' => 1, 'column' => 2, 'code' => 'P-A2'],
                $guindaste->id => ['row' => 2, 'column' => 1, 'code' => 'P-B1'],
                $guincho->id => ['row' => 2, 'column' => 2, 'code' => 'P-B2'],
                $caminhao->id => ['row' => 3, 'column' => 1, 'code' => 'P-C1'],
            ]);

            // Motorista vinculado ao caminhão + rota/entrega recente.
            $this->seedDriverAndRoute($tenantId, $caminhao);

            // 3 Contratos, um por modalidade de cobrança.
            $this->createContract($tenantId, [
                'client_id' => $clients['rioVerde']->id,
                'asset_id' => $escavadeira->id,
                'contract_number' => 'CT-'.now()->format('Ym').'-TERRA-001',
                'billing_type' => Contract::BILLING_POR_HORA,
                'price' => 185.00,
                'usage_purpose' => 'Terraplenagem — locação por hora',
            ]);
            $this->createContract($tenantId, [
                'client_id' => $clients['emergencial']->id,
                'asset_id' => $guincho->id,
                'contract_number' => 'CT-'.now()->format('Ym').'-GUINCHO-001',
                'billing_type' => Contract::BILLING_DIARIA,
                'price' => 950.00,
                'usage_purpose' => 'Atendimento emergencial — reboque pontual',
            ]);
            $this->createContract($tenantId, [
                'client_id' => $clients['fixo']->id,
                'asset_id' => $caminhao->id,
                'contract_number' => 'CT-'.now()->format('Ym').'-TRANSP-001',
                'billing_type' => Contract::BILLING_MENSAL_FIXO,
                'price' => 18500.00,
                'usage_purpose' => 'Transporte de carga — contrato mensal fixo',
            ]);
        });

        Auth::logout();

        $this->info('Dataset de demonstração criado com sucesso.');

        return self::SUCCESS;
    }

    /**
     * @return array{terraplenagem: object, icamento: object, transporte: object}
     *                                                                            cada item tem ->id (AssetCategory) e ->checklistGroupId (ChecklistGroup)
     */
    private function createCategoriesAndGroups(string $tenantId): array
    {
        $definitions = [
            'terraplenagem' => 'Terraplenagem',
            'icamento' => 'Içamento e Guincho',
            'transporte' => 'Transporte de Carga',
        ];

        $result = [];

        foreach ($definitions as $key => $name) {
            $category = AssetCategory::create(['tenant_id' => $tenantId, 'name' => $name]);
            $group = ChecklistGroup::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'description' => "Checklist padrão de verificação — {$name}.",
            ]);

            $result[$key] = (object) ['id' => $category->id, 'checklistGroupId' => $group->id];
        }

        return $result;
    }

    /**
     * @return array{rioVerde: Client, emergencial: Client, fixo: Client}
     */
    private function createClients(string $tenantId): array
    {
        $rioVerde = Client::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Obras Rio Verde Ltda'],
            ['email' => 'contato@riovergeobras.com.br']
        );

        $emergencial = Client::create([
            'tenant_id' => $tenantId,
            'name' => 'Construtora Resposta Rápida Ltda',
            'email' => 'contato@respostarapida.com.br',
        ]);

        $fixo = Client::create([
            'tenant_id' => $tenantId,
            'name' => 'Distribuidora Rota Fixa SA',
            'email' => 'logistica@rotafixa.com.br',
        ]);

        return ['rioVerde' => $rioVerde, 'emergencial' => $emergencial, 'fixo' => $fixo];
    }

    private function createAsset(string $tenantId, object $category, InternalUnit $internalUnit, array $extra): Asset
    {
        $checklistItems = [
            ['item' => 'Nível de óleo/fluidos verificado', 'status' => true],
            ['item' => 'Pneus/esteiras em boas condições', 'status' => true],
            ['item' => 'Sistema de freios testado', 'status' => true],
            ['item' => 'Sem vazamentos aparentes', 'status' => true],
        ];

        return Asset::create(array_merge([
            'tenant_id' => $tenantId,
            'asset_category_id' => $category->id,
            'checklist_group_id' => $category->checklistGroupId,
            'internal_unit_id' => $internalUnit->id,
            'cep' => '13010-001',
            'endereco' => 'Av. José Bonifácio, 1200, Cambuí, Campinas - SP',
            'latitude' => -22.9068,
            'longitude' => -47.0626,
            'useful_life_years' => 10,
            'residual_value' => 0,
            'checklist' => $checklistItems,
        ], $extra));
    }

    private function seedHorimeterReadings(string $tenantId, Asset $asset, User $user): void
    {
        HorimeterReading::create([
            'tenant_id' => $tenantId,
            'asset_id' => $asset->id,
            'reading' => 1260.00,
            'recorded_at' => now()->subDays(5),
            'recorded_by' => $user->id,
            'source' => HorimeterReading::SOURCE_MANUAL,
            'notes' => 'Leitura de rotina antes de envio pra obra.',
        ]);

        HorimeterReading::create([
            'tenant_id' => $tenantId,
            'asset_id' => $asset->id,
            'reading' => 1284.50,
            'recorded_at' => now()->subDay(),
            'recorded_by' => $user->id,
            'source' => HorimeterReading::SOURCE_MANUAL,
            'notes' => 'Leitura atual.',
        ]);
    }

    private function createMaintenancePlan(string $tenantId, array $data): MaintenancePlan
    {
        return MaintenancePlan::create(array_merge([
            'tenant_id' => $tenantId,
            'is_active' => true,
            'source' => 'manual',
            'notes' => 'Criado via seed de demonstração.',
        ], $data));
    }

    private function seedWorkHistory(string $tenantId, Asset $asset, string $description, string $type): void
    {
        MaintenanceOrder::create([
            'tenant_id' => $tenantId,
            'asset_id' => $asset->id,
            'maintenance_type' => $type,
            'description' => $description,
            'status' => 'Concluída',
            'started_at' => now()->subDays(10),
            'finished_at' => now()->subDays(9),
            'labor_cost' => 350.00,
            'material_cost' => 480.00,
            'total_order_cost' => 830.00,
        ]);
    }

    /**
     * @param  array<string, array{row:int, column:int, code:string}>  $positions  asset_id => posição
     */
    private function seedStorageLocations(string $tenantId, InternalUnit $internalUnit, array $positions): void
    {
        foreach ($positions as $assetId => $position) {
            $location = StorageLocation::create([
                'tenant_id' => $tenantId,
                'internal_unit_id' => $internalUnit->id,
                'context' => StorageLocation::CONTEXT_PATIO_ATIVOS,
                'code' => $position['code'],
                'label' => $position['code'],
                'row' => $position['row'],
                'column' => $position['column'],
                'is_active' => true,
            ]);

            Asset::where('id', $assetId)->update(['storage_location_id' => $location->id]);
        }
    }

    private function seedDriverAndRoute(string $tenantId, Asset $caminhao): void
    {
        $driver = FleetDriver::create([
            'tenant_id' => $tenantId,
            'name' => 'Carlos Roberto da Silva',
            'cpf' => '123.456.789-00',
            'phone' => '(19) 99876-5432',
            'employment_type' => FleetDriver::EMPLOYMENT_PROPRIO,
            'cnh_number' => '01234567890',
            'cnh_category' => 'E',
            'cnh_expiry_date' => now()->addYears(2),
            'active' => true,
        ]);

        EquipmentMovement::create([
            'tenant_id' => $tenantId,
            'asset_id' => $caminhao->id,
            'fleet_driver_id' => $driver->id,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
            'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'km_inicial' => 45200,
            'km_final' => 45680,
            'scheduled_at' => now()->subDays(2),
            'started_at' => now()->subDays(2),
            'completed_at' => now()->subDay(),
            'custo_transporte' => 620.00,
        ]);
    }

    private function createContract(string $tenantId, array $data): Contract
    {
        return Contract::create(array_merge([
            'tenant_id' => $tenantId,
            'status' => 'Ativo',
            'start_date' => now()->subDays(15),
        ], $data));
    }
}
