<?php

namespace Tests\Feature;

use App\Filament\Resources\CrmLeadResource\Pages\EditCrmLead;
use App\Models\AIAnalysis;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommercialLeadAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CommercialLeadAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Lead '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_crm_leads', 'ia_diagnostico_avarias'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Lead '.uniqid(), 'slug' => 'tenant-lead-'.uniqid(),
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

    private function fakeClaudeJsonResponse(array $payload): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => json_encode($payload)],
                ],
            ], 200),
        ]);
    }

    public function test_analyze_stores_parsed_response_with_lead_reference(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $lead = CrmLead::create([
            'tenant_id' => $tenant->id, 'name' => 'João Pereira', 'company_name' => 'Construtora Delta',
            'stage' => CrmLead::STAGE_QUALIFICADO, 'estimated_value' => 50000,
        ]);

        CrmLeadInteraction::create([
            'tenant_id' => $tenant->id, 'crm_lead_id' => $lead->id, 'user_id' => $admin->id,
            'channel' => CrmLeadInteraction::CHANNEL_TELEFONE, 'stage_at_time' => CrmLead::STAGE_QUALIFICADO,
            'contact_date' => now()->subDays(3), 'summary' => 'Cliente pediu mais prazo',
        ]);

        $this->fakeClaudeJsonResponse([
            'probabilidade_perda' => 'Alta (≈65%) — sem contato há dias',
            'recomendacoes' => ['Ligar hoje mesmo', 'Oferecer condição especial'],
            'email_sugerido' => 'Olá João, tudo bem?',
        ]);

        $analysis = app(CommercialLeadAnalysisService::class)->analyze($lead, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_CONCLUIDA, $analysis->status);
        $this->assertSame(AIAnalysis::TYPE_COMERCIAL, $analysis->type);
        $this->assertSame($lead->id, $analysis->crm_lead_id);
        $this->assertSame('João Pereira', $analysis->reference_label);
        $this->assertStringContainsString('Alta', $analysis->response['probabilidade_perda']);
        $this->assertSame('Construtora Delta', $analysis->context['lead']['empresa']);
    }

    public function test_analyze_marks_analysis_as_failed_when_api_key_is_missing(): void
    {
        config(['services.anthropic.key' => null]);

        [$tenant, $admin] = $this->makeTenantAdmin();
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead Sem IA', 'stage' => CrmLead::STAGE_NOVO]);

        $analysis = app(CommercialLeadAnalysisService::class)->analyze($lead, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_FALHOU, $analysis->status);
        $this->assertNotNull($analysis->error);
    }

    public function test_edit_crm_lead_action_triggers_analysis_and_creates_ai_analysis_record(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead da Tela', 'stage' => CrmLead::STAGE_NOVO]);

        $this->fakeClaudeJsonResponse([
            'probabilidade_perda' => 'Baixa',
            'recomendacoes' => [],
            'email_sugerido' => null,
        ]);

        Livewire::test(EditCrmLead::class, ['record' => $lead->id])
            ->callAction('analisar_ia')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('ai_analyses', [
            'tenant_id' => $tenant->id,
            'crm_lead_id' => $lead->id,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'status' => AIAnalysis::STATUS_CONCLUIDA,
        ]);
    }

    public function test_action_is_not_visible_for_a_closed_lead(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Lead Convertido', 'stage' => CrmLead::STAGE_CONVERTIDO]);

        Livewire::test(EditCrmLead::class, ['record' => $lead->id])
            ->assertActionHidden('analisar_ia');
    }
}
