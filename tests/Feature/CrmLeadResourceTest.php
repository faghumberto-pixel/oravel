<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmLeadResource;
use App\Filament\Resources\CrmLeadResource\Pages\EditCrmLead;
use App\Filament\Resources\CrmLeadResource\Pages\ListCrmLeads;
use App\Filament\Resources\CrmLeadResource\RelationManagers\InteractionsRelationManager;
use App\Filament\Resources\CrmLeadResource\Widgets\CrmLeadStats;
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
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
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

    /**
     * lost_reason (texto livre) virou o DETALHE de lost_reason_category
     * (categoria estruturada, nova) -- a categoria e' sempre obrigatoria
     * quando perdido; o detalhe so' e' obrigatorio pras categorias que
     * precisam de mais contexto (concorrencia/outro).
     */
    public function test_lost_reason_category_is_required_when_stage_is_perdido(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CrmLeadResource\Pages\CreateCrmLead::class)
            ->fillForm([
                'name' => 'Lead Perdido',
                'stage' => CrmLead::STAGE_PERDIDO,
                'lost_reason_category' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['lost_reason_category']);
    }

    public function test_lost_reason_detail_is_required_only_for_concorrencia_or_outro(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CrmLeadResource\Pages\CreateCrmLead::class)
            ->fillForm([
                'name' => 'Lead Perdido Sem Orçamento',
                'stage' => CrmLead::STAGE_PERDIDO,
                'lost_reason_category' => CrmLead::LOST_REASON_SEM_ORCAMENTO,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CrmLeadResource\Pages\CreateCrmLead::class)
            ->fillForm([
                'name' => 'Lead Perdido Pra Concorrente',
                'stage' => CrmLead::STAGE_PERDIDO,
                'lost_reason_category' => CrmLead::LOST_REASON_CONCORRENCIA,
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

    /**
     * Mesmo padrão já usado em Assets/Clients/Materials/Fornecedores
     * (dashboard de 4 KPIs no topo da listagem) -- só faltava em Leads.
     */
    public function test_crm_lead_stats_widget_computes_funnel_counts(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Novo 1', 'stage' => CrmLead::STAGE_NOVO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Contato 1', 'stage' => CrmLead::STAGE_CONTATO_INICIADO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Qualificado 1', 'stage' => CrmLead::STAGE_QUALIFICADO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Convertido 1', 'stage' => CrmLead::STAGE_CONVERTIDO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Perdido 1', 'stage' => CrmLead::STAGE_PERDIDO]);
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Perdido 2', 'stage' => CrmLead::STAGE_PERDIDO]);

        $this->actingAs($admin);

        // getStats() é protected (mesmo padrão de todo StatsOverviewWidget
        // do projeto) -- reflection é o jeito limpo de testar o cálculo
        // sem expor o método ou passar pelo ciclo de vida do Livewire.
        $method = new \ReflectionMethod(CrmLeadStats::class, 'getStats');
        $method->setAccessible(true);
        $stats = $method->invoke(new CrmLeadStats);

        $this->assertSame(6, $stats[0]->getValue());
        $this->assertSame(2, $stats[1]->getValue());
        $this->assertSame(1, $stats[2]->getValue());
        $this->assertSame(2, $stats[3]->getValue());
    }

    /**
     * Achado de auditoria de segurança 2026-08-19: o filtro "Vendedor"
     * (assigned_user_id) tinha o mesmo padrão do bug corrigido no commit
     * 1425168 -- relationship('assignedUser', 'name') sem modifyQueryUsing,
     * vazando nomes de usuários de todos os tenants nas opções do filtro.
     */
    public function test_assigned_user_filter_only_offers_users_from_the_current_tenant(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        [$otherTenant] = $this->makeTenantAdmin();
        $userFromOtherTenant = User::create([
            'name' => 'Vendedor de Outro Tenant', 'email' => 'vendedor-outro-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $otherTenant->id,
        ]);
        $userFromOtherTenant->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin);

        // Exercita o dropdown do filtro de verdade (mesmo caminho que o
        // navegador percorre ao abrir o painel de filtros da tabela),
        // em vez de reimplementar a query -- só assim se confirma que o
        // modifyQueryUsing aplicado de fato afeta o que o Filament exibe.
        Livewire::test(ListCrmLeads::class)
            ->assertSeeHtml($admin->name)
            ->assertDontSeeHtml($userFromOtherTenant->name);
    }
}
