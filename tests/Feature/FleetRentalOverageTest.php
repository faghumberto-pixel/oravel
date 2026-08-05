<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\ForkliftSpecification;
use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Models\AccountPayable;
use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\Client;
use App\Models\Contract;
use App\Models\HorimeterReading;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fundação de dados do módulo vertical de Frotas/Empilhadeiras
 * (app/Domain/Fleet) -- gap real identificado 2026-08-05: Contract não
 * modelava franquia de horas nem cobrança por excedente.
 */
class FleetRentalOverageTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Fleet '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_contracts', 'tabela_rental_hour_franchises', 'tabela_rental_overage_charges'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Fleet '.uniqid(), 'slug' => 'tenant-fleet-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeAsset(Tenant $tenant): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira Teste', 'tag' => 'AST-'.uniqid(),
            'status' => 'locado',
        ]);
    }

    private function makeContract(Tenant $tenant, Asset $asset): Contract
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Fleet']);

        return Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-FLEET-'.uniqid(), 'start_date' => now()->subMonth(),
            'status' => 'Ativo', 'price' => 5000,
        ]);
    }

    public function test_forklift_specification_belongs_to_asset_and_tenant(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $spec = ForkliftSpecification::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'load_capacity_kg' => 2500, 'lift_height_m' => 4.80,
            'energy_type' => ForkliftSpecification::ENERGY_ELETRICA,
            'mast_type' => ForkliftSpecification::MAST_TRIPLA,
            'tire_type' => ForkliftSpecification::TIRE_POLIURETANO,
            'battery_voltage' => '80V', 'battery_amperage_ah' => 700,
            'battery_serial_number' => 'BAT-0099', 'charger_model' => 'CH-3000',
        ]);

        $this->assertSame($asset->id, $spec->asset->id);
        $this->assertSame('2500.00', $spec->load_capacity_kg);
        $this->assertSame('4.80', $spec->lift_height_m);
        $this->assertTrue($spec->isElectric());
        $this->assertSame('700.00', $spec->battery_amperage_ah);
    }

    public function test_forklift_specification_is_unique_per_asset(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        ForkliftSpecification::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id]);

        $this->expectException(QueryException::class);
        ForkliftSpecification::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id]);
    }

    public function test_rental_hour_franchise_belongs_to_contract(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $contract = $this->makeContract($tenant, $asset);
        $this->actingAs($admin);

        $franchise = RentalHourFranchise::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
            'included_hours_per_period' => 200, 'period_type' => RentalHourFranchise::PERIOD_MENSAL,
            'overage_rate_per_hour' => 45.50, 'effective_from' => now(),
        ]);

        $this->assertSame($contract->id, $franchise->contract->id);
        $this->assertSame('45.50', $franchise->overage_rate_per_hour);
    }

    public function test_rental_overage_charge_belongs_to_contract_and_asset(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $contract = $this->makeContract($tenant, $asset);
        $this->actingAs($admin);

        $charge = RentalOverageCharge::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'asset_id' => $asset->id,
            'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(),
            'hours_used' => 230, 'hours_included' => 200, 'hours_overage' => 30,
            'amount' => 1365,
        ]);

        $this->assertSame($contract->id, $charge->contract->id);
        $this->assertSame($asset->id, $charge->asset->id);
        $this->assertSame(RentalOverageCharge::STATUS_PENDING, $charge->fresh()->status);
        $this->assertNull($charge->account_receivable_id);
    }

    public function test_rental_overage_charge_is_unique_per_contract_asset_and_period(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $contract = $this->makeContract($tenant, $asset);
        $this->actingAs($admin);

        $attrs = [
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'asset_id' => $asset->id,
            'period_start' => now()->startOfMonth(), 'period_end' => now()->endOfMonth(),
            'hours_used' => 230, 'hours_included' => 200, 'hours_overage' => 30, 'amount' => 1365,
        ];
        RentalOverageCharge::create($attrs);

        $this->expectException(QueryException::class);
        RentalOverageCharge::create($attrs);
    }

    public function test_fleet_records_do_not_leak_across_tenants(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = $this->makeAsset($tenantA);
        $contractA = $this->makeContract($tenantA, $assetA);
        $this->actingAs($adminA);

        RentalHourFranchise::create([
            'tenant_id' => $tenantA->id, 'contract_id' => $contractA->id,
            'included_hours_per_period' => 200, 'period_type' => RentalHourFranchise::PERIOD_MENSAL,
            'overage_rate_per_hour' => 45.50, 'effective_from' => now(),
        ]);
        ForkliftSpecification::create(['tenant_id' => $tenantA->id, 'asset_id' => $assetA->id]);

        [$tenantB, $adminB] = $this->makeTenantAdmin();
        $this->actingAs($adminB);

        $this->assertSame(0, RentalHourFranchise::count());
        $this->assertSame(0, ForkliftSpecification::count());
    }

    public function test_contract_billing_type_controls_whether_it_uses_hour_franchise(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $mensal = $this->makeContract($tenant, $asset);
        $mensal->update(['billing_type' => Contract::BILLING_MENSAL_FIXO]);
        $this->assertFalse($mensal->usesHourFranchise());

        $franquia = $this->makeContract($tenant, $asset);
        $franquia->update(['billing_type' => Contract::BILLING_FRANQUIA_EXCEDENTE]);
        $this->assertTrue($franquia->fresh()->usesHourFranchise());
    }

    public function test_average_hourly_usage_is_computed_from_horimeter_readings(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 100, 'recorded_at' => now()->subDays(10),
            'source' => HorimeterReading::SOURCE_MANUAL,
        ]);
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 200, 'recorded_at' => now(),
            'source' => HorimeterReading::SOURCE_MANUAL,
        ]);

        $usage = $asset->getAverageHourlyUsage();

        $this->assertSame(10.0, $usage['daily_average']);
        $this->assertSame(300.0, $usage['monthly_average']);
    }

    public function test_average_hourly_usage_is_zero_with_a_single_reading(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 100, 'recorded_at' => now(),
            'source' => HorimeterReading::SOURCE_MANUAL,
        ]);

        $usage = $asset->getAverageHourlyUsage();

        $this->assertSame(0.0, $usage['daily_average']);
        $this->assertSame(0.0, $usage['monthly_average']);
    }

    public function test_mtbf_is_null_without_any_closed_failure(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $this->assertNull($asset->getMtbfHours());
    }

    public function test_mtbf_divides_operating_hours_by_failure_count(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 0, 'recorded_at' => now()->subDays(20),
            'source' => HorimeterReading::SOURCE_MANUAL,
        ]);
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 400, 'recorded_at' => now(),
            'source' => HorimeterReading::SOURCE_MANUAL,
        ]);

        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now()->subDays(10), 'ended_at' => now()->subDays(9),
            'reason' => AssetDowntimeEvent::REASON_QUEBRA,
        ]);
        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now()->subDays(5), 'ended_at' => now()->subDays(4),
            'reason' => AssetDowntimeEvent::REASON_MANUTENCAO_CORRETIVA,
        ]);
        // Parada sem quebra não conta como falha pro MTBF.
        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now()->subDays(2), 'ended_at' => now()->subDay(),
            'reason' => AssetDowntimeEvent::REASON_OCIOSO_SEM_USO,
        ]);

        $this->assertSame(200.0, $asset->getMtbfHours());
    }

    public function test_total_cost_of_ownership_subtracts_account_payables_from_rental_revenue(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->makeContract($tenant, $asset);
        $this->actingAs($admin);

        AccountPayable::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'Revisão terceirizada', 'amount' => 800,
            'due_date' => now(), 'status' => 'pending',
        ]);

        $tco = $asset->getTotalCostOfOwnership();

        $this->assertSame(5000.0, $tco['total_rental_revenue']);
        $this->assertSame(800.0, $tco['total_accounts_payable']);
        $this->assertSame(4200.0, $tco['result']);
    }
}
