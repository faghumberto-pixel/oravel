<?php

namespace Tests\Feature;

use App\Filament\Resources\CatalogoGrupoResource\Pages\EditCatalogoGrupo;
use App\Jobs\Aptidao\RecalcularAptidaoAtivoJob;
use App\Jobs\Aptidao\RecalcularAptidaoDiarioJob;
use App\Jobs\Aptidao\RecalcularAptidaoGrupoJob;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoCumprimento;
use App\Models\CatalogoItem;
use App\Models\CatalogoPreventiva;
use App\Models\CatalogoRegra;
use App\Models\CatalogoRequisito;
use App\Models\Nr13Inspection;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\Aptidao\AptidaoImpacto;
use App\Services\Catalogo\Aptidao\AssetFitnessService;
use App\Services\Catalogo\Aptidao\ResultadoAptidao;
use App\Services\Catalogo\CatalogoGestao;
use App\Services\Catalogo\CatalogoInstalador;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * G1 fase 3: AssetFitnessService (motor unico), cache, gatilhos, job diario, previa de impacto.
 * SEGURANCA: so roda em banco com "test" no nome (config/database.php fixa pgsql; o sqlite do phpunit.xml nao vale).
 */
class G1AptidaoTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private AssetCategory $grupo;

    private AssetFitnessService $motor;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }

        $this->motor = app(AssetFitnessService::class);
        $this->tenant = $this->novoTenant(true);
        app(CatalogoInstalador::class)->instalar($this->tenant);
        $this->grupo = AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'compressor')->firstOrFail();
    }

    private function novoTenant(bool $comChave): Tenant
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly',
            'is_active' => true, 'features' => $comChave ? [CatalogoItem::FEATURE_KEY => true] : []]);

        return Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'g1a-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
    }

    private function ativo(?AssetCategory $g = null, array $extra = [], ?Tenant $t = null): Asset
    {
        $t ??= $this->tenant;
        $g = func_num_args() === 0 ? $this->grupo : $g;

        return Asset::factory()->create(array_merge(['tenant_id' => $t->id, 'asset_category_id' => $g?->id, 'horimetro_atual' => 1000, 'odometro_atual' => 0], $extra));
    }

    private function req(string $slug, ?AssetCategory $g = null): CatalogoRequisito
    {
        return CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', ($g ?? $this->grupo)->id)->where('slug', $slug)->firstOrFail();
    }

    private function prev(string $slug): CatalogoPreventiva
    {
        return CatalogoPreventiva::withoutGlobalScopes()->where('grupo_id', $this->grupo->id)->where('slug', $slug)->firstOrFail();
    }

    private function cumprir(Asset $a, CatalogoRequisito $r, string $resultado = 'ok', array $extra = []): CatalogoCumprimento
    {
        return CatalogoCumprimento::create(array_merge(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'requisito_id' => $r->id, 'resultado' => $resultado, 'realizado_em' => now()], $extra));
    }

    private function fezPreventiva(Asset $a, string $slug, float $leitura, ?Carbon $quando = null): CatalogoCumprimento
    {
        return CatalogoCumprimento::create(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'preventiva_id' => $this->prev($slug)->id,
            'resultado' => 'ok', 'realizado_em' => $quando ?? now(), 'medidor_leitura' => $leitura]);
    }

    private function nr13(Asset $a, string $proxima, ?string $inspecaoEm = null): Nr13Inspection
    {
        return Nr13Inspection::withoutGlobalScopes()->create(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'tipo' => 'externa',
            'data_inspecao' => $inspecaoEm ?? now()->subDays(10)->toDateString(), 'data_proxima_inspecao' => $proxima, 'resultado' => 'aprovado']);
    }

    /** Ativo do compressor com tudo em dia. */
    private function ativoEmDia(): Asset
    {
        $a = $this->ativo();
        foreach (['prontuario', 'teste-valvula-seguranca', 'calibracao-manometro'] as $slug) {
            $this->cumprir($a, $this->req($slug));
        }
        $this->nr13($a, now()->addYear()->toDateString());
        foreach (['revisao-500h', 'revisao-1000h', 'revisao-2000h'] as $slug) {
            $this->fezPreventiva($a, $slug, 1000);
        }

        return $a->fresh();
    }

    private function motivos(ResultadoAptidao $r): array
    {
        return array_column($r->motivos, 'codigo');
    }

    // ------------------------------------------------------------ status basicos

    public function test_ativo_sem_grupo_e_cadastro_incompleto(): void
    {
        $r = $this->motor->calcular($this->ativo(null));

        $this->assertSame(ResultadoAptidao::INCOMPLETO, $r->status);
        $this->assertSame(['sem_grupo'], $this->motivos($r));
    }

    public function test_grupo_arquivado_e_cadastro_incompleto(): void
    {
        $a = $this->ativo();
        $this->grupo->delete();

        $this->assertSame(ResultadoAptidao::INCOMPLETO, $this->motor->calcular($a->fresh())->status);
    }

    public function test_sem_nenhuma_evidencia_e_apto_com_restricao_nao_inapto(): void
    {
        $r = $this->motor->calcular($this->ativo());

        $this->assertSame(ResultadoAptidao::RESTRICAO, $r->status, 'evidencia ausente nao e vencida: nao pode derrubar a frota inteira ao ligar a chave');
        $this->assertContains('sem_registro:prontuario', $this->motivos($r));
        $this->assertContains('sem_registro:revisao-500h', $this->motivos($r));
    }

    public function test_ativo_em_dia_e_apto(): void
    {
        $r = $this->motor->calcular($this->ativoEmDia());

        $this->assertSame(ResultadoAptidao::APTO, $r->status, json_encode($r->motivos));
        $this->assertSame([], $r->motivos);
    }

    public function test_sem_registro_configuravel_por_requisito(): void
    {
        $a = $this->ativoEmDia();
        CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->where('requisito_id', $this->req('prontuario')->id)->delete();

        $this->assertSame(ResultadoAptidao::RESTRICAO, $this->motor->calcular($a)->status);

        $this->req('prontuario')->update(['parametros' => ['sem_registro' => 'inapto']]);
        $this->assertSame(ResultadoAptidao::INAPTO, $this->motor->calcular($a)->status);

        $this->req('prontuario')->update(['parametros' => ['sem_registro' => 'ignorar']]);
        $this->assertSame(ResultadoAptidao::APTO, $this->motor->calcular($a)->status);
    }

    // ------------------------------------------------------------ NR-13 / documentos

    public function test_nr13_vencida_e_inapto_com_motivo_unico(): void
    {
        $a = $this->ativoEmDia();
        Nr13Inspection::withoutGlobalScopes()->where('asset_id', $a->id)->delete();
        $this->nr13($a, now()->subDay()->toDateString());

        $r = $this->motor->calcular($a);

        $this->assertSame(ResultadoAptidao::INAPTO, $r->status);
        $this->assertSame(1, count(array_filter($this->motivos($r), fn ($c) => $c === 'nr13_vencida')), 'requisito e strategy nao duplicam o motivo');
    }

    public function test_nr13_vence_hoje_ainda_vale(): void
    {
        $a = $this->ativoEmDia();
        Nr13Inspection::withoutGlobalScopes()->where('asset_id', $a->id)->delete();
        $this->nr13($a, now()->toDateString());

        $this->assertSame(ResultadoAptidao::APTO, $this->motor->calcular($a)->status);
    }

    public function test_documento_com_validade_vencida_e_inapto(): void
    {
        $a = $this->ativoEmDia();
        CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->where('requisito_id', $this->req('teste-valvula-seguranca')->id)->delete();
        $this->cumprir($a, $this->req('teste-valvula-seguranca'), 'ok', ['valido_ate' => now()->subDay()->toDateString()]);

        $r = $this->motor->calcular($a);

        $this->assertSame(ResultadoAptidao::INAPTO, $r->status);
        $this->assertContains('req:teste-valvula-seguranca', $this->motivos($r));
    }

    public function test_efeito_do_vencido_configuravel_para_restricao(): void
    {
        $a = $this->ativoEmDia();
        CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->where('requisito_id', $this->req('calibracao-manometro')->id)->delete();
        $this->cumprir($a, $this->req('calibracao-manometro'), 'ok', ['valido_ate' => now()->subDay()->toDateString()]);
        $this->req('calibracao-manometro')->update(['parametros' => ['efeito_vencido' => 'restricao']]);

        $this->assertSame(ResultadoAptidao::RESTRICAO, $this->motor->calcular($a)->status);
    }

    // ------------------------------------------------------------ checklist

    public function test_imp_reprovado_e_inapto_e_pede_os_alr_so_restringe(): void
    {
        $a = $this->ativoEmDia();

        $this->cumprir($a, $this->req('vazamentos-de-ar'), 'reprovado');
        $r = $this->motor->calcular($a);
        $this->assertSame(ResultadoAptidao::RESTRICAO, $r->status);
        $this->assertSame([], $r->osAGerar);

        $this->cumprir($a, $this->req('manometro'), 'reprovado');
        $r = $this->motor->calcular($a);
        $this->assertSame(ResultadoAptidao::INAPTO, $r->status);
        $this->assertSame(['manometro'], $r->osAGerar);
    }

    public function test_checklist_reprovado_e_depois_aprovado_volta_ao_normal(): void
    {
        $a = $this->ativoEmDia();
        $this->cumprir($a, $this->req('oleo'), 'reprovado', ['realizado_em' => now()->subHour()]);
        $this->assertSame(ResultadoAptidao::INAPTO, $this->motor->calcular($a)->status);

        $this->cumprir($a, $this->req('oleo'), 'ok', ['realizado_em' => now()]);
        $this->assertSame(ResultadoAptidao::APTO, $this->motor->calcular($a)->status);
    }

    // ------------------------------------------------------------ preventiva

    public function test_preventiva_tolerancia_de_10_por_cento(): void
    {
        $a = $this->ativoEmDia();
        // revisao-500h feita com 1000 h: vence em 1500. Tolerancia 10% = 50 h.
        foreach ([[1500, ResultadoAptidao::APTO], [1540, ResultadoAptidao::RESTRICAO], [1550, ResultadoAptidao::RESTRICAO], [1560, ResultadoAptidao::INAPTO]] as [$horas, $esperado]) {
            Asset::withoutGlobalScopes()->whereKey($a->id)->update(['horimetro_atual' => $horas]);
            // as de 1000 h/2000 h nao devem interferir ate 2000 h de uso: mantem so a de 500 h em teste
            $r = $this->motor->calcular($a->fresh());
            $this->assertSame($esperado, $r->status, "horimetro {$horas}: ".json_encode($r->motivos));
        }
    }

    public function test_tolerancia_da_preventiva_e_editavel_pelo_tenant(): void
    {
        $a = $this->ativoEmDia();
        Asset::withoutGlobalScopes()->whereKey($a->id)->update(['horimetro_atual' => 1560]);

        $this->assertSame(ResultadoAptidao::INAPTO, $this->motor->calcular($a->fresh())->status);

        $this->prev('revisao-500h')->update(['tolerancia_pct' => 15]);
        $this->assertSame(ResultadoAptidao::RESTRICAO, $this->motor->calcular($a->fresh())->status);
    }

    public function test_preventiva_por_dias_e_regra_primeiro_x_ambos(): void
    {
        $a = $this->ativoEmDia();
        $p = $this->prev('revisao-500h');
        $p->update(['ou_dias' => 100]);
        // feita ha 150 dias com 1000 h; horimetro em 1200 (dentro das 500 h) mas 50 dias acima do prazo = 50% -> inapto
        CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->where('preventiva_id', $p->id)->update(['realizado_em' => now()->subDays(150)]);
        Asset::withoutGlobalScopes()->whereKey($a->id)->update(['horimetro_atual' => 1200]);

        $this->assertSame(ResultadoAptidao::INAPTO, $this->motor->calcular($a->fresh())->status, 'primeiro: basta uma dimensao vencer');

        $p->update(['regra_ou' => 'ambos']);
        $this->assertSame(ResultadoAptidao::APTO, $this->motor->calcular($a->fresh())->status, 'ambos: so vence se as duas vencerem');
    }

    public function test_preventiva_por_km_usa_odometro(): void
    {
        $g = app(CatalogoGestao::class)->clonarGrupo($this->grupo, 'Veiculo');
        $p = CatalogoPreventiva::withoutGlobalScopes()->where('grupo_id', $g->id)->where('slug', 'revisao-500h')->firstOrFail();
        $p->update(['medidor' => 'km', 'intervalo' => 10000]);
        $a = $this->ativo($g, ['odometro_atual' => 25000, 'horimetro_atual' => 0]);
        CatalogoCumprimento::create(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'preventiva_id' => $p->id, 'resultado' => 'ok', 'realizado_em' => now(), 'medidor_leitura' => 15000]);

        $r = $this->motor->calcular($a);
        $this->assertNotContains('prev:revisao-500h', $this->motivos($r));

        Asset::withoutGlobalScopes()->whereKey($a->id)->update(['odometro_atual' => 26500]); // 1.500 km acima = 15% -> inapto
        $this->assertContains('prev:revisao-500h', $this->motivos($this->motor->calcular($a->fresh())));
    }

    // ------------------------------------------------------------ atributos e regras

    public function test_atributo_obrigatorio_vazio_e_so_aviso(): void
    {
        $a = $this->ativoEmDia();

        $r = $this->motor->calcular($a);
        $this->assertSame(ResultadoAptidao::APTO, $r->status);
        $this->assertNotEmpty($r->avisos);

        $a->update(['atributos_tecnicos' => ['pressao-maxima' => 12, 'vazao-pcm' => 100, 'tipo' => 'parafuso']]);
        $this->assertSame([], $this->motor->calcular($a->fresh())->avisos);
    }

    public function test_regra_em_dados_bloqueia_alocacao_sem_mudar_o_status(): void
    {
        $a = $this->ativoEmDia();
        CatalogoRegra::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'grupo_id' => $this->grupo->id, 'slug' => 'sem-plano', 'nome' => 'Sem plano de icamento',
            'efeito' => 'bloqueia_alocacao', 'condicao' => ['sem_cumprimento' => 'prontuario'], 'ativo' => true]);
        CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->where('requisito_id', $this->req('prontuario')->id)->delete();
        $this->req('prontuario')->update(['parametros' => ['sem_registro' => 'ignorar']]);

        $r = $this->motor->calcular($a);

        $this->assertTrue($r->bloqueiaAlocacao);
        $this->assertSame(ResultadoAptidao::APTO, $r->status);

        $this->cumprir($a, $this->req('prontuario'));
        $this->assertFalse($this->motor->calcular($a)->bloqueiaAlocacao);
    }

    public function test_regra_em_dados_inapto_por_atributo_vazio(): void
    {
        $a = $this->ativoEmDia();
        CatalogoRegra::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'grupo_id' => $this->grupo->id, 'slug' => 'sem-pressao', 'nome' => 'Pressao maxima nao informada',
            'efeito' => 'inapto', 'condicao' => ['atributo_vazio' => 'pressao-maxima'], 'ativo' => true]);

        $this->assertSame(ResultadoAptidao::INAPTO, $this->motor->calcular($a)->status);

        $a->update(['atributos_tecnicos' => ['pressao-maxima' => 12]]);
        $this->assertSame(ResultadoAptidao::APTO, $this->motor->calcular($a->fresh())->status);
    }

    // ------------------------------------------------------------ isolamento entre grupos

    public function test_ativo_de_um_grupo_nao_cobra_requisitos_de_outro(): void
    {
        $gerador = AssetCategory::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Gerador', 'slug' => 'gerador', 'is_active' => true,
            'medidor' => 'horimetro', 'criticidade_padrao' => 'media', 'exige_operador' => false]);
        $extintor = CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'extintor')->firstOrFail();
        $reqGerador = CatalogoRequisito::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'grupo_id' => $gerador->id, 'item_id' => $extintor->id,
            'slug' => 'extintor', 'efeito' => 'IMP', 'ativo' => true]);

        $compressor = $this->ativoEmDia();
        $ger = $this->ativo($gerador);

        // reprovar o extintor do gerador no ativo do compressor nao o afeta
        $this->cumprir($compressor, $reqGerador, 'reprovado');
        $r = $this->motor->calcular($compressor);
        $this->assertSame(ResultadoAptidao::APTO, $r->status);
        $this->assertNotContains('req:extintor', $this->motivos($r));

        // o gerador nao cobra NR-13 do compressor, mesmo com inspecao vencida no cadastro dele
        $this->nr13($ger, now()->subYear()->toDateString());
        $rg = $this->motor->calcular($ger->fresh());
        $this->assertNotContains('nr13_vencida', $this->motivos($rg));
        $this->assertSame([], array_values(array_filter($this->motivos($rg), fn ($c) => str_contains($c, 'prontuario') || str_contains($c, 'revisao'))));

        // e o requisito do gerador so aparece no gerador
        $this->cumprir($ger, $reqGerador, 'reprovado');
        $this->assertContains('req:extintor', $this->motivos($this->motor->calcular($ger->fresh())));
    }

    // ------------------------------------------------------------ historico imutavel

    public function test_editar_catalogo_nao_altera_cumprimento_antigo(): void
    {
        $req = $this->req('teste-valvula-seguranca');
        $req->update(['validade_dias' => 30]);
        $a = $this->ativo();
        $c = $this->cumprir($a, $req->fresh());
        $validoAte = $c->fresh()->valido_ate->toDateString();

        $this->assertSame(now()->addDays(30)->toDateString(), $validoAte);
        $this->assertSame('INSP', $c->snapshot['efeito']);
        $this->assertSame(30, $c->snapshot['validade_dias']);

        $req->update(['validade_dias' => 1, 'efeito' => 'ALR']);
        $c->update(['snapshot' => ['adulterado' => true], 'observacao' => 'x']); // snapshot e write-once

        $depois = $c->fresh();
        $this->assertSame($validoAte, $depois->valido_ate->toDateString());
        $this->assertSame('INSP', $depois->snapshot['efeito']);
        $this->assertSame(30, $depois->snapshot['validade_dias']);
        $this->assertSame('x', $depois->observacao);
    }

    public function test_snapshot_da_preventiva_guarda_o_intervalo_da_epoca(): void
    {
        $a = $this->ativo();
        $c = $this->fezPreventiva($a, 'revisao-500h', 1000);
        $this->prev('revisao-500h')->update(['intervalo' => 900]);

        $this->assertSame(500, $c->fresh()->snapshot['intervalo']);
    }

    // ------------------------------------------------------------ cache, gate, gatilhos, jobs

    public function test_recalcular_grava_o_cache_no_ativo(): void
    {
        $a = $this->ativoEmDia();
        $this->cumprir($a, $this->req('oleo'), 'reprovado');

        $this->motor->recalcular($a);
        $f = Asset::withoutGlobalScopes()->find($a->id);

        $this->assertSame('inapto', $f->aptidao_status);
        $this->assertSame('req:oleo', $f->aptidao_motivos['motivos'][0]['codigo']);
        $this->assertNotNull($f->aptidao_calculada_em);
        $this->assertFalse($f->aptidao_bloqueia_alocacao);
    }

    public function test_com_a_chave_desligada_nada_e_calculado(): void
    {
        $off = $this->novoTenant(false);
        $a = $this->ativo(null, [], $off);
        $a->update(['horimetro_atual' => 5]); // gatilho do Asset

        RecalcularAptidaoAtivoJob::dispatchSync($a->id);
        RecalcularAptidaoDiarioJob::dispatchSync($off->id);

        $this->assertNull(Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);
    }

    public function test_gatilho_de_horimetro_recalcula_quando_a_chave_esta_ligada(): void
    {
        $a = $this->ativoEmDia();
        $this->motor->recalcular($a);
        $this->assertSame('apto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);

        $a->update(['horimetro_atual' => 1600]); // 600 h depois da revisao de 500 h: vencida acima da tolerancia

        $this->assertSame('inapto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);
    }

    public function test_gatilho_de_cumprimento_e_de_nr13_recalcula(): void
    {
        $a = $this->ativoEmDia();
        $this->motor->recalcular($a);

        $this->cumprir($a, $this->req('manometro'), 'reprovado');
        $this->assertSame('inapto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);

        $this->cumprir($a, $this->req('manometro'), 'ok', ['realizado_em' => now()->addMinute()]);
        $this->assertSame('apto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);

        $this->nr13($a, now()->subDay()->toDateString(), now()->toDateString()); // inspecao mais recente, ja vencida
        $this->assertSame('inapto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);
    }

    public function test_gatilho_ao_trocar_o_grupo_do_ativo(): void
    {
        $a = $this->ativo(null);
        $this->assertSame('cadastro_incompleto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);

        $a->update(['asset_category_id' => $this->grupo->id]);

        $this->assertNotSame('cadastro_incompleto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);
    }

    public function test_job_diario_pega_vencimento_por_data_sem_evento(): void
    {
        $a = $this->ativoEmDia();
        CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->where('requisito_id', $this->req('teste-valvula-seguranca')->id)->delete();
        $this->cumprir($a, $this->req('teste-valvula-seguranca'), 'ok', ['valido_ate' => now()->addDay()->toDateString()]);
        $this->motor->recalcular($a->fresh());
        $this->assertSame('apto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);

        Carbon::setTestNow(now()->addDays(3));
        try {
            RecalcularAptidaoDiarioJob::dispatchSync($this->tenant->id);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame('inapto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);
    }

    public function test_job_de_grupo_e_comando_recalculam(): void
    {
        $a = $this->ativo();
        Asset::withoutGlobalScopes()->whereKey($a->id)->update(['aptidao_status' => null]);

        RecalcularAptidaoGrupoJob::dispatchSync($this->grupo->id);
        $this->assertNotNull(Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);

        Asset::withoutGlobalScopes()->whereKey($a->id)->update(['aptidao_status' => null]);
        $this->artisan('aptidao:recalcular', ['--tenant' => $this->tenant->id])->assertSuccessful();
        $this->assertNotNull(Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);
    }

    // ------------------------------------------------------------ previa de impacto

    public function test_previa_de_impacto_conta_ativos_que_mudariam(): void
    {
        $a1 = $this->ativoEmDia();
        $a2 = $this->ativoEmDia();
        $a3 = $this->ativoEmDia();
        foreach ([$a1, $a2] as $a) {
            $this->cumprir($a, $this->req('vazamentos-de-ar'), 'reprovado'); // ALR: ambos em restricao
        }

        $estado = ['requisitos' => $this->grupo->requisitos()->get()->mapWithKeys(fn ($r) => ['record-'.$r->id => $r->only(['item_id', 'efeito', 'validade_dias', 'gera_os', 'ativo'])])->all()];
        $estado['requisitos']['record-'.$this->req('vazamentos-de-ar')->id]['efeito'] = 'IMP'; // ALR -> IMP

        $p = app(AptidaoImpacto::class)->previa($this->grupo, $estado);

        $this->assertSame(3, $p['total']);
        $this->assertSame(2, $p['mudaram']);
        $this->assertSame(['apto_restricao>inapto' => 2], $p['transicoes']);
        // previa nao grava nada
        $this->assertSame('ALR', $this->req('vazamentos-de-ar')->efeito);

        // sem mudanca no formulario: ninguem muda
        $sem = ['requisitos' => $this->grupo->requisitos()->get()->mapWithKeys(fn ($r) => ['record-'.$r->id => $r->only(['item_id', 'efeito', 'validade_dias', 'gera_os', 'ativo'])])->all()];
        $this->assertSame(0, app(AptidaoImpacto::class)->previa($this->grupo, $sem)['mudaram']);
        $this->assertNotNull($a3);
    }

    public function test_previa_de_impacto_remover_requisito_e_requisito_novo(): void
    {
        $a = $this->ativoEmDia();
        $this->cumprir($a, $this->req('oleo'), 'reprovado'); // inapto por IMP

        $linhas = $this->grupo->requisitos()->get()->mapWithKeys(fn ($r) => ['record-'.$r->id => $r->only(['item_id', 'efeito', 'validade_dias', 'gera_os', 'ativo'])])->all();
        unset($linhas['record-'.$this->req('oleo')->id]);

        $p = app(AptidaoImpacto::class)->previa($this->grupo, ['requisitos' => $linhas]);
        $this->assertSame(['inapto>apto' => 1], $p['transicoes']);

        // requisito novo obrigatorio sem registro: apto -> apto_restricao
        $a2 = $this->ativoEmDia();
        $item = CatalogoItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'crlv')->firstOrFail();
        $linhas = $this->grupo->requisitos()->get()->mapWithKeys(fn ($r) => ['record-'.$r->id => $r->only(['item_id', 'efeito', 'validade_dias', 'gera_os', 'ativo'])])->all();
        $linhas['item-novo'] = ['item_id' => $item->id, 'efeito' => 'DOC', 'ativo' => true];
        $this->assertSame(1, app(AptidaoImpacto::class)->previa($this->grupo, ['requisitos' => $linhas])['transicoes']['apto>apto_restricao'] ?? 0);
        $this->assertNotNull($a2);
    }

    public function test_tela_de_edicao_mostra_previa_de_impacto(): void
    {
        $admin = User::create(['name' => 'Ana', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->ativoEmDia();

        Livewire::test(EditCatalogoGrupo::class, ['record' => $this->grupo->getKey()])->callAction('previa_impacto')->assertNotified();
    }
}
