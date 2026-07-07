<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmLeadResource;
use App\Filament\Resources\CrmLeadResource\Pages\EditCrmLead;
use App\Filament\Resources\CrmLeadResource\RelationManagers\InteractionsRelationManager;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrmLeadResourceTest extends TestCase
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

    public function test_admin_can_create_a_lead(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CrmLeadResource\Pages\CreateCrmLead::class)
            ->fillForm([
                'name' => 'João da Silva',
                'company_name' => 'Construtora Alfa',
                'stage' => CrmLead::STAGE_NOVO,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('crm_leads', [
            'tenant_id' => $tenant->id,
            'name' => 'João da Silva',
            'company_name' => 'Construtora Alfa',
            'stage' => CrmLead::STAGE_NOVO,
        ]);
    }

    public function test_lost_reason_is_required_when_stage_is_perdido(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CrmLeadResource\Pages\CreateCrmLead::class)
            ->fillForm([
                'name' => 'Lead Perdido',
                'stage' => CrmLead::STAGE_PERDIDO,
                'lost_reason' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['lost_reason']);
    }

    public function test_registering_an_interaction_stamps_user_and_current_stage(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $lead = CrmLead::create([
            'tenant_id' => $tenant->id, 'name' => 'Lead Interação', 'stage' => CrmLead::STAGE_QUALIFICADO,
        ]);

        $this->actingAs($admin);

        Livewire::test(InteractionsRelationManager::class, [
            'ownerRecord' => $lead,
            'pageClass' => EditCrmLead::class,
        ])
            ->callTableAction('create', null, data: [
                'contact_date' => now()->toDateTimeString(),
                'channel' => CrmLeadInteraction::CHANNEL_TELEFONE,
                'summary' => 'Cliente confirmou interesse, vai avaliar orçamento.',
                'next_followup_date' => now()->addDays(3)->toDateString(),
            ]);

        $interaction = CrmLeadInteraction::where('crm_lead_id', $lead->id)->first();
        $this->assertNotNull($interaction);
        $this->assertSame($admin->id, $interaction->user_id);
        $this->assertSame(CrmLead::STAGE_QUALIFICADO, $interaction->stage_at_time);
    }

    public function test_latest_interaction_refreshes_the_leads_followup_cache(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead Followup', 'stage' => CrmLead::STAGE_NOVO]);

        CrmLeadInteraction::create([
            'tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'user_id' => $admin->id,
            'channel' => CrmLeadInteraction::CHANNEL_EMAIL, 'contact_date' => now()->subDays(5),
            'summary' => 'Primeiro contato', 'next_followup_date' => now()->addDays(1)->toDateString(),
            'stage_at_time' => CrmLead::STAGE_NOVO,
        ]);

        CrmLeadInteraction::create([
            'tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'user_id' => $admin->id,
            'channel' => CrmLeadInteraction::CHANNEL_TELEFONE, 'contact_date' => now(),
            'summary' => 'Segundo contato', 'next_followup_date' => now()->addDays(10)->toDateString(),
            'stage_at_time' => CrmLead::STAGE_CONTATO_INICIADO,
        ]);

        $lead->refresh();
        $this->assertSame(now()->addDays(10)->toDateString(), $lead->next_followup_date->toDateString());

        $this->actingAs($admin);

        $response = $this->get(CrmLeadResource::getUrl('index'));
        $response->assertOk();
        $response->assertSee(now()->addDays(10)->format('d/m/Y'));
        $response->assertDontSee(now()->addDays(1)->format('d/m/Y'));
    }

    public function test_resource_does_not_leak_another_tenants_leads(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        CrmLead::create(['tenant_id' => $tenantA->id, 'name' => 'Lead Tenant A']);

        [$tenantB, $adminB] = $this->makeTenantAdmin();

        $this->actingAs($adminB);

        $response = $this->get(CrmLeadResource::getUrl('index'));
        $response->assertOk();
        $response->assertDontSee('Lead Tenant A');
    }
}
