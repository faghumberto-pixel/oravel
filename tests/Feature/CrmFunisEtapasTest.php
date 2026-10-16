<?php

namespace Tests\Feature;

use App\Filament\Pages\CrmFunil;
use App\Filament\Resources\CrmPipelineResource\Pages\ListCrmPipelines;
use App\Models\CrmEtapa;
use App\Models\CrmLead;
use App\Models\CrmMotivoPerda;
use App\Models\CrmOrigem;
use App\Models\CrmPipeline;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CrmFunilPadrao;
use App\Services\CrmFunilService;
use App\Services\TenantProvisioner;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 1 do CRM: funis/etapas configuráveis, serviço de movimentação, ponderado e isolamento. */
class CrmFunisEtapasTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Funil '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_crm_leads' => true, 'tabela_crm_funis' => true, 'tabela_crm_etapas' => true, 'tabela_crm_motivos_perda' => true, 'tabela_crm_origens' => true],
        ]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function etapa(Tenant $t, string $slug): CrmEtapa
    {
        return CrmEtapa::withoutGlobalScopes()->where('tenant_id', $t->id)->where('slug', $slug)->firstOrFail();
    }

    public function test_default_funnel_is_created_once_with_five_stages_reasons_and_sources(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $id = CrmFunilPadrao::garantir($tenant->id);
        $this->assertSame($id, CrmFunilPadrao::garantir($tenant->id), 'idempotente');

        $this->assertSame(1, CrmPipeline::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(['novo', 'contato_iniciado', 'qualificado', 'convertido', 'perdido'],
            CrmEtapa::withoutGlobalScopes()->where('crm_funil_id', $id)->orderBy('ordem')->pluck('slug')->all());
        $this->assertSame(7, CrmMotivoPerda::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(5, CrmOrigem::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $this->assertTrue((bool) CrmMotivoPerda::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', 'concorrencia')->value('exige_detalhe'));
    }

    public function test_backfill_links_existing_leads_by_stage_and_unknown_stage_falls_back_to_first_open_stage(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        // Leads "antigos": gravados direto no banco, sem etapa_id (como estavam antes da migration).
        foreach (['novo', 'qualificado', 'convertido', 'prospecção'] as $stage) {
            DB::table('crm_leads')->insert([
                'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'name' => 'L '.$stage,
                'stage' => $stage, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        CrmFunilPadrao::garantir($tenant->id);

        $porStage = DB::table('crm_leads')->join('crm_etapas', 'crm_etapas.id', '=', 'crm_leads.etapa_id')
            ->where('crm_leads.tenant_id', $tenant->id)->pluck('crm_etapas.slug', 'crm_leads.stage')->all();

        $this->assertEquals(['novo' => 'novo', 'qualificado' => 'qualificado', 'convertido' => 'convertido', 'prospecção' => 'novo'], $porStage);
        $this->assertSame(4, DB::table('crm_leads')->where('tenant_id', $tenant->id)->count(), 'nenhum lead perdido');
    }

    public function test_legacy_stage_writes_resolve_etapa_and_etapa_writes_mirror_stage(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L1', 'stage' => CrmLead::STAGE_QUALIFICADO]);
        $this->assertSame($this->etapa($tenant, 'qualificado')->id, $lead->etapa_id);

        // Etapa final personalizada: stage espelha 'convertido'/'perdido' para os leitores antigos.
        $funil = CrmPipeline::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $fechou = CrmEtapa::create(['tenant_id' => $tenant->id, 'crm_funil_id' => $funil->id, 'nome' => 'Fechou', 'tipo' => CrmEtapa::TIPO_GANHO, 'ordem' => 9]);
        $this->assertSame('fechou', $fechou->slug);
        $this->assertSame(100, $fechou->probabilidade);

        $lead->update(['etapa_id' => $fechou->id]);
        $this->assertSame(CrmLead::STAGE_CONVERTIDO, $lead->fresh()->stage);
        $this->assertTrue($lead->fresh()->isWon());
        $this->assertFalse($lead->fresh()->isOpen());
    }

    public function test_lead_cannot_use_a_stage_from_another_tenant(): void
    {
        [$a] = $this->makeTenantAdmin();
        [$b] = $this->makeTenantAdmin();
        CrmFunilPadrao::garantir($a->id);
        CrmFunilPadrao::garantir($b->id);

        $this->expectException(\InvalidArgumentException::class);
        CrmLead::create(['tenant_id' => $a->id, 'name' => 'X', 'etapa_id' => $this->etapa($b, 'novo')->id]);
    }

    public function test_service_requires_reason_and_detail_when_the_reason_asks_for_it(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'L', 'stage' => CrmLead::STAGE_NOVO]);
        $perdido = $this->etapa($tenant, 'perdido');
        $svc = app(CrmFunilService::class);

        try {
            $svc->moverLead($lead, $perdido, $admin);
            $this->fail('deveria exigir motivo');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('motivo', $e->errors());
        }

        try {
            $svc->moverLead($lead, $perdido, $admin, 'concorrencia');
            $this->fail('concorrência exige detalhe');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('detalhe', $e->errors());
        }

        $svc->moverLead($lead, $perdido, $admin, 'concorrencia', 'Locadora X');
        $lead->refresh();
        $this->assertTrue($lead->isLost());
        $this->assertSame('concorrencia', $lead->lost_reason_category);
        $this->assertSame('Locadora X', $lead->lost_reason);
    }

    public function test_service_rejects_other_tenant_stage_and_other_tenant_user(): void
    {
        [$a, $adminA] = $this->makeTenantAdmin();
        [$b, $adminB] = $this->makeTenantAdmin();
        $leadA = CrmLead::create(['tenant_id' => $a->id, 'name' => 'A', 'stage' => CrmLead::STAGE_NOVO]);
        CrmFunilPadrao::garantir($b->id);
        $svc = app(CrmFunilService::class);

        foreach ([[$leadA, $this->etapa($b, 'qualificado'), $adminA], [$leadA, $this->etapa($a, 'qualificado'), $adminB]] as [$l, $e, $u]) {
            try {
                $svc->moverLead($l, $e, $u);
                $this->fail('deveria rejeitar');
            } catch (\InvalidArgumentException) {
                $this->assertSame(CrmLead::STAGE_NOVO, $leadA->fresh()->stage);
            }
        }
    }

    public function test_kanban_columns_show_total_and_weighted_value_by_probability(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'A', 'stage' => 'novo', 'estimated_value' => 1000]);          // 10% = 100
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'B', 'stage' => 'qualificado', 'estimated_value' => 2000]);   // 50% = 1000
        CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'C', 'stage' => 'convertido', 'estimated_value' => 500]);     // ganho, fora do ponderado aberto
        $this->actingAs($admin);

        $page = Livewire::test(CrmFunil::class)->instance();
        $colunas = $page->getColunas()->keyBy(fn ($c) => $c['etapa']->slug);

        $this->assertEqualsWithDelta(1000.0, $colunas['novo']['total'], 0.01);
        $this->assertEqualsWithDelta(100.0, $colunas['novo']['ponderado'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $colunas['qualificado']['ponderado'], 0.01);
        $this->assertEqualsWithDelta(1100.0, $page->getWeightedPipelineValue(), 0.01);
        $this->assertEqualsWithDelta(3000.0, $page->getOpenPipelineValue(), 0.01);
    }

    public function test_second_funnel_is_independent_and_default_cannot_be_duplicated(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        CrmFunilPadrao::garantir($tenant->id);
        $this->actingAs($admin);

        $segundo = CrmPipeline::create(['tenant_id' => $tenant->id, 'nome' => 'Pós-venda', 'padrao' => false, 'ativo' => true]);
        $etapa = CrmEtapa::create(['tenant_id' => $tenant->id, 'crm_funil_id' => $segundo->id, 'nome' => 'Contato', 'tipo' => 'aberta', 'ordem' => 0, 'probabilidade' => 30]);
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'PV', 'etapa_id' => $etapa->id]);

        $colunasSegundo = Livewire::test(CrmFunil::class)->call('trocarFunil', $segundo->id)->instance()->getColunas();
        $this->assertSame([$lead->id], $colunasSegundo->first()['leads']->pluck('id')->all());

        $this->expectException(QueryException::class);
        DB::table('crm_funis')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'nome' => 'Outro padrão', 'padrao' => true, 'ativo' => true]);
    }

    public function test_funnel_resource_lists_only_own_tenant_funnels(): void
    {
        [$a, $adminA] = $this->makeTenantAdmin();
        [$b] = $this->makeTenantAdmin();
        CrmFunilPadrao::garantir($a->id);
        CrmFunilPadrao::garantir($b->id);
        CrmPipeline::withoutGlobalScopes()->where('tenant_id', $b->id)->update(['nome' => 'Funil do outro tenant']);

        $this->actingAs($adminA);

        Livewire::test(ListCrmPipelines::class)
            ->assertSee('Funil de Vendas')
            ->assertDontSee('Funil do outro tenant');
    }

    public function test_new_tenant_provisioning_creates_default_funnel(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        TenantProvisioner::provision($tenant, ['name' => 'Novo Admin', 'email' => 'n-'.uniqid().'@oravel.com.br', 'password' => 'secret123']);

        $this->assertTrue(CrmPipeline::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('padrao', true)->exists());
    }

    public function test_migration_is_reversible_and_keeps_every_lead(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $lead = CrmLead::create(['tenant_id' => $tenant->id, 'name' => 'Sobrevive', 'stage' => CrmLead::STAGE_QUALIFICADO]);

        $migration = require base_path('database/migrations/2026_11_12_100000_create_crm_funis_etapas_motivos_origens.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('crm_etapas'));
        $this->assertFalse(Schema::hasColumn('crm_leads', 'etapa_id'));
        $this->assertSame('qualificado', DB::table('crm_leads')->where('id', $lead->id)->value('stage'), 'stage intacto após o down');

        $migration->up();
        $linha = DB::table('crm_leads')->join('crm_etapas', 'crm_etapas.id', '=', 'crm_leads.etapa_id')
            ->where('crm_leads.id', $lead->id)->value('crm_etapas.slug');
        $this->assertSame('qualificado', $linha, 'up refaz o vínculo pelo stage');
    }
}
