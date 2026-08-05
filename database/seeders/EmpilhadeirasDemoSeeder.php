<?php

namespace Database\Seeders;

use App\Domain\Fleet\Models\ForkliftSpecification;
use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDowntimeEvent;
use App\Models\Client;
use App\Models\Contract;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Database\Seeder;

/**
 * Cenario de demonstracao comercial do modulo vertical de Frotas/
 * Empilhadeiras (app/Domain/Fleet, 2026-08-05) -- tenant isolado, dedicado
 * so' a esta demo, pra nao misturar com dados de tenants reais nem com os
 * outros tenants de demo (Torres & Guindastes, Geradores RMC). Idempotente:
 * se o tenant 'empilhadeiras-demo' ja existe, nao roda de novo.
 *
 * Uso: php artisan db:seed --class=EmpilhadeirasDemoSeeder
 */
class EmpilhadeirasDemoSeeder extends Seeder
{
    private const SLUG = 'empilhadeiras-demo';

    public function run(): void
    {
        $tenant = Tenant::where('slug', self::SLUG)->first();

        if ($tenant) {
            $this->command?->info("Tenant '".self::SLUG."' já existe -- nada a fazer.");

            return;
        }

        $this->command?->info('Semeando tenant Empilhadeiras Demo...');

        $plan = $this->ensurePlan();

        $tenant = Tenant::create([
            'name' => 'Empilhadeiras Demo',
            'slug' => self::SLUG,
            'status' => 'active',
            'plan_id' => $plan->id,
            'onboarding_completed' => true,
        ]);

        TenantProvisioner::provision($tenant, [
            'name' => 'Admin Empilhadeiras Demo',
            'email' => 'admin@empilhadeirasdemo.com.br',
            'password' => 'Demo@Oravel1',
        ]);

        $technician = User::where('tenant_id', $tenant->id)
            ->where('email', 'admin@empilhadeirasdemo.com.br')->firstOrFail();

        $client = $this->seedClient($tenant);
        $assets = $this->seedAssets($tenant);
        $this->seedContractWithFranchise($tenant, $assets['toyota'], $client);
        $this->seedHorimeterHistory($tenant, $assets);
        $this->seedMaintenanceHistory($tenant, $assets, $technician);
        $this->seedDowntimeHistory($tenant, $assets);

        $this->command?->info('Empilhadeiras Demo: cenário pronto (admin@empilhadeirasdemo.com.br / Demo@Oravel1).');
    }

    /**
     * Plano dedicado (nao reaproveita "Plano Demo Comercial" dos outros
     * tenants de demo) -- inclui as 3 features novas do modulo Fleet, que
     * os planos antigos nao tem.
     */
    private function ensurePlan(): Plan
    {
        return Plan::firstOrCreate(
            ['name' => 'Plano Demo Empilhadeiras'],
            [
                'price' => 100, 'base_price' => 100, 'level' => 1,
                'billing_cycle' => 'monthly', 'is_active' => true,
                'features' => [
                    'tabela_assets', 'tabela_asset_categories', 'tabela_clients', 'tabela_contracts',
                    'tabela_maintenance_orders', 'tabela_maintenance_plans', 'tabela_horimeter_readings',
                    'tabela_asset_downtime_events', 'tabela_roles', 'tabela_users',
                    'tabela_rental_hour_franchises', 'tabela_rental_overage_charges',
                ],
            ]
        );
    }

    private function seedClient(Tenant $tenant): Client
    {
        return Client::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Distribuidora Intralog Ltda'],
            [
                'address' => 'Rod. Anhanguera, km 104 - Distrito Industrial, Campinas - SP',
                'city' => 'Campinas',
                'uf' => 'SP',
                'activity_type' => Client::NICHE_CONSTRUCAO_CIVIL,
            ]
        );
    }

    /** @return array<string, Asset> */
    private function seedAssets(Tenant $tenant): array
    {
        $category = AssetCategory::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Empilhadeira']
        );

        $assets = [];

        $assets['toyota'] = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira Toyota 8FBE20',
            'tag' => 'EMP-TOYOTA-001', 'patrimonio' => 'PAT-EMP-001',
            'serial_number' => 'TY8FBE20-2024-0091', 'status' => Asset::STATUS_LOCADO,
            'asset_category_id' => $category->id, 'client_id' => null,
            'capacity_value' => 2000, 'capacity_unit' => 'kg',
            'horimetro_inicial' => 0, 'horimetro_atual' => 0,
        ]);
        ForkliftSpecification::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assets['toyota']->id,
            'load_capacity_kg' => 2000, 'lift_height_m' => 4.50,
            'energy_type' => ForkliftSpecification::ENERGY_ELETRICA,
            'mast_type' => ForkliftSpecification::MAST_TRIPLA,
            'tire_type' => ForkliftSpecification::TIRE_POLIURETANO,
            'battery_voltage' => '80V', 'battery_amperage_ah' => 700, 'battery_cycles' => 320,
            'battery_serial_number' => 'BAT-TY-0091', 'charger_model' => 'Toyota TCPC-80',
        ]);

        $assets['hyster'] = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira Hyster H3.5FT',
            'tag' => 'EMP-HYSTER-002', 'patrimonio' => 'PAT-EMP-002',
            'serial_number' => 'HY35FT-2023-0044', 'status' => Asset::STATUS_DISPONIVEL,
            'asset_category_id' => $category->id,
            'capacity_value' => 3500, 'capacity_unit' => 'kg',
            'horimetro_inicial' => 0, 'horimetro_atual' => 0,
        ]);
        ForkliftSpecification::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assets['hyster']->id,
            'load_capacity_kg' => 3500, 'lift_height_m' => 5.00,
            'energy_type' => ForkliftSpecification::ENERGY_GLP,
            'mast_type' => ForkliftSpecification::MAST_DUPLA_DUPLEX,
            'tire_type' => ForkliftSpecification::TIRE_PNEUMATICO,
        ]);

        $assets['still'] = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira Still RX20-16 Retrátil',
            'tag' => 'EMP-STILL-003', 'patrimonio' => 'PAT-EMP-003',
            'serial_number' => 'ST-RX2016-2022-0177', 'status' => Asset::STATUS_MANUTENCAO,
            'asset_category_id' => $category->id,
            'capacity_value' => 1600, 'capacity_unit' => 'kg',
            'horimetro_inicial' => 0, 'horimetro_atual' => 0,
        ]);
        ForkliftSpecification::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assets['still']->id,
            'load_capacity_kg' => 1600, 'lift_height_m' => 8.20,
            'energy_type' => ForkliftSpecification::ENERGY_ELETRICA,
            'mast_type' => ForkliftSpecification::MAST_RETRATIL,
            'tire_type' => ForkliftSpecification::TIRE_POLIURETANO,
            'battery_voltage' => '48V', 'battery_amperage_ah' => 620, 'battery_cycles' => 890,
            'battery_serial_number' => 'BAT-ST-0177', 'charger_model' => 'Still LG/6 48V',
        ]);

        return $assets;
    }

    private function seedContractWithFranchise(Tenant $tenant, Asset $toyota, Client $client): Contract
    {
        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'asset_id' => $toyota->id, 'client_id' => $client->id,
            'contract_number' => 'CT-EMP-0001', 'status' => 'Ativo',
            'start_date' => now()->subMonths(3), 'price' => 4800.00,
            'billing_type' => Contract::BILLING_FRANQUIA_EXCEDENTE,
            'payment_method' => 'Boleto',
            'usage_purpose' => 'Movimentação de paletes em centro de distribuição',
            'is_active' => true, 'initial_horimeter' => 0,
        ]);

        RentalHourFranchise::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
            'included_hours_per_period' => 200, 'period_type' => RentalHourFranchise::PERIOD_MENSAL,
            'overage_rate_per_hour' => 38.50, 'effective_from' => $contract->start_date,
        ]);

        return $contract;
    }

    /** @param array<string, Asset> $assets */
    private function seedHorimeterHistory(Tenant $tenant, array $assets): void
    {
        // Toyota: em operação há 90 dias, ~8h/dia -- alimenta média de uso
        // e MTBF com números realistas de CD que roda 2 turnos.
        $readings = [
            ['days_ago' => 90, 'reading' => 0],
            ['days_ago' => 60, 'reading' => 230],
            ['days_ago' => 30, 'reading' => 470],
            ['days_ago' => 0, 'reading' => 720],
        ];
        foreach ($readings as $r) {
            HorimeterReading::create([
                'tenant_id' => $tenant->id, 'asset_id' => $assets['toyota']->id,
                'reading' => $r['reading'], 'recorded_at' => now()->subDays($r['days_ago']),
                'source' => HorimeterReading::SOURCE_MANUAL, 'recorded_by_name' => 'Operador CD',
            ]);
        }

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assets['hyster']->id,
            'reading' => 1450, 'recorded_at' => now()->subDays(5),
            'source' => HorimeterReading::SOURCE_MANUAL,
        ]);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assets['still']->id,
            'reading' => 3120, 'recorded_at' => now()->subDay(),
            'source' => HorimeterReading::SOURCE_MAINTENANCE_ORDER,
        ]);
    }

    /** @param array<string, Asset> $assets */
    private function seedMaintenanceHistory(Tenant $tenant, array $assets, User $technician): void
    {
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assets['toyota']->id, 'technician_id' => $technician->id,
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'description' => 'Revisão programada 500h — troca de óleo, filtros e inspeção de garfos',
            'status' => 'Concluída', 'horimetro_entry' => 470,
            'labor_cost' => 220, 'material_cost' => 180,
        ]);

        // Corretiva com peça e numero de serie (rastreabilidade) -- ver
        // MaintenanceOrderMaterial::booted() (bloqueia sem serial quando o
        // material exige).
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assets['still']->id, 'technician_id' => $technician->id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'description' => 'Falha na bateria — substituição de célula',
            'status' => 'Em Andamento', 'horimetro_entry' => 3120,
            'labor_cost' => 150,
        ]);
    }

    /** @param array<string, Asset> $assets */
    private function seedDowntimeHistory(Tenant $tenant, array $assets): void
    {
        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assets['toyota']->id,
            'started_at' => now()->subDays(45), 'ended_at' => now()->subDays(44)->addHours(6),
            'reason' => AssetDowntimeEvent::REASON_MANUTENCAO_PREVENTIVA,
            'notes' => 'Revisão 250h programada.',
        ]);

        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assets['still']->id,
            'started_at' => now()->subDay(), 'ended_at' => null,
            'reason' => AssetDowntimeEvent::REASON_QUEBRA,
            'notes' => 'Falha de bateria, aguardando peça de substituição.',
        ]);
    }
}
