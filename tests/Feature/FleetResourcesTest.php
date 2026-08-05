<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\ForkliftSpecification;
use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\RentalHourFranchiseResource\Pages\ManageRentalHourFranchises;
use App\Filament\Resources\RentalOverageChargeResource\Pages\ManageRentalOverageCharges;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Resources Filament do módulo vertical de Frotas/Empilhadeiras
 * (app/Domain/Fleet, 2026-08-05): RentalHourFranchiseResource,
 * RentalOverageChargeResource, e a aba condicional "Empilhadeira" embutida
 * no AssetResource (só aparece quando asset_category = 'Empilhadeira').
 */
class FleetResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Fleet Resources '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [
                'tabela_assets', 'tabela_contracts', 'tabela_asset_categories',
                'tabela_rental_hour_franchises', 'tabela_rental_overage_charges',
            ],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Fleet Resources '.uniqid(), 'slug' => 'tenant-fleet-res-'.uniqid(),
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

    private function makeContract(Tenant $tenant): Contract
    {
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira', 'tag' => 'AST-'.uniqid(), 'status' => 'locado']);
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Fleet']);

        return Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now()->subMonth(),
            'status' => 'Ativo', 'price' => 5000,
        ]);
    }

    public function test_creating_a_rental_hour_franchise_via_the_resource(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $contract = $this->makeContract($tenant);
        $this->actingAs($admin);

        Livewire::test(ManageRentalHourFranchises::class)
            ->callAction('create', data: [
                'contract_id' => $contract->id,
                'period_type' => RentalHourFranchise::PERIOD_MENSAL,
                'included_hours_per_period' => 200,
                'overage_rate_per_hour' => 45.50,
                'effective_from' => now()->toDateString(),
            ])
            ->assertHasNoActionErrors();

        $franchise = RentalHourFranchise::sole();
        $this->assertSame($contract->id, $franchise->contract_id);
    }

    public function test_creating_a_rental_overage_charge_via_the_resource(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $contract = $this->makeContract($tenant);
        $asset = $contract->asset;
        $this->actingAs($admin);

        Livewire::test(ManageRentalOverageCharges::class)
            ->callAction('create', data: [
                'contract_id' => $contract->id,
                'asset_id' => $asset->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'hours_used' => 230,
                'hours_included' => 200,
                'hours_overage' => 30,
                'amount' => 1365,
                'status' => RentalOverageCharge::STATUS_PENDING,
            ])
            ->assertHasNoActionErrors();

        $charge = RentalOverageCharge::sole();
        $this->assertSame($contract->id, $charge->contract_id);
        $this->assertSame(RentalOverageCharge::STATUS_PENDING, $charge->status);
    }

    public function test_fleet_resource_records_do_not_leak_across_tenants(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $contractA = $this->makeContract($tenantA);
        $this->actingAs($adminA);
        RentalHourFranchise::create([
            'tenant_id' => $tenantA->id, 'contract_id' => $contractA->id,
            'included_hours_per_period' => 200, 'period_type' => RentalHourFranchise::PERIOD_MENSAL,
            'overage_rate_per_hour' => 45.50, 'effective_from' => now(),
        ]);

        [$tenantB, $adminB] = $this->makeTenantAdmin();
        $this->actingAs($adminB);

        Livewire::test(ManageRentalHourFranchises::class)
            ->assertCountTableRecords(0);
    }

    public function test_asset_edit_page_shows_forklift_tab_only_for_forklift_category(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $forkliftCategory = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira']);
        $forklift = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira Teste', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL, 'asset_category_id' => $forkliftCategory->id,
            'asset_category' => 'Empilhadeira',
        ]);

        $generatorCategory = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Gerador']);
        $generator = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL, 'asset_category_id' => $generatorCategory->id,
            'asset_category' => 'Gerador',
        ]);

        Livewire::test(EditAsset::class, ['record' => $forklift->id])
            ->assertSee('Empilhadeira')
            ->assertFormFieldExists('forkliftSpecification.load_capacity_kg');

        Livewire::test(EditAsset::class, ['record' => $generator->id])
            ->assertFormFieldDoesNotExist('forkliftSpecification.load_capacity_kg');
    }

    public function test_saving_forklift_specs_creates_the_related_record(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira Teste', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL, 'asset_category_id' => $category->id,
            'asset_category' => 'Empilhadeira',
        ]);

        Livewire::test(EditAsset::class, ['record' => $asset->id])
            ->fillForm([
                'patrimonio' => 'PAT-'.uniqid(),
                'acquisition_value' => 1000,
                'acquisition_date' => now()->subYear()->toDateString(),
                'forkliftSpecification.load_capacity_kg' => 2500,
                'forkliftSpecification.lift_height_m' => 4.80,
                'forkliftSpecification.energy_type' => ForkliftSpecification::ENERGY_ELETRICA,
                'forkliftSpecification.mast_type' => ForkliftSpecification::MAST_TRIPLA,
                'forkliftSpecification.tire_type' => ForkliftSpecification::TIRE_POLIURETANO,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $spec = ForkliftSpecification::where('asset_id', $asset->id)->sole();
        $this->assertSame('2500.00', $spec->load_capacity_kg);
        $this->assertSame(ForkliftSpecification::ENERGY_ELETRICA, $spec->energy_type);
    }
}
