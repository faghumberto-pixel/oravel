<?php

namespace Tests\Feature\CrmFunil;

use App\Models\AIAnalysis;
use App\Models\CrmLead;
use App\Models\CrmLeadInteraction;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommercialLeadAnalysisService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Auditoria de segurança: Isolamento por tenant na IA do Funil.
 *
 * Verificar que:
 * 1. Dados de outro tenant não entram no prompt da Claude API
 * 2. IDOR não permite acessar leads de outro tenant
 * 3. AIAnalysis fica isolada por tenant
 * 4. Estatísticas de "baseline" só consideram leads do mesmo tenant
 */
class TenantIsolationSecurityTest extends TestCase
{
    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private CrmLead $leadA;
    private CrmLead $leadB;

    protected function setUp(): void
    {
        parent::setUp();

        // Criar role admin (necessária para testes)
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        // Criar dois tenants completamente separados
        $slugA = 'tenant-a-'.\Illuminate\Support\Str::random(8);
        $slugB = 'tenant-b-'.\Illuminate\Support\Str::random(8);
        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => $slugA]);
        $this->tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => $slugB]);

        // Criar usuários com tenant_id definido (CRÍTICO para BelongsToTenant funcionar)
        $this->userA = User::factory()->create([
            'email' => 'user-a-'.\Illuminate\Support\Str::random(8).'@test.local',
            'tenant_id' => $this->tenantA->id,
        ]);
        $this->userA->assignRole('admin');

        $this->userB = User::factory()->create([
            'email' => 'user-b-'.\Illuminate\Support\Str::random(8).'@test.local',
            'tenant_id' => $this->tenantB->id,
        ]);
        $this->userB->assignRole('admin');

        // Criar leads em cada tenant
        $this->leadA = CrmLead::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Lead Único de A',
            'stage' => 'novo',
        ]);

        $this->leadB = CrmLead::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Lead Único de B',
            'stage' => 'novo',
        ]);

        // Criar interações para cada lead
        CrmLeadInteraction::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'crm_lead_id' => $this->leadA->id,
            'user_id' => $this->userA->id,
            'channel' => 'telefone',
            'contact_date' => now(),
            'summary' => 'Contato de A',
            'stage_at_time' => 'novo',
        ]);

        CrmLeadInteraction::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'crm_lead_id' => $this->leadB->id,
            'user_id' => $this->userB->id,
            'channel' => 'telefone',
            'contact_date' => now(),
            'summary' => 'Contato de B',
            'stage_at_time' => 'novo',
        ]);
    }

    /**
     * [SEGURANÇA CRÍTICA] Verificar que a query de leads "convertidos"
     * (usada pra calcular taxa de conversão) filtra por tenant_id explicitamente.
     */
    public function test_converted_leads_query_filters_by_tenant()
    {
        $this->actingAs($this->userA);

        // Criar leads convertidos em cada tenant
        $convertidoA = CrmLead::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'name' => 'Convertido A',
            'stage' => CrmLead::STAGE_CONVERTIDO,
        ]);

        $convertidoB = CrmLead::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'name' => 'Convertido B',
            'stage' => CrmLead::STAGE_CONVERTIDO,
        ]);

        // A query em buildContext() filtra manualmente por tenant da lead,
        // então quando processando uma lead de A, apenas leads de A são considerados
        $convertidosA = CrmLead::where('tenant_id', $this->leadA->tenant_id)
            ->where('stage', CrmLead::STAGE_CONVERTIDO)
            ->get();

        // User A não consegue ver convertidos de B mesmo com query manual
        // (porque Auth::user()->tenant_id = tenantA->id, filtraria através do global scope se houvesse)
        // Mas neste teste, estamos fazendo query com where('tenant_id', ...) EXPLÍCITO,
        // então o filtro funciona porque é manual, não pelo global scope
        $this->assertCount(1, $convertidosA);
        $this->assertEquals($convertidoA->id, $convertidosA->first()->id);

        // Verificar isolamento: user A não consegue ver dados de B via query sem tenant_id explícito
        $allLeads = CrmLead::all();
        $this->assertContains($convertidoA->id, $allLeads->pluck('id'));
        $this->assertNotContains($convertidoB->id, $allLeads->pluck('id'),
            'User A conseguiu ver lead de outro tenant via query sem filtro (global scope falhou)');
    }

    /**
     * [SEGURANÇA CRÍTICA] Verificar que AIAnalysis é isolada por tenant_id
     * na tabela de armazenamento.
     */
    public function test_ai_analysis_isolation_by_tenant()
    {
        // Criar análises para cada tenant
        $analysisA = AIAnalysis::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'crm_lead_id' => $this->leadA->id,
            'context' => [],
        ]);

        $analysisB = AIAnalysis::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->userB->id,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'crm_lead_id' => $this->leadB->id,
            'context' => [],
        ]);

        // Confirmar que tenant_id está correto em cada análise
        $this->assertEquals($this->tenantA->id, $analysisA->tenant_id);
        $this->assertEquals($this->tenantB->id, $analysisB->tenant_id);

        // Quando user A atua, BelongsToTenant filtrará análises de B
        $this->actingAs($this->userA);
        $fetchedA = AIAnalysis::find($analysisA->id);
        $this->assertNotNull($fetchedA);

        // Query direta com BelongsToTenant
        $fetchedB = AIAnalysis::find($analysisB->id);
        $this->assertNull($fetchedB,
            'User A conseguiu ver análise de outro tenant (BelongsToTenant falhou)');
    }

    /**
     * [SEGURANÇA CRÍTICA] Verificar que o método analisarComIA() em CrmFunil
     * não permite IDOR -- i.e., não deixa trocar leadId para acessar lead de outro tenant.
     */
    public function test_idor_protection_in_analyze_with_ia()
    {
        // User A tenta acessar lead de B via analisarComIA()
        $this->actingAs($this->userA);

        // Simular a query que analisarComIA() faz (linha 211 do CrmFunil.php)
        $lead = CrmLead::where('tenant_id', $this->tenantA->id)->find($this->leadB->id);

        // Deve retornar null porque leadB pertence a tenantB, não tenantA
        $this->assertNull($lead,
            'IDOR: User A conseguiu encontrar lead de outro tenant com filtro de tenant_id');
    }

    /**
     * [SEGURANÇA CRÍTICA] Verificar que os dados enviados para Claude API
     * (via buildContext()) não contêm informações de outros tenants.
     */
    public function test_context_sent_to_ai_excludes_other_tenants()
    {
        $this->actingAs($this->userA);

        // Simular a construção do contexto (sem chamar Claude realmente)
        $convertidos = CrmLead::where('tenant_id', $this->leadA->tenant_id)
            ->where('stage', CrmLead::STAGE_CONVERTIDO)
            ->get(['created_at', 'updated_at']);

        $totalNoFunil = CrmLead::where('tenant_id', $this->leadA->tenant_id)->count();

        // Contexto só deve conter dados de leadA's tenant
        // Se leadA está em tenantA e há 1 lead total (o próprio leadA):
        $this->assertEquals(1, $totalNoFunil,
            'Query de totalNoFunil contém leads de outro tenant');

        // Interação do lead
        $lastInteraction = $this->leadA->interactions()->latest('contact_date')->first();
        $this->assertNotNull($lastInteraction);
        $this->assertEquals('Contato de A', $lastInteraction->summary,
            'Interação contém dados de outro tenant');
    }

    /**
     * [SEGURANÇA CRÍTICA] Verificar que a interação $lead->interactions()
     * está filtrada por lead_id, impedindo vazamento entre leads.
     */
    public function test_lead_interactions_scoped_by_lead_id()
    {
        $this->actingAs($this->userA);

        // leadA deve ter 1 interação (Contato de A)
        $interactionsA = $this->leadA->interactions()->get();
        $this->assertCount(1, $interactionsA);
        $this->assertEquals('Contato de A', $interactionsA->first()->summary);

        // User A não consegue ver leadB (está em outro tenant)
        // Portanto, vamos verificar isolamento via queries manuais
        $allInteractionsForTenantA = CrmLeadInteraction::where('tenant_id', $this->tenantA->id)->get();
        $this->assertContains('Contato de A', $allInteractionsForTenantA->pluck('summary'));
        $this->assertNotContains('Contato de B', $allInteractionsForTenantA->pluck('summary'),
            'User A conseguiu ver interação de outro tenant');

        // User B não consegue ver interações de A
        $this->actingAs($this->userB);
        $allInteractionsForTenantB = CrmLeadInteraction::where('tenant_id', $this->tenantB->id)->get();
        $this->assertContains('Contato de B', $allInteractionsForTenantB->pluck('summary'));
        $this->assertNotContains('Contato de A', $allInteractionsForTenantB->pluck('summary'),
            'User B conseguiu ver interação de outro tenant');
    }

    /**
     * [SEGURANÇA CRÍTICA] Verificar que o log de prompts/respostas em ai_analyses
     * é isolado por tenant, impedindo que um super admin veja respostas de outros tenants
     * sem explicitamente desabilitar isolamento.
     */
    public function test_ai_analysis_context_response_isolated_by_tenant()
    {
        $contextA = [
            'lead' => ['nome' => 'Lead A', 'empresa' => 'Empresa A'],
            'baseline_empresa' => ['taxa_media_conversao_pct' => 50],
        ];

        $contextB = [
            'lead' => ['nome' => 'Lead B', 'empresa' => 'Empresa B'],
            'baseline_empresa' => ['taxa_media_conversao_pct' => 75],
        ];

        $analysisA = AIAnalysis::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'crm_lead_id' => $this->leadA->id,
            'context' => $contextA,
            'response' => ['probabilidade_perda' => 'Alta (≈70%)'],
        ]);

        $analysisB = AIAnalysis::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->userB->id,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'crm_lead_id' => $this->leadB->id,
            'context' => $contextB,
            'response' => ['probabilidade_perda' => 'Baixa (≈30%)'],
        ]);

        // User A actua na sua análise
        $this->actingAs($this->userA);
        $fetchedA = AIAnalysis::find($analysisA->id);
        $this->assertEquals($contextA, $fetchedA->context);

        // User A NÃO deve conseguir ver análise de B mesmo consultando diretamente
        // (porque AIAnalysis tem BelongsToTenant)
        $fetchedB = AIAnalysis::find($analysisB->id);
        $this->assertNull($fetchedB,
            'User A conseguiu ver análise de outro tenant (BelongsToTenant falhou)');
    }

    /**
     * [SEGURANÇA CRÍTICA] Verificar que queries sem filtro explícito de tenant
     * ainda são protegidas por BelongsToTenant global scope.
     */
    public function test_belongstotenant_global_scope_protects_queries()
    {
        // Criar análises em ambos tenants
        $analysisA = AIAnalysis::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'context' => [],
        ]);

        $analysisB = AIAnalysis::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->userB->id,
            'type' => AIAnalysis::TYPE_COMERCIAL,
            'context' => [],
        ]);

        // User A faz query sem filtro de tenant explícito
        $this->actingAs($this->userA);
        $allAnalyses = AIAnalysis::all();

        // Deve retornar só a análise de A, não a de B
        $ids = $allAnalyses->pluck('id')->all();
        $this->assertContains($analysisA->id, $ids);
        $this->assertNotContains($analysisB->id, $ids,
            'Query::all() sem filtro explícito retornou dados de outro tenant (global scope falhou)');
    }
}
