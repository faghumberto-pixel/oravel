<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Filament\Resources\ClientResource\Pages\ListClients;
use App\Filament\Resources\ContractResource\Pages\ListContracts;
use App\Filament\Resources\DepartmentResource\Pages\ListDepartments;
use App\Filament\Resources\MaintenanceOrderResource\Pages\ListMaintenanceOrders;
use App\Filament\Resources\MaintenancePlanResource\Pages\ListMaintenancePlans;
use App\Filament\Resources\MaterialResource\Pages\ListMaterials;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Department;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Botao Imprimir (HasPrintAction, ja existente pra 6 dos 8 recursos) +
 * Exportar Excel (Filament\Actions\ExportAction, novo -- primeira vez usado
 * nesta base) nas telas de listagem de: Ativo, OS, Clientes, Materiais,
 * Departamentos ("Equipes"), Perfis de Acesso, Contratos, Planos Preventivos.
 */
class PrintExportActionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * config('queue.batching.database') le DB_CONNECTION cru do env (nao
     * passa pelo hardcode 'pgsql' de config/database.php) -- em produção
     * bate igual (DB_CONNECTION=pgsql no .env), mas no ambiente de teste
     * phpunit.xml forca DB_CONNECTION=sqlite, then apontando o batching do
     * Bus::batch() (usado pelo ExportAction) pra uma conexao sqlite
     * "in memory" separada, nunca migrada (sem job_batches). Alinha as duas
     * pra mesma conexao real usada pelos dados, igual acontece em produção.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.batching.database' => config('database.default')]);
    }

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Print Export '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [
                'tabela_assets', 'tabela_maintenance_orders', 'tabela_clients', 'tabela_materials',
                'tabela_departments', 'tabela_roles', 'tabela_contracts', 'tabela_maintenance_plans',
            ],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Print Export '.uniqid(), 'slug' => 'tenant-print-export-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    /**
     * @return array<int, array{0: class-string}>
     */
    public static function listPagesProvider(): array
    {
        return [
            'Ativo' => [ListAssets::class],
            'Ordem de Serviço' => [ListMaintenanceOrders::class],
            'Clientes' => [ListClients::class],
            'Materiais' => [ListMaterials::class],
            'Equipes (Departamentos)' => [ListDepartments::class],
            'Perfis de Acesso' => [ListRoles::class],
            'Contratos' => [ListContracts::class],
            'Planos Preventivos' => [ListMaintenancePlans::class],
        ];
    }

    #[DataProvider('listPagesProvider')]
    public function test_list_page_shows_both_imprimir_and_exportar_buttons(string $listPageClass): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test($listPageClass)
            ->assertActionExists('imprimir')
            ->assertActionExists('export')
            ->assertSuccessful();
    }

    public function test_exporting_departments_produces_a_completed_export_scoped_to_the_tenant(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        Department::create(['tenant_id' => $tenantA->id, 'name' => 'Manutenção', 'code' => 'MNT']);
        Department::create(['tenant_id' => $tenantA->id, 'name' => 'Logística', 'code' => 'LOG']);

        [$tenantB, $adminB] = $this->makeTenantAdmin();
        Department::create(['tenant_id' => $tenantB->id, 'name' => 'Departamento de Outro Tenant', 'code' => 'OUT']);

        $this->actingAs($adminA);

        Livewire::test(ListDepartments::class)
            ->callAction('export')
            ->assertHasNoActionErrors();

        $export = Export::latest('id')->first();

        $this->assertNotNull($export);
        $this->assertSame(2, $export->total_rows);
        $this->assertSame(2, $export->successful_rows);
    }

    public function test_exporting_roles_produces_a_completed_export(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        Livewire::test(ListRoles::class)
            ->callAction('export')
            ->assertHasNoActionErrors();

        $export = Export::latest('id')->first();

        $this->assertNotNull($export);
        $this->assertGreaterThanOrEqual(2, $export->total_rows);
        $this->assertSame($export->total_rows, $export->successful_rows);
    }

    public function test_print_route_shows_real_columns_for_departments_not_the_generic_fallback(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $department = Department::create(['tenant_id' => $tenant->id, 'name' => 'Manutenção de Campo', 'code' => 'MNT-C']);

        $this->actingAs($admin);

        $token = (string) Str::uuid();
        Cache::put("table-print:{$token}", [
            'model' => Department::class,
            'ids' => [$department->id],
            'filtros' => [],
            'titulo' => 'Departamentos',
        ], now()->addMinutes(15));

        $this->get(route('table-print.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Manutenção de Campo')
            ->assertSee('Departamento')
            ->assertDontSee('Criado em');
    }

    public function test_print_route_shows_real_columns_for_roles_not_the_generic_fallback(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $role = Role::firstOrCreate(['name' => 'supervisor-campo', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $this->actingAs($admin);

        $token = (string) Str::uuid();
        Cache::put("table-print:{$token}", [
            // TablePrintController::columnsFor() casa contra a classe RAW do
            // Spatie (a mesma que RoleResource usa como $model) -- App\Models\Role
            // (import padrão deste arquivo de teste) é uma classe PHP diferente
            // do ponto de vista do match(), mesmo estendendo a mesma tabela.
            'model' => \Spatie\Permission\Models\Role::class,
            'ids' => [$role->id],
            'filtros' => [],
            'titulo' => 'Perfis de Acesso',
        ], now()->addMinutes(15));

        $this->get(route('table-print.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('supervisor-campo')
            ->assertSee('Função')
            ->assertDontSee('Criado em');
    }

    public function test_asset_export_only_includes_current_tenants_assets(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        Asset::create(['tenant_id' => $tenantA->id, 'name' => 'Ativo A', 'tag' => 'A-1', 'status' => 'disponivel']);

        [$tenantB, $adminB] = $this->makeTenantAdmin();
        Asset::create(['tenant_id' => $tenantB->id, 'name' => 'Ativo B', 'tag' => 'B-1', 'status' => 'disponivel']);

        $this->actingAs($adminA);

        Livewire::test(ListAssets::class)
            ->callAction('export')
            ->assertHasNoActionErrors();

        $export = Export::latest('id')->first();

        $this->assertSame(1, $export->total_rows);
    }

    public function test_client_export_produces_a_completed_export(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Export']);

        $this->actingAs($admin);

        Livewire::test(ListClients::class)
            ->callAction('export')
            ->assertHasNoActionErrors();

        $export = Export::latest('id')->first();

        $this->assertSame(1, $export->total_rows);
        $this->assertSame(1, $export->successful_rows);
    }
}
