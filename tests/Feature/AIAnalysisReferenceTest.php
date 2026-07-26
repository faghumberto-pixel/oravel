<?php

namespace Tests\Feature;

use App\Filament\Resources\AIAnalysisResource\Pages\ListAIAnalyses;
use App\Models\AIAnalysis;
use App\Models\CrmLead;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AIAnalysisReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano IA '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_crm_leads', 'ia_diagnostico_avarias'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant IA '.uniqid(), 'slug' => 'tenant-ia-'.uniqid(),
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

    public function test_comercial_analysis_reference_label_resolves_to_lead_name(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $lead = CrmLead::create([
            'tenant_id' => $tenant->id,
            'name' => 'Maria Souza',
            'company_name' => 'Construtora Beta',
            'stage' => CrmLead::STAGE_NOVO,
        ]);

        $analysis = AIAnalysis::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'crm_lead_id' => $lead->id,
            'context' => ['lead' => ['nome' => 'Maria Souza']],
            'status' => AIAnalysis::STATUS_CONCLUIDA,
            'response' => ['probabilidade_perda' => 'Baixa'],
        ]);

        $this->assertSame('Maria Souza', $analysis->reference_label);
    }

    public function test_comercial_analysis_reference_label_falls_back_to_context_when_lead_is_deleted(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $lead = CrmLead::create([
            'tenant_id' => $tenant->id,
            'name' => 'Carlos Pereira',
            'stage' => CrmLead::STAGE_NOVO,
        ]);

        $analysis = AIAnalysis::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'crm_lead_id' => $lead->id,
            'context' => ['lead' => ['nome' => 'Carlos Pereira']],
            'status' => AIAnalysis::STATUS_CONCLUIDA,
            'response' => ['probabilidade_perda' => 'Alta'],
        ]);

        $lead->delete();
        $analysis->refresh();

        $this->assertNull($analysis->crm_lead_id);
        $this->assertSame('Carlos Pereira', $analysis->reference_label);
    }

    public function test_logistica_analysis_reference_label_resolves_to_formatted_date(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $analysis = AIAnalysis::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'type' => AIAnalysis::TYPE_LOGISTICA,
            'context' => ['data' => '2026-07-26', 'rotas' => []],
            'status' => AIAnalysis::STATUS_CONCLUIDA,
            'response' => ['resumo_geral' => 'ok'],
        ]);

        $this->assertSame('26/07/2026', $analysis->reference_label);
    }

    public function test_central_de_ia_table_shows_lead_name_for_comercial_analysis(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $lead = CrmLead::create([
            'tenant_id' => $tenant->id,
            'name' => 'Ana Lima',
            'stage' => CrmLead::STAGE_NOVO,
        ]);

        $analysis = AIAnalysis::create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'crm_lead_id' => $lead->id,
            'context' => ['lead' => ['nome' => 'Ana Lima']],
            'status' => AIAnalysis::STATUS_CONCLUIDA,
            'response' => ['probabilidade_perda' => 'Média'],
        ]);

        Livewire::test(ListAIAnalyses::class)
            ->assertTableColumnStateSet('reference_label', 'Ana Lima', record: $analysis);
    }
}
