<?php

namespace Tests\Feature;

use App\Filament\Pages\PecasCriticasGrupos;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoItem;
use App\Models\CatalogoPecaCritica;
use App\Models\CatalogoSla;
use App\Models\MaintenanceOrder;
use App\Models\Material;
use App\Models\Plan;
use App\Models\PriorityLevel;
use App\Models\Role;
use App\Models\SlaPolicy;
use App\Models\SlaTimer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Catalogo\PecasCriticas;
use App\Services\Sla\SlaService;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * G1 fase 7: SLA por grupo x criticidade (em cima da F2/F6) e pecas criticas x estoque (Material).
 * SEGURANCA: so roda em banco com "test" no nome.
 */
class G1Fase7SlaPecasTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }

        SlaService::esquecer();
        Carbon::setTestNow('2026-11-09 12:00:00');
        [$this->tenant, $this->admin] = $this->novoTenant(true);
        app(CatalogoInstalador::class)->instalar($this->tenant);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        SlaService::esquecer();
        parent::tearDown();
    }

    /** @return array{0: Tenant, 1: User} */
    private function novoTenant(bool $comChave): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ($comChave ? [CatalogoItem::FEATURE_KEY => true] : []) + ['tabela_assets' => true, 'tabela_maintenance_orders' => true, 'tabela_sla_policies' => true]]);
        $t = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'g17-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $u = User::create(['name' => 'U', 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();
        $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));

        return [$t, $u];
    }

    private function grupo(string $slug, ?Tenant $t = null): AssetCategory
    {
        return AssetCategory::withoutGlobalScopes()->where('tenant_id', ($t ?? $this->tenant)->id)->where('slug', $slug)->firstOrFail();
    }

    private function ativo(string $grupo, ?Tenant $t = null): Asset
    {
        $t ??= $this->tenant;

        return Asset::factory()->create(['tenant_id' => $t->id, 'asset_category_id' => $this->grupo($grupo, $t)->id, 'status' => Asset::STATUS_DISPONIVEL]);
    }

    private function os(Asset $ativo, string $prioridade = 'alta', ?Tenant $t = null): MaintenanceOrder
    {
        return MaintenanceOrder::create(['tenant_id' => ($t ?? $this->tenant)->id, 'asset_id' => $ativo->id, 'description' => 'x',
            'maintenance_type' => 'Corretiva', 'status' => 'Pendente', 'priority' => $prioridade]);
    }

    private function timer(MaintenanceOrder $os, string $relogio): ?SlaTimer
    {
        return SlaTimer::withoutGlobalScopes()->where('subject_id', $os->id)->where('clock', $relogio)->first();
    }

    private function politicaGeral(array $extra = []): SlaPolicy
    {
        return SlaPolicy::withoutGlobalScopes()->create($extra + ['tenant_id' => $this->tenant->id, 'priority_code' => 'alta', 'response_minutes' => 999, 'service_minutes' => 600, 'resolution_minutes' => 2000, 'is_active' => true]);
    }

    private function slaDoGrupo(string $grupo, string $criticidade): CatalogoSla
    {
        return CatalogoSla::withoutGlobalScopes()->where('grupo_id', $this->grupo($grupo)->id)->where('criticidade', $criticidade)->firstOrFail();
    }

    public function test_os_do_grupo_usa_a_resposta_do_catalogo_pela_criticidade_da_prioridade(): void
    {
        $this->politicaGeral();
        $os = $this->os($this->ativo('guindaste'), 'critica');
        $sla = $this->slaDoGrupo('guindaste', 'critica');

        $resposta = $this->timer($os, 'response');
        $this->assertSame($sla->resposta_min, $resposta->target_minutes);
        $this->assertSame($sla->id, $resposta->catalogo_sla_id);
        $this->assertNull($resposta->policy_id);
        $this->assertSame('24x7', $sla->calendario);
        $this->assertSame('24x7', $resposta->calendar_mode);
    }

    public function test_relogio_que_o_grupo_nao_define_vem_da_politica_geral(): void
    {
        $geral = $this->politicaGeral();
        $os = $this->ativo('gerador');
        $os = $this->os($os, 'alta');

        $this->assertSame($this->slaDoGrupo('gerador', 'alta')->resposta_min, $this->timer($os, 'response')->target_minutes, 'resposta: do grupo');
        $this->assertNull($this->slaDoGrupo('gerador', 'alta')->resolucao_min);
        $res = $this->timer($os, 'resolution');
        $this->assertSame(2000, $res->target_minutes, 'resolucao: da politica geral');
        $this->assertSame($geral->id, $res->policy_id);
        $this->assertNull($res->catalogo_sla_id);
        $this->assertSame(600, $this->timer($os, 'service')->target_minutes);
    }

    public function test_politica_de_cliente_ou_contrato_vence_o_catalogo(): void
    {
        $cliente = \App\Models\Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Norte']);
        $acordo = $this->politicaGeral(['client_id' => $cliente->id, 'response_minutes' => 15, 'resolution_minutes' => 120]);
        $ativo = $this->ativo('gerador');
        $os = MaintenanceOrder::create(['tenant_id' => $this->tenant->id, 'asset_id' => $ativo->id, 'client_id' => $cliente->id, 'description' => 'x',
            'maintenance_type' => 'Corretiva', 'status' => 'Pendente', 'priority' => 'alta']);

        $this->assertSame(15, $this->timer($os, 'response')->target_minutes);
        $this->assertSame($acordo->id, $this->timer($os, 'response')->policy_id);
        $this->assertNull($this->timer($os, 'response')->catalogo_sla_id);
    }

    public function test_prioridade_personalizada_entra_pelo_nivel_base(): void
    {
        PriorityLevel::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'code' => 'urgentissima', 'name' => 'Urgentíssima', 'base_level' => 'critica', 'sort_order' => 0, 'is_system' => false, 'is_active' => true]);
        $os = $this->os($this->ativo('guindaste'), 'urgentissima');

        $this->assertSame($this->slaDoGrupo('guindaste', 'critica')->resposta_min, $this->timer($os, 'response')->target_minutes);
    }

    public function test_mudar_a_prioridade_recalcula_pelo_sla_da_nova_criticidade(): void
    {
        $os = $this->os($this->ativo('guindaste'), 'baixa');
        $this->assertSame($this->slaDoGrupo('guindaste', 'baixa')->resposta_min, $this->timer($os, 'response')->target_minutes);

        $os->update(['priority' => 'critica']);
        SlaService::recalcular($os->refresh());

        $this->assertSame($this->slaDoGrupo('guindaste', 'critica')->resposta_min, $this->timer($os, 'response')->target_minutes);
    }

    public function test_sem_a_chave_do_catalogo_nada_muda(): void
    {
        [$off, ] = $this->novoTenant(false);
        $grupo = AssetCategory::withoutGlobalScopes()->create(['tenant_id' => $off->id, 'name' => 'Qualquer', 'slug' => 'qualquer', 'is_active' => true]);
        CatalogoSla::withoutGlobalScopes()->create(['tenant_id' => $off->id, 'grupo_id' => $grupo->id, 'slug' => 'alta', 'criticidade' => 'alta', 'resposta_min' => 5, 'calendario' => '24x7', 'ativo' => true, 'origem' => 'tenant']);
        $ativo = Asset::factory()->create(['tenant_id' => $off->id, 'asset_category_id' => $grupo->id, 'status' => Asset::STATUS_DISPONIVEL]);
        $os = MaintenanceOrder::create(['tenant_id' => $off->id, 'asset_id' => $ativo->id, 'description' => 'x', 'maintenance_type' => 'Corretiva', 'status' => 'Pendente', 'priority' => 'alta']);

        $this->assertNull($this->timer($os, 'response'));
    }

    public function test_pausa_propria_do_grupo_vale_so_para_o_cronometro_dele(): void
    {
        $sla = $this->slaDoGrupo('guindaste', 'alta');
        $sla->update(['pausa_por' => ['pendencia', 'status_inventado']]);
        $os = $this->os($this->ativo('guindaste'), 'alta');
        $t = $this->timer($os, 'response');

        $this->assertSame(['pendencia'], $t->pause_statuses, 'so status conhecidos');

        // "aguardando_peca" pausa pelo padrao da empresa, mas o grupo definiu so "pendencia": nao pausa.
        $os->update(['internal_status' => 'aguardando_peca']);
        SlaService::avancar($os->refresh());
        $this->assertNull($t->refresh()->paused_at);

        $os->update(['internal_status' => 'pendencia']);
        SlaService::avancar($os->refresh());
        $this->assertNotNull($t->refresh()->paused_at);
    }

    public function test_pecas_criticas_classificam_o_estoque_do_material(): void
    {
        $g = $this->grupo('gerador');
        $mat = fn (string $n, int $atual, int $min) => Material::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'sku' => 'SKU-'.uniqid(), 'unit_cost' => 1, 'name' => $n, 'current_stock' => $atual, 'min_stock' => $min]);
        $peca = fn (string $d, ?Material $m) => CatalogoPecaCritica::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'grupo_id' => $g->id, 'slug' => 'p-'.uniqid(), 'descricao' => $d, 'material_id' => $m?->id, 'ativo' => true, 'origem' => 'tenant']);

        $peca('Filtro de oleo', $mat('Filtro', 2, 5));
        $peca('Correia', $mat('Correia', 0, 3));
        $peca('Bomba', $mat('Bomba', 10, 3));
        $peca('Injetor', null);
        $arquivada = $peca('Antiga', $mat('Antiga', 0, 3));
        $arquivada->update(['ativo' => false]);
        $this->ativo('gerador');

        $linhas = PecasCriticas::situacao($this->tenant->id);

        $this->assertSame(['sem_estoque', 'abaixo_minimo', 'sem_vinculo', 'ok'], $linhas->pluck('estado')->all(), 'mais grave primeiro; arquivada fora');
        $this->assertSame(1, $linhas->first()['ativos']);
        $this->assertSame(['sem_estoque' => 1, 'abaixo_minimo' => 1, 'sem_vinculo' => 1, 'ok' => 1], PecasCriticas::contagem($this->tenant->id));
    }

    public function test_pecas_criticas_nao_vazam_entre_tenants_nem_aparecem_sem_a_chave(): void
    {
        [$off, ] = $this->novoTenant(false);
        $grupo = AssetCategory::withoutGlobalScopes()->create(['tenant_id' => $off->id, 'name' => 'G', 'slug' => 'g', 'is_active' => true]);
        CatalogoPecaCritica::withoutGlobalScopes()->create(['tenant_id' => $off->id, 'grupo_id' => $grupo->id, 'slug' => 'x', 'descricao' => 'X', 'ativo' => true, 'origem' => 'tenant']);
        CatalogoPecaCritica::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'grupo_id' => $this->grupo('gerador')->id, 'slug' => 'y', 'descricao' => 'Y', 'ativo' => true, 'origem' => 'tenant']);

        $this->assertCount(0, PecasCriticas::situacao($off->id), 'chave desligada');
        $this->assertSame(['Y'], PecasCriticas::situacao($this->tenant->id)->map(fn ($l) => $l['peca']->descricao)->all());
    }

    public function test_pagina_de_pecas_criticas_mostra_a_situacao(): void
    {
        $m = Material::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'sku' => 'SKU-'.uniqid(), 'unit_cost' => 1, 'name' => 'Correia Z', 'current_stock' => 0, 'min_stock' => 3]);
        CatalogoPecaCritica::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'grupo_id' => $this->grupo('gerador')->id, 'slug' => 'c', 'descricao' => 'Correia do alternador', 'material_id' => $m->id, 'ativo' => true, 'origem' => 'tenant']);

        Livewire::test(PecasCriticasGrupos::class)->assertSee('Correia do alternador')->assertSee('Correia Z')->assertSee('Sem estoque');
    }
}
