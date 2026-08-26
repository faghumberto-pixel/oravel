<?php

namespace Database\Seeders;

use App\Domain\Fleet\Models\ForkliftSpecification;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Contract;
use App\Models\MaintenanceDueAlert;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Role;
use App\Models\TechnicianAllocation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSpecialty;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Expansão de volume do tenant Empilhadeiras Demo (ver
 * EmpilhadeirasDemoSeeder, que cria o tenant com 1 cliente/3 ativos) --
 * pedido do usuário 2026-08-26 pra ter pelo menos 10 registros em Clients/
 * Assets/Contracts/técnicos+especialidades/OS+alocações/alertas PMP, pra
 * testar de verdade as páginas novas da área PMP (Dashboard, Alocação de
 * Técnicos, Consulta por Cliente) com volume real.
 *
 * Idempotente por prefixo: cada registro criado aqui tem um marcador no
 * nome/tag ("Vol-" ou similar) e a checagem de "já rodou" é por contagem
 * mínima, não por nome exato -- rodar de novo não duplica se já bateu 10+.
 *
 * Uso: php artisan db:seed --class=EmpilhadeirasDemoVolumeSeeder
 */
class EmpilhadeirasDemoVolumeSeeder extends Seeder
{
    private const SLUG = 'empilhadeiras-demo';

    private const TARGET = 10;

    private Tenant $tenant;

    public function run(): void
    {
        $tenant = Tenant::where('slug', self::SLUG)->first();

        if (! $tenant) {
            $this->command?->error("Tenant '".self::SLUG."' não existe -- rode EmpilhadeirasDemoSeeder primeiro.");

            return;
        }

        $this->tenant = $tenant;

        $clients = $this->ensureClients();
        $assets = $this->ensureAssets();
        $this->ensureContracts($clients, $assets);
        $this->ensureMaintenancePlans($assets);
        $technicians = $this->ensureTechniciansWithSpecialties();
        $this->ensureMaintenanceOrders($assets, $technicians);
        $this->ensureAllocations($technicians);
        $this->ensureDueAlerts($assets);

        $this->command?->info('Empilhadeiras Demo: volume de dados garantido (10+ em cada tabela pedida).');
    }

    /** @return Collection<int, Client> */
    private function ensureClients()
    {
        $existing = Client::where('tenant_id', $this->tenant->id)->get();
        $needed = max(0, self::TARGET - $existing->count());

        $names = [
            'Distribuidora Vale Norte', 'Armazém Central Logística', 'Portocargas Movimentação',
            'Metalúrgica Sul Industrial', 'CD Express Distribuição', 'Grupo Atlas Armazenagem',
            'Comercial Rota Verde', 'Indústria Bento Peças', 'Logística Silva & Filhos',
            'Centro de Distribuição Prime', 'Armazéns Reunidos SP', 'Transportadora Horizonte',
        ];

        for ($i = 0; $i < $needed; $i++) {
            $name = $names[$i % count($names)].' '.($i + 1);
            $existing->push(Client::create([
                'tenant_id' => $this->tenant->id,
                'name' => $name,
                'city' => 'Campinas',
                'uf' => 'SP',
                'activity_type' => Client::NICHE_CONSTRUCAO_CIVIL,
            ]));
        }

        return $existing;
    }

    /** @return Collection<int, Asset> */
    private function ensureAssets()
    {
        $category = AssetCategory::firstOrCreate(['tenant_id' => $this->tenant->id, 'name' => 'Empilhadeira']);

        $existing = Asset::where('tenant_id', $this->tenant->id)->get();
        $needed = max(0, self::TARGET - $existing->count());

        $models = [
            ['brand' => 'Toyota', 'model' => '8FBE15', 'capacity' => 1500],
            ['brand' => 'Hyster', 'model' => 'H2.5FT', 'capacity' => 2500],
            ['brand' => 'Still', 'model' => 'RX20-14', 'capacity' => 1400],
            ['brand' => 'Yale', 'model' => 'GLP050', 'capacity' => 2300],
            ['brand' => 'Linde', 'model' => 'H30D', 'capacity' => 3000],
            ['brand' => 'Clark', 'model' => 'C25', 'capacity' => 2500],
            ['brand' => 'Crown', 'model' => 'FC4500', 'capacity' => 2000],
            ['brand' => 'Mitsubishi', 'model' => 'FG25N', 'capacity' => 2500],
        ];

        $nextSeq = $existing->count() + 1;

        for ($i = 0; $i < $needed; $i++) {
            $spec = $models[$i % count($models)];
            $seq = str_pad((string) ($nextSeq + $i), 3, '0', STR_PAD_LEFT);

            $asset = Asset::create([
                'tenant_id' => $this->tenant->id,
                'name' => 'Empilhadeira '.$spec['brand'].' '.$spec['model'].' #'.$seq,
                'tag' => 'EMP-VOL-'.$seq,
                'patrimonio' => 'PAT-VOL-'.$seq,
                'serial_number' => strtoupper($spec['brand']).'-'.$seq.'-VOL',
                'status' => [Asset::STATUS_DISPONIVEL, Asset::STATUS_LOCADO, Asset::STATUS_MANUTENCAO][$i % 3],
                'asset_category_id' => $category->id,
                'capacity_value' => $spec['capacity'],
                'capacity_unit' => 'kg',
                'horimetro_inicial' => 0,
                'horimetro_atual' => rand(50, 3000),
            ]);

            ForkliftSpecification::create([
                'tenant_id' => $this->tenant->id,
                'asset_id' => $asset->id,
                'load_capacity_kg' => $spec['capacity'],
                'lift_height_m' => 4.5,
                'energy_type' => $i % 2 === 0 ? ForkliftSpecification::ENERGY_ELETRICA : ForkliftSpecification::ENERGY_GLP,
                'mast_type' => ForkliftSpecification::MAST_DUPLA_DUPLEX,
                'tire_type' => ForkliftSpecification::TIRE_PNEUMATICO,
            ]);

            $existing->push($asset);
        }

        return $existing;
    }

    private function ensureContracts($clients, $assets): void
    {
        $existing = Contract::where('tenant_id', $this->tenant->id)->count();
        $needed = max(0, self::TARGET - $existing);

        $assetsWithoutActiveContract = $assets->filter(
            fn (Asset $asset) => ! Contract::where('tenant_id', $this->tenant->id)
                ->where('asset_id', $asset->id)->where('is_active', true)->exists()
        )->values();

        $seq = $existing + 1;

        for ($i = 0; $i < $needed && $i < $assetsWithoutActiveContract->count(); $i++) {
            Contract::create([
                'tenant_id' => $this->tenant->id,
                'asset_id' => $assetsWithoutActiveContract[$i]->id,
                'client_id' => $clients[$i % $clients->count()]->id,
                'contract_number' => 'CT-VOL-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'status' => 'Ativo',
                'start_date' => now()->subMonths(rand(1, 12)),
                'price' => rand(2500, 6000),
                'billing_type' => Contract::BILLING_MENSAL_FIXO,
                'payment_method' => 'Boleto',
                'is_active' => true,
                'initial_horimeter' => 0,
            ]);
            $seq++;
        }
    }

    /** @return Collection<int, User> */
    private function ensureTechniciansWithSpecialties()
    {
        $existing = User::where('tenant_id', $this->tenant->id)->get();
        $needed = max(0, self::TARGET - $existing->count());

        $role = Role::firstOrCreate([
            'name' => 'Técnico', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id,
        ]);

        $names = [
            'Carlos Silva', 'Marcos Oliveira', 'Fernanda Costa', 'Roberto Alves', 'Juliana Santos',
            'Paulo Ferreira', 'Ana Paula Souza', 'Ricardo Lima', 'Bruno Martins', 'Camila Rocha',
        ];

        $specialtyOptions = [
            MaintenanceOrder::FAILURE_CATEGORY_ELETRICO,
            MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO,
            MaintenanceOrder::FAILURE_CATEGORY_MOTOR,
            MaintenanceOrder::FAILURE_CATEGORY_PNEU_ESTEIRA,
            MaintenanceOrder::FAILURE_CATEGORY_ESTRUTURAL,
        ];

        for ($i = 0; $i < $needed; $i++) {
            $name = $names[$i % count($names)];
            $seq = $i + 1;

            $technician = User::create([
                'tenant_id' => $this->tenant->id,
                'name' => $name.' (Vol '.$seq.')',
                'email' => 'tecnico.vol'.$seq.'@empilhadeirasdemo.com.br',
                'password' => bcrypt('Demo@Oravel1'),
                'is_approved' => true,
            ]);
            $technician->forceFill(['email_verified_at' => now()])->save();
            $technician->assignRole($role);

            // 1 ou 2 especialidades por técnico, variando pra ter mistura
            // real na hora de testar o aviso de especialidade incompatível.
            UserSpecialty::create(['user_id' => $technician->id, 'specialty' => $specialtyOptions[$i % count($specialtyOptions)]]);
            if ($i % 3 === 0) {
                UserSpecialty::create(['user_id' => $technician->id, 'specialty' => $specialtyOptions[($i + 1) % count($specialtyOptions)]]);
            }

            $existing->push($technician);
        }

        return $existing;
    }

    private function ensureMaintenanceOrders($assets, $technicians): void
    {
        $existing = MaintenanceOrder::where('tenant_id', $this->tenant->id)->count();
        $needed = max(0, self::TARGET - $existing);

        $failureCategories = [
            MaintenanceOrder::FAILURE_CATEGORY_ELETRICO,
            MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO,
            MaintenanceOrder::FAILURE_CATEGORY_MOTOR,
            MaintenanceOrder::FAILURE_CATEGORY_PNEU_ESTEIRA,
        ];
        $internalStatuses = ['aguardando_diagnostico', 'em_manutencao', 'teste_qualidade', 'concluido'];

        for ($i = 0; $i < $needed; $i++) {
            $isCorrective = $i % 2 === 0;
            $asset = $assets[$i % $assets->count()];
            $technician = $i % 4 === 0 ? null : $technicians[$i % $technicians->count()];
            $internalStatus = $internalStatuses[$i % count($internalStatuses)];

            MaintenanceOrder::create([
                'tenant_id' => $this->tenant->id,
                'asset_id' => $asset->id,
                'technician_id' => $technician?->id,
                'maintenance_type' => $isCorrective ? MaintenanceOrder::TYPE_CORRECTIVE : MaintenanceOrder::TYPE_PREVENTIVE,
                'failure_category' => $isCorrective ? $failureCategories[$i % count($failureCategories)] : null,
                'description' => $isCorrective ? 'Falha reportada #'.($i + 1).' — inspeção necessária' : 'Revisão preventiva programada #'.($i + 1),
                'internal_status' => $internalStatus,
                'status' => match ($internalStatus) {
                    'concluido' => 'Concluída',
                    'aguardando_diagnostico' => 'Aberto',
                    default => 'Em Andamento',
                },
                'scheduled_at' => now()->addDays(rand(-5, 20)),
                'finished_at' => $internalStatus === 'concluido' ? now()->subDays(rand(1, 10)) : null,
            ]);
        }
    }

    private function ensureAllocations($technicians): void
    {
        $existing = TechnicianAllocation::where('tenant_id', $this->tenant->id)->count();
        $needed = max(0, self::TARGET - $existing);

        $ordersWithoutAllocation = MaintenanceOrder::where('tenant_id', $this->tenant->id)
            ->whereDoesntHave('technicianAllocations')
            ->get();

        for ($i = 0; $i < $needed && $i < $ordersWithoutAllocation->count(); $i++) {
            $order = $ordersWithoutAllocation[$i];
            $technician = $technicians[$i % $technicians->count()];
            $starts = now()->addDays(rand(-3, 15))->setTime(8 + ($i % 8), 0);

            TechnicianAllocation::create([
                'tenant_id' => $this->tenant->id,
                'technician_id' => $technician->id,
                'maintenance_order_id' => $order->id,
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->addHours(2),
                'status' => TechnicianAllocation::STATUS_PLANEJADO,
            ]);
        }
    }

    /**
     * O tenant base (EmpilhadeirasDemoSeeder) nunca cria MaintenancePlan --
     * só MaintenanceOrder avulsas. Sem plano não tem como gerar
     * MaintenanceDueAlert (a coluna "A Fazer" do Dashboard PMP), então
     * garante 1 plano por ativo antes de gerar os alertas.
     */
    private function ensureMaintenancePlans($assets): void
    {
        foreach ($assets as $asset) {
            $hasPlan = MaintenancePlan::where('tenant_id', $this->tenant->id)
                ->where('asset_id', $asset->id)
                ->exists();

            if ($hasPlan) {
                continue;
            }

            MaintenancePlan::create([
                'tenant_id' => $this->tenant->id,
                'asset_id' => $asset->id,
                'name' => 'Revisão preventiva 250h',
                'interval_hours' => 250,
                'last_service_hours' => max(0, (float) $asset->horimetro_atual - 260),
            ]);
        }
    }

    private function ensureDueAlerts($assets): void
    {
        $existing = MaintenanceDueAlert::where('tenant_id', $this->tenant->id)->count();
        $needed = max(0, self::TARGET - $existing);

        $plans = MaintenancePlan::where('tenant_id', $this->tenant->id)
            ->whereIn('asset_id', $assets->pluck('id'))
            ->get();

        if ($plans->isEmpty()) {
            $this->command?->warn('Nenhum MaintenancePlan encontrado pros ativos -- pulando MaintenanceDueAlert.');

            return;
        }

        $created = 0;
        $planIndex = 0;

        while ($created < $needed && $planIndex < $plans->count() * 3) {
            $plan = $plans[$planIndex % $plans->count()];
            $planIndex++;

            $alreadyAlerted = MaintenanceDueAlert::where('tenant_id', $this->tenant->id)
                ->where('asset_id', $plan->asset_id)
                ->where('maintenance_plan_id', $plan->id)
                ->exists();

            if ($alreadyAlerted || ! $plan->asset_id) {
                continue;
            }

            MaintenanceDueAlert::create([
                'tenant_id' => $this->tenant->id,
                'asset_id' => $plan->asset_id,
                'maintenance_plan_id' => $plan->id,
                'alerted_at' => now()->subDays(rand(1, 6)),
            ]);
            $created++;
        }
    }
}
