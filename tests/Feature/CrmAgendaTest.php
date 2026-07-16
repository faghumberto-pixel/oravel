<?php

namespace Tests\Feature;

use App\Filament\Pages\CrmAgenda;
use App\Filament\Widgets\CrmAgendaWidget;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmAgendaTest extends TestCase
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
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_page_loads_and_widget_fetches_followup_events(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead Agenda', 'stage' => CrmLead::STAGE_NOVO]);

        CrmLeadInteraction::create([
            'tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'user_id' => $admin->id,
            'channel' => CrmLeadInteraction::CHANNEL_TELEFONE, 'contact_date' => now(),
            'summary' => 'Primeiro contato', 'next_followup_date' => now()->addDays(2)->toDateString(),
            'stage_at_time' => CrmLead::STAGE_NOVO,
        ]);

        $this->actingAs($admin);

        $this->get(CrmAgenda::getUrl())->assertOk();

        $events = Livewire::test(CrmAgendaWidget::class)
            ->instance()
            ->fetchEvents([
                'start' => now()->startOfMonth()->toIso8601String(),
                'end' => now()->endOfMonth()->toIso8601String(),
            ]);

        $this->assertCount(1, $events);
    }

    public function test_non_admin_only_sees_their_assigned_leads(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $vendorA = User::create([
            'name' => 'Vendedor A', 'email' => 'vendedor-a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $vendorA->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $comercialRole = Role::firstOrCreate(['name' => 'comercial', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $vendorA->assignRole($comercialRole);

        $vendorB = User::create([
            'name' => 'Vendedor B', 'email' => 'vendedor-b-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $vendorB->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $vendorB->assignRole($comercialRole);

        $leadA = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead do A', 'stage' => CrmLead::STAGE_NOVO, 'assigned_user_id' => $vendorA->id]);
        $leadB = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead do B', 'stage' => CrmLead::STAGE_NOVO, 'assigned_user_id' => $vendorB->id]);

        CrmLeadInteraction::create([
            'tenant_id' => $tenant->id, 'crm_lead_id' => $leadA->id, 'user_id' => $vendorA->id,
            'channel' => CrmLeadInteraction::CHANNEL_TELEFONE, 'contact_date' => now(),
            'summary' => 'Contato A', 'next_followup_date' => now()->addDay()->toDateString(),
            'stage_at_time' => CrmLead::STAGE_NOVO,
        ]);
        CrmLeadInteraction::create([
            'tenant_id' => $tenant->id, 'crm_lead_id' => $leadB->id, 'user_id' => $vendorB->id,
            'channel' => CrmLeadInteraction::CHANNEL_TELEFONE, 'contact_date' => now(),
            'summary' => 'Contato B', 'next_followup_date' => now()->addDay()->toDateString(),
            'stage_at_time' => CrmLead::STAGE_NOVO,
        ]);

        $this->actingAs($vendorA);

        $events = Livewire::test(CrmAgendaWidget::class)
            ->instance()
            ->fetchEvents([
                'start' => now()->startOfMonth()->toIso8601String(),
                'end' => now()->endOfMonth()->toIso8601String(),
            ]);

        $this->assertCount(1, $events);
    }
}
