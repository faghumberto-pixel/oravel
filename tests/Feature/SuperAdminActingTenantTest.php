<?php

namespace Tests\Feature;

use App\Filament\Pages\SelectActingTenant;
use App\Filament\Resources\ChecklistGroupResource;
use App\Livewire\TenantSwitcher;
use App\Models\ChecklistGroup;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminActingTenantTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Plano Teste', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_checklist_groups'],
        ]);
    }

    private function makeTenant(Plan $plan, string $name): Tenant
    {
        return Tenant::create([
            'name' => $name, 'slug' => str($name)->slug().'-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeTenantAdmin(Tenant $tenant): User
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'email_verified_at' => now(), 'is_approved' => true,
        ]);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeSuperAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'humberto@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null, 'email_verified_at' => now(), 'is_approved' => true,
        ]);
    }

    public function test_tenant_admin_create_behavior_is_unaffected(): void
    {
        $tenant = $this->makeTenant($this->makePlan(), 'Tenant Normal');
        $user = $this->makeTenantAdmin($tenant);

        $this->actingAs($user);

        $group = ChecklistGroup::create(['name' => 'Grupo Teste']);
        $this->assertEquals($tenant->id, $group->tenant_id);
    }

    public function test_tenant_admin_read_scope_is_unaffected(): void
    {
        $plan = $this->makePlan();
        $tenantA = $this->makeTenant($plan, 'Tenant A');
        $tenantB = $this->makeTenant($plan, 'Tenant B');
        ChecklistGroup::create(['tenant_id' => $tenantA->id, 'name' => 'Do Tenant A']);
        ChecklistGroup::create(['tenant_id' => $tenantB->id, 'name' => 'Do Tenant B']);

        $this->actingAs($this->makeTenantAdmin($tenantA));

        $this->assertEquals(1, ChecklistGroup::count());
    }

    public function test_super_admin_cannot_create_without_acting_tenant(): void
    {
        $this->makeTenant($this->makePlan(), 'Qualquer');
        $this->actingAs($this->makeSuperAdmin());

        $this->assertNull(Tenancy::current());
        $this->expectException(QueryException::class);
        ChecklistGroup::create(['name' => 'Sem Tenant']);
    }

    public function test_super_admin_can_create_after_selecting_acting_tenant(): void
    {
        $tenant = $this->makeTenant($this->makePlan(), 'Tenant Escolhido');
        $this->actingAs($this->makeSuperAdmin());

        session(['acting_tenant_id' => $tenant->id]);

        $this->assertEquals($tenant->id, Tenancy::current()?->id);

        $group = ChecklistGroup::create(['name' => 'Com Acting Tenant']);
        $this->assertEquals($tenant->id, $group->tenant_id);
    }

    public function test_super_admin_read_scope_never_restricted_by_acting_tenant(): void
    {
        $plan = $this->makePlan();
        $tenantA = $this->makeTenant($plan, 'Tenant A');
        $tenantB = $this->makeTenant($plan, 'Tenant B');
        ChecklistGroup::create(['tenant_id' => $tenantA->id, 'name' => 'Do Tenant A']);
        ChecklistGroup::create(['tenant_id' => $tenantB->id, 'name' => 'Do Tenant B']);

        $this->actingAs($this->makeSuperAdmin());
        session(['acting_tenant_id' => $tenantA->id]);

        $this->assertEquals(2, ChecklistGroup::count());
    }

    public function test_select_acting_tenant_page_accessible_only_to_super_admin(): void
    {
        $tenant = $this->makeTenant($this->makePlan(), 'Tenant X');

        $this->actingAs($this->makeSuperAdmin());
        $this->assertTrue(SelectActingTenant::canAccess());

        $this->actingAs($this->makeTenantAdmin($tenant));
        $this->assertFalse(SelectActingTenant::canAccess());
    }

    public function test_select_acting_tenant_form_persists_choice_to_session(): void
    {
        $tenant = $this->makeTenant($this->makePlan(), 'Tenant Selecionavel');
        $this->actingAs($this->makeSuperAdmin());

        Livewire::test(SelectActingTenant::class)
            ->fillForm(['acting_tenant_id' => $tenant->id])
            ->call('save');

        $this->assertEquals($tenant->id, session('acting_tenant_id'));
    }

    public function test_select_acting_tenant_form_can_clear_choice(): void
    {
        $tenant = $this->makeTenant($this->makePlan(), 'Tenant Para Limpar');
        $this->actingAs($this->makeSuperAdmin());
        session(['acting_tenant_id' => $tenant->id]);

        Livewire::test(SelectActingTenant::class)
            ->fillForm(['acting_tenant_id' => null])
            ->call('save');

        $this->assertNull(session('acting_tenant_id'));
    }

    /**
     * Regressao: o componente TenantSwitcher (seletor rapido no topo do
     * painel) tinha um @if envolvendo todo o template sem uma div raiz por
     * fora -- Livewire exige que todo componente tenha exatamente 1
     * elemento raiz sempre renderizado, entao qualquer usuario para quem a
     * lista de tenants ficava vazia (todo mundo que nao e super admin,
     * pois o componente so lista tenants pra ele) derrubava a pagina
     * inteira com 500. So foi percebido testando com um tenant admin
     * normal -- os testes anteriores desta classe so verificavam texto,
     * nao status HTTP.
     */
    public function test_normal_tenant_admin_can_load_a_real_page(): void
    {
        $tenant = $this->makeTenant($this->makePlan(), 'Tenant Pagina Real');
        $this->actingAs($this->makeTenantAdmin($tenant));

        $response = $this->get(ChecklistGroupResource::getUrl());

        $response->assertOk();
    }

    /**
     * Regressao: updatedActingTenantId() chamava $this->redirect(url()->current())
     * -- dentro de uma acao do Livewire, url()->current() resolve pra URL da
     * propria chamada AJAX (/livewire/update), nao pra tela que o usuario
     * esta vendo. Isso fazia o navegador tentar um GET em /livewire/update
     * (rota so aceita POST), quebrando a troca de tenant pelo seletor
     * rapido. Trocado por $this->js('window.location.reload()'), que nao
     * depende de reconstruir a URL no servidor.
     */
    public function test_tenant_switcher_reloads_via_js_not_server_redirect(): void
    {
        $tenant = $this->makeTenant($this->makePlan(), 'Tenant Switcher');
        $this->actingAs($this->makeSuperAdmin());

        $component = Livewire::test(TenantSwitcher::class)
            ->set('actingTenantId', $tenant->id);

        $this->assertEquals($tenant->id, session('acting_tenant_id'));

        $effects = $component->effects;
        $this->assertArrayNotHasKey('redirect', $effects);
        $this->assertArrayHasKey('xjs', $effects);
        $this->assertSame('window.location.reload()', $effects['xjs'][0]['expression']);
    }
}
