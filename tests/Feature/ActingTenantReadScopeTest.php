<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Models\Department;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Super admin que escolhe um tenant atuante (seletor do topo / "Atuar como
 * Tenant") passa a ver, no painel de cliente, so os dados daquele tenant.
 * Antes ele via os de todos misturados (ex.: departamentos repetidos).
 * Pedido do usuario 05/10/2026.
 */
class ActingTenantReadScopeTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $a;

    private Tenant $b;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create([
            'name' => 'Plano Acting '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_departments'],
        ]);
        $this->a = Tenant::create(['name' => 'Tenant A '.uniqid(), 'slug' => 'ta-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $this->b = Tenant::create(['name' => 'Tenant B '.uniqid(), 'slug' => 'tb-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);

        Department::create(['name' => 'Depto-Alfa-'.uniqid(), 'tenant_id' => $this->a->id]);
        Department::create(['name' => 'Depto-Beta-'.uniqid(), 'tenant_id' => $this->b->id]);

        $email = 'super-'.uniqid().'@oravel.test';
        config(['oravel.super_admins' => [$email]]);
        $this->superAdmin = User::create(['name' => 'Super', 'email' => $email, 'password' => bcrypt('x')]);
        $this->actingAs($this->superAdmin);
    }

    private function visibleTenantIds(): array
    {
        return Department::query()->whereIn('tenant_id', [$this->a->id, $this->b->id])->pluck('tenant_id')->unique()->sort()->values()->all();
    }

    public function test_without_acting_tenant_super_admin_still_sees_every_tenant(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $this->visibleTenantIds());
    }

    public function test_with_acting_tenant_the_admin_panel_shows_only_that_tenant(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        session(['acting_tenant_id' => $this->a->id]);

        $this->assertSame([$this->a->id], $this->visibleTenantIds());

        session(['acting_tenant_id' => $this->b->id]);
        $this->assertSame([$this->b->id], $this->visibleTenantIds());
    }

    public function test_central_panel_and_console_keep_seeing_every_tenant_even_with_acting_tenant(): void
    {
        session(['acting_tenant_id' => $this->a->id]);

        Filament::setCurrentPanel(Filament::getPanel('central'));
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $this->visibleTenantIds());
    }

    public function test_regular_tenant_user_is_unaffected_by_a_stale_acting_tenant_in_session(): void
    {
        $user = User::create(['name' => 'Gerente', 'email' => 'g-'.uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $this->b->id]);
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        session(['acting_tenant_id' => $this->a->id]);

        $this->assertSame([$this->b->id], $this->visibleTenantIds());
    }

    public function test_users_list_follows_the_acting_tenant_too(): void
    {
        $uA = User::create(['name' => 'Func A', 'email' => 'fa-'.uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $this->a->id]);
        $uB = User::create(['name' => 'Func B', 'email' => 'fb-'.uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $this->b->id]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        session(['acting_tenant_id' => $this->a->id]);

        $ids = UserResource::getEloquentQuery()->pluck('users.id')->all();

        $this->assertContains($uA->id, $ids);
        $this->assertNotContains($uB->id, $ids);
    }
}
