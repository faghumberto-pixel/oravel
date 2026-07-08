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
use App\Models\UserActivityLog;
use App\Support\SaaSRegistry;
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
    // 3. [FIX] RolePolicy/MaintenanceOrderPolicy/MaterialPolicy agora
    //    checam isSuperAdmin() PRIMEIRO, mesma ordem de
    //    AbstractPolicy::check(). Antes checavam hasFeature() antes do
    //    bypass de admin -- na pratica nunca vazou pra super admin porque
    //    Tenant::hasFeature() (app/Models/Tenant.php:50-52) tem seu
    //    PROPRIO bypass redundante de super admin, mas a ordem agora
    //    tambem esta correta na propria policy, nao so mascarada pelo
    //    model. O que IMPORTA de verdade pra seguranca -- um ADMIN DE
    //    TENANT (nao super) sendo bloqueado quando o PROPRIO plano do
    //    tenant nao tem a feature -- continua testado abaixo.
    // ---------------------------------------------------------------

    public function test_super_admin_passes_hand_rolled_policies_regardless_of_acting_tenant_plan(): void
    {
        [$tenant, $admin] = $this->makeTenant([]); // plano sem NENHUMA feature
        $super = $this->makeSuperAdmin();

        session(['acting_tenant_id' => $tenant->id]);
        $this->actingAs($super);

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
     * FIX aplicado: RolePolicy::before() checava super-admin por
     * str_ends_with($user->email, '@oravel.com.br') || $user->hasRole('admin')
     * -- NAO por $user->isSuperAdmin(). config('oravel.super_admins') e' uma
     * lista de e-mails arbitraria (SUPER_ADMINS no .env), nao restrita a
     *
     * @oravel.com.br, entao um super admin de outro dominio e sem role
     * 'admin' em tenant nenhum ficava bloqueado de Perfis de Acesso.
     * Trocado por $user->isAdmin() (que ja checa isSuperAdmin() primeiro).
     */
    public function test_super_admin_with_non_oravel_email_can_access_role_module(): void
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
        $this->assertTrue($super->can('viewAny', Role::class), 'super admin com e-mail fora de @oravel.com.br e sem role admin deve acessar Perfis de Acesso');
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
    // 4. [FIX] Chat agora respeita a trava comercial do plano
    //    (modulo_chat), tanto no Chat::canAccess() quanto no proprio
    //    GlobalChat::mount() (defesa em profundidade).
    // ---------------------------------------------------------------

    public function test_chat_page_is_blocked_when_plan_lacks_chat_module(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem modulo_chat
        $this->actingAs($admin);

        $this->assertFalse($tenant->hasFeature('modulo_chat'), 'sanity check: plano realmente nao tem modulo_chat');
        $this->assertFalse(Chat::canAccess());
    }

    public function test_chat_page_is_accessible_when_plan_includes_chat_module(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets', 'modulo_chat']);
        $this->actingAs($admin);

        $this->assertTrue(Chat::canAccess());
    }

    public function test_global_chat_livewire_blocks_mount_when_plan_lacks_chat_module(): void
    {
        [$tenant, $admin] = $this->makeTenant([]); // sem NENHUMA feature
        $this->actingAs($admin);

        Livewire::test(GlobalChat::class)->assertStatus(403);
    }

    public function test_global_chat_livewire_mounts_when_plan_includes_chat_module(): void
    {
        [$tenant, $admin] = $this->makeTenant(['modulo_chat']);
        $this->actingAs($admin);

        Livewire::test(GlobalChat::class)->assertOk();
    }

    /**
     * Achado colateral durante o fix do Chat: QUALQUER model com
     * HasSaaSMetadata mas sem Policy nomeada propria cai no mesmo bug
     * (DynamicPolicy nao resolve o model em viewAny/create, trava comercial
     * e' pulada). UserActivityLog tinha o mesmo problema, corrigido junto.
     */
    public function test_user_activity_log_is_blocked_when_plan_lacks_feature(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem tabela_user_activity_logs
        $this->actingAs($admin);

        $this->assertFalse($tenant->hasFeature('tabela_user_activity_logs'));
        $this->assertFalse($admin->can('viewAny', UserActivityLog::class));
    }

    public function test_no_saas_registry_module_is_missing_a_dedicated_policy(): void
    {
        // Varredura completa: todo model com HasSaaSMetadata precisa de uma
        // Policy nomeada propria (App\Policies\{Model}Policy), senao viewAny/
        // create sem $record caem no DynamicPolicy e pulam a trava comercial
        // silenciosamente (ver AbstractPolicy::resolveModelClass()).
        $missing = [];

        foreach (SaaSRegistry::modules() as $module) {
            $model = $module['model'] ?? null;
            if (! $model) {
                continue;
            }

            $policyClass = 'App\\Policies\\'.class_basename($model).'Policy';
            if (! class_exists($policyClass)) {
                $missing[] = $model;
            }
        }

        $this->assertEmpty($missing, 'Modelos do SaaSRegistry sem Policy dedicada: '.implode(', ', $missing));
    }

    // ---------------------------------------------------------------
    // 5. [FIX] CrmLeadInteraction: tratado como auto-servico (igual
    //    Appointment/ChatRoom) -- qualquer membro do tenant registra
    //    interacao se o plano tiver CRM, sem precisar de permissao
    //    granular concedida por role. update/delete ficam restritos ao
    //    autor ou admin.
    // ---------------------------------------------------------------

    public function test_non_admin_user_can_create_crm_interaction_when_plan_has_crm(): void
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

        $this->assertTrue($vendedor->can('create', CrmLeadInteraction::class), 'vendedor nao-admin deve conseguir registrar interacao quando o plano tem CRM');
    }

    public function test_non_admin_user_cannot_create_crm_interaction_when_plan_lacks_crm(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem tabela_crm_leads

        $vendedor = User::create([
            'name' => 'Vendedor', 'email' => 'vendedor-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $vendedor->forceFill(['email_verified_at' => now()])->save();
        $vendedorRole = Role::create(['name' => 'vendedor', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $vendedor->assignRole($vendedorRole);
        $this->actingAs($vendedor);

        $this->assertFalse($vendedor->can('create', CrmLeadInteraction::class), 'trava comercial deve continuar valendo mesmo sendo auto-servico');
    }

    public function test_non_author_non_admin_cannot_edit_or_delete_another_users_crm_interaction(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets', 'tabela_crm_leads']);

        $vendedorA = User::create(['name' => 'Vendedor A', 'email' => 'vendedor-a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id]);
        $vendedorA->forceFill(['email_verified_at' => now()])->save();
        $vendedorB = User::create(['name' => 'Vendedor B', 'email' => 'vendedor-b-'.uniqid().'@oravel.com.br', 'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id]);
        $vendedorB->forceFill(['email_verified_at' => now()])->save();
        $vendedorRole = Role::create(['name' => 'vendedor', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $vendedorA->assignRole($vendedorRole);
        $vendedorB->assignRole($vendedorRole);

        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead Teste', 'stage' => CrmLead::STAGE_NOVO]);
        $interaction = CrmLeadInteraction::create([
            'tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'user_id' => $vendedorA->id,
            'channel' => CrmLeadInteraction::CHANNEL_TELEFONE, 'contact_date' => now(), 'summary' => 'Contato A',
            'stage_at_time' => CrmLead::STAGE_NOVO,
        ]);

        $this->actingAs($vendedorB);
        $this->assertFalse($vendedorB->can('update', $interaction), 'vendedor B nao pode editar interacao registrada pelo vendedor A');
        $this->assertFalse($vendedorB->can('delete', $interaction));

        $this->actingAs($vendedorA);
        $this->assertTrue($vendedorA->can('update', $interaction), 'autor pode editar a propria interacao');
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
