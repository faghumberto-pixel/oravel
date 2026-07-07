<?php

namespace Tests\Feature;

use App\Filament\Pages\CrmFunil;
use App\Models\CrmLead;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CrmFunilTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano CRM '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_crm_leads'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant CRM '.uniqid(), 'slug' => 'tenant-crm-'.uniqid(),
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

    public function test_page_loads_and_groups_leads_by_stage(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead Novo', 'stage' => CrmLead::STAGE_NOVO, 'estimated_value' => 1000]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead Qualificado', 'stage' => CrmLead::STAGE_QUALIFICADO, 'estimated_value' => 2000]);

        $this->actingAs($admin);

        $this->get(CrmFunil::getUrl())->assertOk();

        $component = Livewire::test(CrmFunil::class);
        $grouped = $component->instance()->getLeadsByStage();

        $this->assertCount(1, $grouped->get(CrmLead::STAGE_NOVO));
        $this->assertCount(1, $grouped->get(CrmLead::STAGE_QUALIFICADO));
    }

    public function test_moving_to_perdido_requires_reason(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead X', 'stage' => CrmLead::STAGE_NOVO]);

        $this->actingAs($admin);

        Livewire::test(CrmFunil::class)->call('moveStage', $lead->id, CrmLead::STAGE_PERDIDO);
        $this->assertSame(CrmLead::STAGE_NOVO, $lead->fresh()->stage);

        Livewire::test(CrmFunil::class)->call('moveStage', $lead->id, CrmLead::STAGE_PERDIDO, 'Sem orçamento');
        $lead->refresh();
        $this->assertSame(CrmLead::STAGE_PERDIDO, $lead->stage);
        $this->assertSame('Sem orçamento', $lead->lost_reason);
    }

    public function test_non_admin_cannot_move_lead_not_assigned_to_them(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $vendor = User::create([
            'name' => 'Vendedor', 'email' => 'vendedor-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $vendor->forceFill(['email_verified_at' => now()])->save();
        $comercialRole = Role::firstOrCreate(['name' => 'comercial', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $vendor->assignRole($comercialRole);
        Permission::firstOrCreate(['name' => 'ler_crm_lead', 'guard_name' => 'web']);
        $vendor->givePermissionTo('ler_crm_lead');

        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead do Admin', 'stage' => CrmLead::STAGE_NOVO, 'assigned_user_id' => $admin->id]);

        $this->actingAs($vendor);

        Livewire::test(CrmFunil::class)->call('moveStage', $lead->id, CrmLead::STAGE_QUALIFICADO);

        $this->assertSame(CrmLead::STAGE_NOVO, $lead->fresh()->stage);
    }
}
