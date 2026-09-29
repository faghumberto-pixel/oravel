<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\CreateAsset;
use App\Models\Asset;
use App\Models\InternalUnit;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido do usuário 29/09/2026, antes de uma demo pra cliente: fabricante
 * (opcional), valor/data de aquisição não obrigatórios, numeração de
 * patrimônio automática opt-in por tenant (Tenant::auto_generate_patrimonio),
 * e "+" pra criar Unidade direto do form do Ativo.
 */
class AssetResourceCreateFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(bool $autoGeneratePatrimonio = false): array
    {
        $plan = Plan::create([
            'name' => 'Plano Ativo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_internal_units'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Ativo '.uniqid(), 'slug' => 'tenant-ativo-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
            'auto_generate_patrimonio' => $autoGeneratePatrimonio,
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
            'email_verified_at' => now(), 'is_approved' => true,
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_can_create_asset_without_fabricante_acquisition_value_or_date(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'patrimonio' => 'PAT-0001',
                'name' => 'Gerador Teste',
                'checklist' => [], 'contracts' => [], 'maintenanceOrders' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $asset = Asset::where('tenant_id', $tenant->id)->where('patrimonio', 'PAT-0001')->firstOrFail();
        $this->assertNull($asset->fabricante);
        $this->assertNull($asset->acquisition_value);
        $this->assertNull($asset->acquisition_date);
    }

    public function test_fabricante_is_saved_when_provided(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CreateAsset::class)
            ->fillForm([
                'patrimonio' => 'PAT-0002',
                'name' => 'Gerador Teste 2',
                'fabricante' => 'Cummins',
                'checklist' => [], 'contracts' => [], 'maintenanceOrders' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $asset = Asset::where('tenant_id', $tenant->id)->where('patrimonio', 'PAT-0002')->firstOrFail();
        $this->assertSame('Cummins', $asset->fabricante);
    }

    public function test_patrimonio_stays_blank_by_default_when_left_empty_and_auto_generate_is_off(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(autoGeneratePatrimonio: false);
        $this->actingAs($admin);

        // Sem auto-geração ligada, patrimônio continua obrigatório de digitar --
        // deixar em branco deve dar erro de validação, não preencher sozinho.
        Livewire::test(CreateAsset::class)
            ->fillForm([
                'patrimonio' => '',
                'name' => 'Gerador Sem Patrimonio',
            ])
            ->call('create')
            ->assertHasFormErrors(['patrimonio']);
    }

    public function test_patrimonio_auto_fills_next_sequence_when_tenant_has_auto_generate_enabled(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin(autoGeneratePatrimonio: true);
        $this->actingAs($admin);

        Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Existente', 'patrimonio' => '000005',
            'status' => Asset::STATUS_DISPONIVEL,
        ]);

        $next = Asset::nextPatrimonio($tenant->id);

        $this->assertSame('000006', $next);
    }

    public function test_internal_unit_select_still_saves_correctly_after_adding_quick_create(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $component = Livewire::test(CreateAsset::class);

        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Filial Teste', 'code' => 'F1']);

        $component->fillForm([
            'patrimonio' => 'PAT-0003',
            'name' => 'Gerador Com Unidade',
            'internal_unit_id' => $unit->id,
            'checklist' => [], 'contracts' => [], 'maintenanceOrders' => [],
        ])->call('create')->assertHasNoFormErrors();

        $asset = Asset::where('tenant_id', $tenant->id)->where('patrimonio', 'PAT-0003')->firstOrFail();
        $this->assertSame($unit->id, $asset->internal_unit_id);
    }
}
