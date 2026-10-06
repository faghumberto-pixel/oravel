<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmLeadResource;
use App\Models\CrmLead;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * O aviso "Novo lead do site" (sino da Central) abre /admin/crm-leads/{id}/edit. Com o super admin
 * atuando como OUTRO tenant, o filtro por tenant escondia o lead e dava 404 (06/10/2026).
 */
class SuperAdminOpensLeadFromBellTest extends TestCase
{
    use DatabaseTransactions;

    public function test_super_admin_acting_as_another_tenant_can_open_a_lead_and_switches_to_its_tenant(): void
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_crm_leads']]);
        $oravel = Tenant::create(['name' => 'Oravel '.uniqid(), 'slug' => 'orv-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $topmixx = Tenant::create(['name' => 'Topmixx '.uniqid(), 'slug' => 'top-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $lead = CrmLead::create(['tenant_id' => $oravel->id, 'name' => 'Fulano', 'company_name' => 'Empresa Lead', 'stage' => CrmLead::STAGE_NOVO]);

        $email = 'dono-'.uniqid().'@oravel.test';
        config(['oravel.super_admins' => [$email]]);
        $super = User::create(['name' => 'Dono', 'email' => $email, 'password' => bcrypt('x'), 'tenant_id' => $oravel->id, 'is_approved' => true]);
        $super->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($super)
            ->withSession(['acting_tenant_id' => $topmixx->id])
            ->get(CrmLeadResource::getUrl('edit', ['record' => $lead], panel: 'admin'))
            ->assertOk();

        $this->assertSame($oravel->id, session('acting_tenant_id'));
    }
}
