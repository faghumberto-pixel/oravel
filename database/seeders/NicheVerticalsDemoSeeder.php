<?php

namespace Database\Seeders;

use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\BillingPlan;
use App\Models\Client;
use App\Models\Contract;
use App\Models\CriticalityLevel;
use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItemTemplate;
use App\Models\EquipmentPatioArrival;
use App\Models\EquipmentPatioArrivalItem;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\TenantProvisioner;
use Illuminate\Database\Seeder;

/**
 * Demonstra as funcionalidades de nicho (Prazo Fatal/Quarentena/Banco de
 * Carga, SLA de Emergência, Kanban de Oficina extra/Mau Uso) e o sistema de
 * Segmentação Dinâmica (Tenant.segment + enabled_modules + Contas a
 * Receber), um tenant pequeno por segmento. Idempotente: cada tenant só é
 * criado se ainda não existir (por slug).
 *
 * Uso: php artisan db:seed --class=NicheVerticalsDemoSeeder
 */
class NicheVerticalsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedEventos();
        $this->seedIndustrialHospitalar();
        $this->seedConstrucaoCivil();

        $this->command?->info('Niches: 3 tenants de demonstração prontos (segmentacao dinamica + contas a receber).');
    }

    private function ensurePlan(): Plan
    {
        return Plan::firstOrCreate(
            ['name' => 'Plano Demo Nichos'],
            [
                'price' => 100, 'base_price' => 100, 'level' => 1,
                'billing_cycle' => 'monthly', 'is_active' => true,
                'features' => [
                    'tabela_assets', 'tabela_asset_categories', 'tabela_clients', 'tabela_contracts',
                    'tabela_maintenance_orders', 'tabela_equipment_movements', 'tabela_equipment_damages',
                    'tabela_account_receivables', 'tabela_billing_plans', 'tabela_roles', 'tabela_users',
                ],
            ]
        );
    }

    // ------------------------------------------------------------------
    // 1. Eventos -- Prazo Fatal + Banco de Carga + Quarentena
    // ------------------------------------------------------------------

    private function seedEventos(): void
    {
        $slug = 'eventos-show-geradores';

        if (Tenant::where('slug', $slug)->exists()) {
            $this->command?->info("Tenant '{$slug}' já existe -- pulando.");

            return;
        }

        $this->command?->info('Semeando tenant Eventos Show Geradores...');

        $tenant = Tenant::create([
            'name' => 'Eventos Show Geradores',
            'slug' => $slug,
            'status' => 'active',
            'plan_id' => $this->ensurePlan()->id,
            'onboarding_completed' => true,
            'segment' => Client::NICHE_EVENTOS,
            'enabled_modules' => Tenant::applySegmentPreset(Client::NICHE_EVENTOS),
        ]);

        $admin = TenantProvisioner::provision($tenant, [
            'name' => 'Admin Eventos Show',
            'email' => 'admin@eventosshow.com.br',
            'password' => 'Demo@Oravel1',
        ]);

        $client = Client::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Festival Verão Produções'],
            ['activity_type' => Client::NICHE_EVENTOS, 'city' => 'São Paulo', 'uf' => 'SP']
        );

        $assetMontagem = Asset::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tag' => 'GER-EVT-MONTAGEM'],
            ['name' => 'Gerador Cummins 150 kVA (Palco Principal)', 'status' => Asset::STATUS_LOCADO, 'capacity_value' => 150, 'capacity_unit' => 'kVA']
        );

        // Prazo Fatal: montagem de palco amanhã de manhã, sem margem de atraso.
        MaintenanceOrder::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $assetMontagem->id, 'description' => 'Montagem e teste do gerador do palco principal'],
            [
                'technician_id' => $admin->id, 'client_id' => $client->id,
                'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
                'status' => 'Aberto', 'internal_status' => 'aguardando_diagnostico',
                'is_prazo_fatal' => true,
                'prazo_fatal_at' => now()->addDay()->setTime(8, 0),
            ]
        );

        // Banco de Carga: movimentação de mobilização já testada antes da saída.
        $movMobilizacao = EquipmentMovement::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $assetMontagem->id, 'type' => EquipmentMovement::TYPE_MOBILIZACAO],
            [
                'status' => EquipmentMovement::STATUS_CONCLUIDO,
                'completed_at' => now()->subHours(6),
                'load_bank_tested' => true,
                'load_bank_notes' => 'Testado a 120kW por 40min antes do embarque -- sem quedas de tensão.',
            ]
        );

        // Quarentena: um segundo gerador voltou de um evento com avaria e fica
        // retido ate revisao manual (nao libera pra Disponivel sozinho).
        $assetRetorno = Asset::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tag' => 'GER-EVT-RETORNO'],
            ['name' => 'Gerador MWM 100 kVA (Retorno de Evento)', 'status' => Asset::STATUS_QUARENTENA, 'capacity_value' => 100, 'capacity_unit' => 'kVA']
        );

        $itemTemplate = EquipmentMovementItemTemplate::firstOrCreate(
            ['type' => EquipmentPatioArrival::TEMPLATE_TYPE, 'label' => 'Inspeção visual'],
            ['section' => 'Geral', 'sort_order' => 1, 'requires_photo' => false]
        );

        $movRetorno = EquipmentMovement::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $assetRetorno->id, 'type' => EquipmentMovement::TYPE_DESMOBILIZACAO],
            ['status' => EquipmentMovement::STATUS_CONCLUIDO, 'completed_at' => now()->subDay()]
        );

        $arrival = EquipmentPatioArrival::firstOrCreate(
            ['tenant_id' => $tenant->id, 'equipment_movement_id' => $movRetorno->id],
            [
                'arrived_at' => now()->subDay()->addHours(2),
                'confirmed_by_user_id' => $admin->id,
                'completed_at' => now()->subDay()->addHours(3),
                'initial_condition_notes' => 'Carenagem amassada no transporte -- aguardando avaliação da oficina.',
            ]
        );

        EquipmentPatioArrivalItem::firstOrCreate(
            ['tenant_id' => $tenant->id, 'equipment_patio_arrival_id' => $arrival->id, 'label' => $itemTemplate->label],
            ['sort_order' => 1, 'requires_photo' => false, 'is_checked' => true, 'has_damage' => true, 'notes' => 'Amassado visível na lateral direita.']
        );
    }

    // ------------------------------------------------------------------
    // 2. Industrial/Hospitalar -- SLA de Emergência + Horímetro rígido + Contas a Receber
    // ------------------------------------------------------------------

    private function seedIndustrialHospitalar(): void
    {
        $slug = 'hospital-vida-plena-energia';

        if (Tenant::where('slug', $slug)->exists()) {
            $this->command?->info("Tenant '{$slug}' já existe -- pulando.");

            return;
        }

        $this->command?->info('Semeando tenant Hospital Vida Plena Energia...');

        $tenant = Tenant::create([
            'name' => 'Hospital Vida Plena Energia',
            'slug' => $slug,
            'status' => 'active',
            'plan_id' => $this->ensurePlan()->id,
            'onboarding_completed' => true,
            'segment' => Client::NICHE_INDUSTRIAL_HOSPITALAR,
            'enabled_modules' => Tenant::applySegmentPreset(Client::NICHE_INDUSTRIAL_HOSPITALAR),
        ]);

        $admin = TenantProvisioner::provision($tenant, [
            'name' => 'Admin Vida Plena',
            'email' => 'admin@vidaplenaenergia.com.br',
            'password' => 'Demo@Oravel1',
        ]);

        $client = Client::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Hospital Vida Plena -- UTI Adulto'],
            ['activity_type' => Client::NICHE_INDUSTRIAL_HOSPITALAR, 'city' => 'Campinas', 'uf' => 'SP']
        );

        $criticidadeAlta = CriticalityLevel::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'alta'],
            ['name' => 'Alta', 'color' => '#ef4444', 'is_urgent' => true]
        );

        $asset = Asset::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tag' => 'GER-UTI-BACKUP'],
            [
                'name' => 'Gerador Caterpillar 500 kVA (Backup UTI)', 'status' => Asset::STATUS_LOCADO,
                'capacity_value' => 500, 'capacity_unit' => 'kVA', 'horimetro_atual' => 4820, 'last_horimetro' => 4820,
            ]
        );

        // SLA de Emergencia: chamado aberto ha 50min com meta de 60min --
        // fica no amarelo (>=70% do prazo), demonstrando slaColor().
        MaintenanceOrder::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Queda de tensão no gerador de backup da UTI'],
            [
                'technician_id' => $admin->id, 'client_id' => $client->id,
                'maintenance_type' => MaintenanceOrder::TYPE_EMERGENCIA,
                'status' => 'Aberto', 'internal_status' => 'aguardando_diagnostico',
                'sla_target_minutes' => 60,
                'created_at' => now()->subMinutes(50),
                'updated_at' => now()->subMinutes(50),
            ]
        );

        // Contas a Receber: contrato com multa rescisoria real (usada como
        // sugestao padrao em AccountReceivable::calculateLateFee()), um
        // Plano de Cobranca mensal, e uma conta ja vencida (multa aplicavel).
        $contract = Contract::firstOrCreate(
            ['tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id],
            [
                'contract_number' => 'CT-VP-0001', 'status' => 'Ativo', 'start_date' => now()->subMonths(6),
                'price' => 3800.00, 'multa_rescisoria' => 8, 'is_active' => true,
                'payment_method' => 'Boleto', 'usage_purpose' => 'Backup de energia -- UTI Adulto',
            ]
        );

        $billingPlan = BillingPlan::firstOrCreate(
            ['tenant_id' => $tenant->id, 'client_id' => $client->id, 'contract_id' => $contract->id],
            ['frequency' => BillingPlan::FREQUENCY_MONTHLY, 'amount' => 3800.00, 'due_day' => 10, 'active' => true]
        );

        AccountReceivable::firstOrCreate(
            ['tenant_id' => $tenant->id, 'client_id' => $client->id, 'contract_id' => $contract->id, 'billing_plan_id' => $billingPlan->id, 'description' => 'Mensalidade backup UTI -- venc. atrasado'],
            ['amount' => 3800.00, 'due_date' => now()->subDays(4), 'status' => 'atrasado']
        );
    }

    // ------------------------------------------------------------------
    // 3. Construção Civil -- Kanban de Oficina extra + Mau Uso
    // ------------------------------------------------------------------

    private function seedConstrucaoCivil(): void
    {
        $slug = 'construtora-alicerce-locacoes';

        if (Tenant::where('slug', $slug)->exists()) {
            $this->command?->info("Tenant '{$slug}' já existe -- pulando.");

            return;
        }

        $this->command?->info('Semeando tenant Construtora Alicerce Locações...');

        // Preset padrao de Construcao Civil deixa sla_emergencia desligado --
        // este tenant liga na mao mesmo assim (demonstra que enabled_modules
        // e' editavel alem do preset do segmento, nao so' um espelho fixo dele).
        $enabledModules = Tenant::applySegmentPreset(Client::NICHE_CONSTRUCAO_CIVIL);
        $enabledModules['sla_emergencia'] = true;

        $tenant = Tenant::create([
            'name' => 'Construtora Alicerce Locações',
            'slug' => $slug,
            'status' => 'active',
            'plan_id' => $this->ensurePlan()->id,
            'onboarding_completed' => true,
            'segment' => Client::NICHE_CONSTRUCAO_CIVIL,
            'enabled_modules' => $enabledModules,
        ]);

        $admin = TenantProvisioner::provision($tenant, [
            'name' => 'Admin Alicerce',
            'email' => 'admin@alicerceloca.com.br',
            'password' => 'Demo@Oravel1',
        ]);

        $client = Client::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Obra Residencial Jardim das Palmeiras'],
            ['activity_type' => Client::NICHE_CONSTRUCAO_CIVIL, 'city' => 'Sorocaba', 'uf' => 'SP']
        );

        $asset = Asset::firstOrCreate(
            ['tenant_id' => $tenant->id, 'tag' => 'GER-OBRA-CANIBALIZADO'],
            ['name' => 'Gerador Stemac 60 kVA (Canibalizado)', 'status' => Asset::STATUS_MANUTENCAO, 'capacity_value' => 60, 'capacity_unit' => 'kVA']
        );

        // Kanban de Oficina extra: coluna so' existe quando kanban_oficina_extra esta ligado.
        $order = MaintenanceOrder::firstOrCreate(
            ['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Peça retirada de outro equipamento parado -- aguardando fornecedor'],
            [
                'technician_id' => $admin->id, 'client_id' => $client->id,
                'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
                'status' => 'Em Andamento', 'internal_status' => 'aguardando_peca_canibalizado',
            ]
        );

        $movement = EquipmentMovement::firstOrCreate(
            ['tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id, 'asset_id' => $asset->id, 'type' => EquipmentMovement::TYPE_DESMOBILIZACAO],
            ['status' => EquipmentMovement::STATUS_CONCLUIDO, 'completed_at' => now()->subDays(2)]
        );

        // Mau Uso: mesma trilha que StoresPhotoEvidence::persistPhotoEvidences()
        // cria quando severity='mau_uso' no repeater de evidencias da OS.
        EquipmentDamage::firstOrCreate(
            ['tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id, 'asset_id' => $asset->id, 'equipment_movement_id' => $movement->id],
            [
                'reported_by_user_id' => $admin->id,
                'severity' => EquipmentDamage::SEVERITY_MODERADA,
                'damage_type' => EquipmentDamage::DAMAGE_TYPE_ESTRUTURAL,
                'description' => "Mau uso detectado na OS #{$order->os_number}",
                'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
            ]
        );
    }
}
