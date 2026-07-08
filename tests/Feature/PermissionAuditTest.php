<?php

namespace Tests\Feature;

use App\Filament\Pages\Chat;
use App\Filament\Resources\CrmLeadResource;
use App\Filament\Resources\RoleResource;
use App\Livewire\GlobalChat;
use App\Models\Asset;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\MaintenanceOrder;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Auditoria pratica (nao so leitura de codigo) do sistema de permissoes em
 * 2 camadas: Central->Plano->Modulos e Admin-Tenant->Roles->Permissoes.
 * Cada teste aqui reproduz um cenario real com dados reais (RefreshDatabase),
 * pedido explicito do usuario -- "nao presuma, teste de verdade".
 */
class PermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(array $features): array
    {
        $plan = Plan::create([
            'name' => 'Plano Auditoria '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => $features,
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Auditoria '.uniqid(), 'slug' => 'tenant-auditoria-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($adminRole);

        return [$tenant, $admin];
    }

    private function makeSuperAdmin(): User
    {
        config(['oravel.super_admins' => ['super-'.uniqid().'@oravel.com.br']]);
        $email = config('oravel.super_admins')[0];

        $super = User::create([
            'name' => 'Super Admin', 'email' => $email,
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now()])->save();

        return $super;
    }

    // ---------------------------------------------------------------
    // 1. Central -> Plano -> Modulos: tenant sem a feature e' bloqueado
    // ---------------------------------------------------------------

    public function test_tenant_admin_is_blocked_from_module_not_in_plan_policy_level(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem tabela_crm_leads
        $this->actingAs($admin); // Tenancy::current() depende do usuario autenticado

        $this->assertFalse($admin->can('viewAny', CrmLead::class), 'admin do tenant NAO deveria ver CRM (fora do plano)');
        $this->assertFalse($admin->can('create', CrmLead::class));
    }

    public function test_tenant_admin_is_blocked_from_module_not_in_plan_http_level(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem tabela_crm_leads

        $this->actingAs($admin);

        $response = $this->get(CrmLeadResource::getUrl('index', ['tenant' => $tenant->slug]));

        $response->assertForbidden();
    }

    public function test_tenant_admin_with_plan_feature_can_access_module(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets', 'tabela_crm_leads']);
        $this->actingAs($admin);

        $this->assertTrue($admin->can('viewAny', CrmLead::class));
    }

    // ---------------------------------------------------------------
    // 2. Admin do Tenant so ve no RoleResource o que o Plano libera
    // ---------------------------------------------------------------

    public function test_role_resource_permission_tabs_do_not_show_modules_outside_plan(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets', 'tabela_roles']); // sem CRM

        $this->actingAs($admin);

        $vendedorRole = Role::create(['name' => 'vendedor', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $response = $this->get(RoleResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $vendedorRole->id]));

        $response->assertOk();
        $response->assertDontSee('CRM - Leads', false);
    }

    public function test_role_resource_permission_tabs_show_modules_inside_plan(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets', 'tabela_roles', 'tabela_crm_leads']);

        $this->actingAs($admin);

        $vendedorRole = Role::create(['name' => 'vendedor', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $response = $this->get(RoleResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $vendedorRole->id]));

        $response->assertOk();
        $response->assertSee('CRM - Leads', false);
    }

    // ---------------------------------------------------------------
    // 3. Achado da analise estatica: RolePolicy/MaintenanceOrderPolicy/
    //    MaterialPolicy checam hasFeature() ANTES do proprio bypass de
    //    admin (ordem diferente de AbstractPolicy, que checa super-admin
    //    primeiro). Na pratica isso NAO vaza pra super admin porque
    //    Tenant::hasFeature() (app/Models/Tenant.php:50-52) tem o SEU
    //    PROPRIO bypass: "if (Auth::user()?->isSuperAdmin()) return true"
    //    -- ou seja, ha um bypass redundante direto no model, nao so na
    //    policy. Confirmado abaixo com dado real. O que IMPORTA de verdade
    //    pra seguranca -- um ADMIN DE TENANT (nao super) sendo bloqueado
    //    quando o PROPRIO plano do tenant nao tem a feature -- tambem e'
    //    testado abaixo e funciona corretamente nas 3 policies.
    // ---------------------------------------------------------------

    public function test_super_admin_is_not_blocked_by_hand_rolled_policies_due_to_redundant_bypass_in_tenant_model(): void
    {
        [$tenant, $admin] = $this->makeTenant([]); // plano sem NENHUMA feature
        $super = $this->makeSuperAdmin();

        session(['acting_tenant_id' => $tenant->id]);
        $this->actingAs($super);

        // Nao e' o AbstractPolicy que salva aqui -- e' Tenant::hasFeature()
        // que devolve true pra qualquer feature quando o usuario autenticado
        // e' super admin, entao mesmo as 3 policies hand-rolled (que checam
        // hasFeature() antes do bypass de admin) acabam passando.
        $this->assertTrue($tenant->hasFeature('tabela_roles'), 'Tenant::hasFeature() tem bypass proprio de super admin, mascarando a ordem de checagem da policy');
        $this->assertTrue($super->can('viewAny', Role::class));
        $this->assertTrue($super->can('viewAny', MaintenanceOrder::class));
        $this->assertTrue($super->can('viewAny', Material::class));
    }

    public function test_tenant_admin_non_super_is_correctly_blocked_from_role_module_outside_plan(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem tabela_roles
        $this->actingAs($admin);

        $this->assertFalse($admin->can('viewAny', Role::class), 'admin do tenant (nao super) deve ser bloqueado de Perfis de Acesso sem tabela_roles no plano');
    }

    public function test_tenant_admin_non_super_is_correctly_blocked_from_maintenance_order_outside_plan(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem tabela_maintenance_orders
        $this->actingAs($admin);

        $this->assertFalse($admin->can('viewAny', MaintenanceOrder::class), 'admin do tenant (nao super) deve ser bloqueado de OS sem tabela_maintenance_orders no plano');
    }

    public function test_tenant_admin_non_super_is_correctly_blocked_from_material_outside_plan(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem tabela_materials
        $this->actingAs($admin);

        $this->assertFalse($admin->can('viewAny', Material::class), 'admin do tenant (nao super) deve ser bloqueado de Materiais sem tabela_materials no plano');
    }

    /**
     * BUG REAL E DISTINTO (nao mascarado pelo bypass do Tenant::hasFeature()):
     * RolePolicy::before() checa super-admin por
     * str_ends_with($user->email, '@oravel.com.br') || $user->hasRole('admin')
     * -- NAO por $user->isSuperAdmin(). config('oravel.super_admins') e' uma
     * lista de e-mails arbitraria (SUPER_ADMINS no .env), nao restrita a
     *
     * @oravel.com.br. Um super admin com e-mail de outro dominio e sem role
     * 'admin' em tenant nenhum fica bloqueado de Perfis de Acesso.
     */
    public function test_super_admin_with_non_oravel_email_is_incorrectly_blocked_from_role_module(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_roles']);

        config(['oravel.super_admins' => ['dono@outraempresa.com.br']]);
        $super = User::create([
            'name' => 'Super Externo', 'email' => 'dono@outraempresa.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now()])->save();

        session(['acting_tenant_id' => $tenant->id]);
        $this->actingAs($super);

        $this->assertTrue($super->isSuperAdmin(), 'sanity check: isSuperAdmin() reconhece o e-mail configurado');
        $this->assertFalse($super->can('viewAny', Role::class), 'BUG CONFIRMADO: RolePolicy nega super admin com e-mail fora de @oravel.com.br e sem role admin');
    }

    public function test_super_admin_is_correctly_allowed_asset_access_regardless_of_acting_tenant_plan(): void
    {
        [$tenant, $admin] = $this->makeTenant([]); // sem NENHUMA feature, nem tabela_assets
        $super = $this->makeSuperAdmin();

        session(['acting_tenant_id' => $tenant->id]);
        $this->actingAs($super);

        $this->assertTrue($super->can('viewAny', Asset::class), 'AssetPolicy (AbstractPolicy) deixa super admin passar sempre, como esperado');
    }

    // ---------------------------------------------------------------
    // 4. Chat ignora a trava comercial (Chat::canAccess() hardcoded true)
    // ---------------------------------------------------------------

    public function test_chat_page_is_accessible_even_when_plan_lacks_chat_module(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem modulo_chat
        $this->actingAs($admin);

        $this->assertFalse($tenant->hasFeature('modulo_chat'), 'sanity check: plano realmente nao tem modulo_chat');

        // Documenta o bug: Chat::canAccess() ignora a feature do plano.
        $this->assertTrue(Chat::canAccess(), 'BUG CONFIRMADO: Chat::canAccess() retorna true mesmo sem modulo_chat no plano');
    }

    public function test_global_chat_livewire_has_no_authorization_check_at_all(): void
    {
        [$tenant, $admin] = $this->makeTenant([]); // sem NENHUMA feature
        $this->actingAs($admin);

        // GlobalChat::mount() nao chama Gate::authorize nem ->can() em lugar
        // nenhum -- o componente monta e responde 200 independente do plano.
        Livewire::test(GlobalChat::class)->assertOk();
    }

    // ---------------------------------------------------------------
    // 5. CrmLeadInteraction sem HasSaaSMetadata -- nao-admin nunca
    //    consegue permissao (nao ha aba no RoleResource pra conceder).
    // ---------------------------------------------------------------

    public function test_non_admin_role_can_never_be_granted_crm_interaction_permission_via_ui(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets', 'tabela_roles', 'tabela_crm_leads']);
        $this->actingAs($admin);

        $vendedorRole = Role::create(['name' => 'vendedor', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        $response = $this->get(RoleResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $vendedorRole->id]));
        $response->assertOk();

        // Nao existe aba/checkbox pra CrmLeadInteraction -- confirma que a
        // permissao 'ler_crm_lead_interaction' nunca aparece na tela.
        $response->assertDontSee('ler_crm_lead_interaction', false);
    }

    public function test_non_admin_user_cannot_manage_crm_interactions_even_with_crm_feature_enabled(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets', 'tabela_crm_leads']);

        $vendedor = User::create([
            'name' => 'Vendedor', 'email' => 'vendedor-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $vendedor->forceFill(['email_verified_at' => now()])->save();
        $vendedorRole = Role::create(['name' => 'vendedor', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $vendedor->assignRole($vendedorRole);
        $this->actingAs($vendedor);

        // Mesmo tentando conceder manualmente via Spatie direto (bypassando a
        // UI, que nem mostra a opcao), a permissao nem existe pra conceder
        // com o slug que o CrmLeadInteractionPolicy espera.
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead Teste', 'stage' => CrmLead::STAGE_NOVO]);

        $this->assertFalse($vendedor->can('create', CrmLeadInteraction::class), 'vendedor nao-admin nao consegue registrar interacao mesmo com CRM no plano');
    }

    // ---------------------------------------------------------------
    // 0. [FIX] Painel Central (operador SaaS) restrito a super admin.
    //    Achado mais grave da auditoria: User::canAccessPanel() retornava
    //    true sempre -- qualquer admin de QUALQUER tenant acessava
    //    /central e via planos/tenants/receita de todos os outros clientes.
    // ---------------------------------------------------------------

    public function test_tenant_admin_cannot_access_central_panel(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets', 'tabela_roles']);
        $this->actingAs($admin);

        $this->get('/central')->assertForbidden();
        $this->get('/central/plans')->assertForbidden();
    }

    public function test_random_authenticated_user_cannot_access_central_panel(): void
    {
        [$tenant, $admin] = $this->makeTenant([]);
        $random = User::create([
            'name' => 'Sem Role', 'email' => 'semrole-'.uniqid().'@empresa.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $random->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($random);

        $this->get('/central')->assertForbidden();
    }

    public function test_super_admin_can_still_access_central_panel(): void
    {
        $super = $this->makeSuperAdmin();
        $this->actingAs($super);

        $this->get('/central')->assertOk();
        $this->get('/central/plans')->assertOk();
    }

    public function test_tenant_admin_can_still_access_own_admin_panel(): void
    {
        // Garante que a correcao nao quebrou o acesso normal ao painel do
        // proprio tenant (admin panel), so o central deveria ser afetado.
        [$tenant, $admin] = $this->makeTenant(['tabela_assets', 'tabela_roles']);
        $this->actingAs($admin);

        $this->get(RoleResource::getUrl('index', ['tenant' => $tenant->slug]))->assertOk();
    }
}
