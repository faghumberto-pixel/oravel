<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\RelationManagers\CumprimentosRelationManager;
use App\Filament\Widgets\AptidaoMotivosWidget;
use App\Filament\Widgets\AptidaoPorGrupoWidget;
use App\Livewire\ChecklistAptidaoMobile;
use App\Models\AptidaoLiberacao;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoCumprimento;
use App\Models\CatalogoItem;
use App\Models\CatalogoRequisito;
use App\Models\Client;
use App\Models\EquipmentMovement;
use App\Models\EquipmentReplacement;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\Aptidao\AptidaoBloqueio;
use App\Services\Catalogo\Aptidao\AptidaoLiberacaoService;
use App\Services\Catalogo\Aptidao\AssetFitnessService;
use App\Services\Catalogo\Aptidao\ChecklistAptidaoService;
use App\Services\Catalogo\Aptidao\ResultadoAptidao;
use App\Services\Catalogo\CatalogoInstalador;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * G1 fase 5: consumidores do motor -- bloqueio de alocacao/despacho, liberacao excepcional, OS automatica em IMP reprovado,
 * checklist no campo e paineis. SEGURANCA: so roda em banco com "test" no nome.
 */
class G1ConsumidoresTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    private AssetFitnessService $motor;

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }

        $this->motor = app(AssetFitnessService::class);
        [$this->tenant, $this->admin] = $this->novoTenant(true);
        app(CatalogoInstalador::class)->instalar($this->tenant);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    /** @return array{0: Tenant, 1: User} */
    private function novoTenant(bool $comChave): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ($comChave ? [CatalogoItem::FEATURE_KEY => true] : []) + ['tabela_assets' => true]]);
        $t = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'g1k-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);

        return [$t, $this->usuario($t, admin: true)];
    }

    private function usuario(Tenant $t, bool $admin = false, array $permissoes = []): User
    {
        $u = User::create(['name' => 'U', 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $t->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();

        if ($admin) {
            $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $t->id]));
        }

        foreach ($permissoes as $p) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }

        return $u;
    }

    private function g(string $slug): AssetCategory
    {
        return AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', $slug)->firstOrFail();
    }

    private function req(string $grupo, string $slug): CatalogoRequisito
    {
        return CatalogoRequisito::withoutGlobalScopes()->where('grupo_id', $this->g($grupo)->id)->where('slug', $slug)->firstOrFail();
    }

    private function ativo(?string $grupo, array $extra = [], ?Tenant $t = null): Asset
    {
        $t ??= $this->tenant;

        return Asset::factory()->create(array_merge(['tenant_id' => $t->id, 'asset_category_id' => $grupo ? $this->g($grupo)->id : null,
            'horimetro_atual' => 100, 'status' => Asset::STATUS_DISPONIVEL], $extra));
    }

    private function cumprir(Asset $a, CatalogoRequisito $r, string $resultado = 'ok', array $extra = []): CatalogoCumprimento
    {
        return CatalogoCumprimento::create(array_merge(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'requisito_id' => $r->id, 'resultado' => $resultado, 'realizado_em' => now()], $extra));
    }

    /** Gerador com o aterramento (IMP) reprovado: Inapto. */
    private function inapto(): Asset
    {
        $a = $this->ativo('gerador');
        $this->cumprir($a, $this->req('gerador', 'aterramento'), 'reprovado');

        return $a->fresh();
    }

    private function mobilizacao(Asset $a, array $extra = []): EquipmentMovement
    {
        return EquipmentMovement::create(array_merge(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'type' => EquipmentMovement::TYPE_MOBILIZACAO,
            'status' => EquipmentMovement::STATUS_AGUARDANDO_VISTORIA, 'scheduled_at' => now()], $extra));
    }

    // ============================================================ bloqueio de alocacao / despacho

    public function test_chave_desligada_nunca_bloqueia(): void
    {
        [$off] = $this->novoTenant(false);
        $a = Asset::factory()->create(['tenant_id' => $off->id, 'asset_category_id' => null, 'status' => Asset::STATUS_DISPONIVEL]);

        $this->assertNull(app(AptidaoBloqueio::class)->motivo($a));
        $this->assertNotNull($this->mobilizacao($a)->id);
    }

    public function test_apto_e_apto_com_restricao_podem_ser_alocados(): void
    {
        $apto = $this->ativo('gerador');
        $this->assertNull(app(AptidaoBloqueio::class)->motivo($apto));

        $restricao = $this->ativo('gerador');
        $this->cumprir($restricao, $this->req('gerador', 'cabos-conexoes'), 'reprovado'); // ALR
        $this->assertSame(ResultadoAptidao::RESTRICAO, $this->motor->calcular($restricao->fresh())->status);
        $this->assertNull(app(AptidaoBloqueio::class)->motivo($restricao->fresh()));
    }

    public function test_inapto_e_cadastro_incompleto_sao_bloqueados_com_motivo_claro(): void
    {
        $m = app(AptidaoBloqueio::class)->motivo($this->inapto());
        $this->assertStringContainsString('Inapto', $m);
        $this->assertStringContainsString('Aterramento', $m);
        $this->assertStringContainsString('liberação excepcional', $m);

        $semGrupo = $this->ativo(null);
        $this->assertStringContainsString('cadastro incompleto', app(AptidaoBloqueio::class)->motivo($semGrupo));
    }

    public function test_despacho_de_mobilizacao_sem_os_e_bloqueado_para_ativo_inapto(): void
    {
        $a = $this->inapto();

        try {
            $this->mobilizacao($a);
            $this->fail('Deveria bloquear o despacho do ativo Inapto');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Inapto', $e->errors()['asset_id'][0]);
        }

        $this->assertSame(0, EquipmentMovement::withoutGlobalScopes()->where('asset_id', $a->id)->count());
    }

    public function test_mobilizacao_de_os_de_manutencao_e_desmobilizacao_nao_sao_bloqueadas(): void
    {
        $a = $this->inapto();
        $os = MaintenanceOrder::create(['tenant_id' => $a->tenant_id, 'asset_id' => $a->id, 'description' => 'conserto', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto']);

        $this->assertNotNull($this->mobilizacao($a, ['maintenance_order_id' => $os->id])->id, 'ativo Inapto precisa ir ao conserto');
        $this->assertNotNull($this->mobilizacao($a, ['type' => EquipmentMovement::TYPE_DESMOBILIZACAO])->id);
    }

    public function test_liberacao_vigente_permite_o_despacho_e_expirar_bloqueia_de_novo(): void
    {
        $a = $this->inapto();
        app(AptidaoLiberacaoService::class)->liberar($a, 'Cliente aguarda, risco aceito pelo gestor', now()->addDay(), $this->admin);

        $this->assertNotNull($this->mobilizacao($a->fresh())->id);

        Carbon::setTestNow(now()->addDays(2));
        try {
            $this->assertNotNull(app(AptidaoBloqueio::class)->motivo($a->fresh()), 'liberacao vencida bloqueia de novo');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_solicitacao_virando_contrato_fechado_exige_ativo_apto(): void
    {
        $client = Client::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente']);
        $a = $this->inapto();
        $s = SolicitacaoLocacao::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'customer_id' => $client->id,
            'category_id' => $this->g('gerador')->id, 'asset_id' => $a->id, 'data_saida_prevista' => now()->addWeek(), 'status_comercial' => 'proposta_em_andamento']);

        try {
            $s->update(['status_comercial' => 'contrato_fechado']);
            $this->fail('Deveria bloquear');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Inapto', collect($e->errors())->flatten()->first());
        }
        $this->assertSame('proposta_em_andamento', $s->fresh()->status_comercial);

        // com a OS resolvida e o item reinspecionado OK, o fechamento passa
        $this->cumprir($a, $this->req('gerador', 'aterramento'), 'ok', ['realizado_em' => now()->addMinute()]);
        $s->update(['status_comercial' => 'contrato_fechado']);
        $this->assertSame('contrato_fechado', $s->fresh()->status_comercial);
    }

    public function test_troca_de_equipamento_bloqueia_substituto_inapto_sem_criar_movimentacao(): void
    {
        $original = $this->ativo('gerador');
        $substituto = $this->inapto();
        $troca = (new EquipmentReplacement)->forceFill(['tenant_id' => $this->tenant->id, 'original_asset_id' => $original->id, 'replacement_asset_id' => $substituto->id]);

        $this->expectException(ValidationException::class);
        try {
            $troca->startLogisticsMovements();
        } finally {
            $this->assertSame(0, EquipmentMovement::withoutGlobalScopes()->whereIn('asset_id', [$original->id, $substituto->id])->count());
        }
    }

    // ============================================================ liberacao excepcional

    public function test_so_quem_tem_a_permissao_propria_libera_e_o_admin_tem_bypass(): void
    {
        $a = $this->inapto();
        $comum = $this->usuario($this->tenant, permissoes: ['ler_catalogo_equipamentos', 'editar_catalogo_equipamentos']);
        $gestor = $this->usuario($this->tenant, permissoes: ['criar_liberacao_aptidao']);
        $svc = app(AptidaoLiberacaoService::class);

        try {
            $svc->liberar($a, 'tentativa sem permissao propria', now()->addDay(), $comum);
            $this->fail('Quem gerencia o catalogo mas nao tem a permissao propria nao pode liberar');
        } catch (AuthorizationException) {
            $this->assertNull(AptidaoLiberacao::vigente($a));
        }

        $this->assertNotNull($svc->liberar($a, 'Responsavel tecnico avaliou o risco', now()->addDay(), $gestor)->id);
    }

    public function test_liberacao_exige_justificativa_prazo_valido_e_ativo_inapto(): void
    {
        $a = $this->inapto();
        $svc = app(AptidaoLiberacaoService::class);

        foreach ([['curta', now()->addDay(), 'justificativa'], ['Justificativa suficiente aqui', now()->subHour(), 'valida_ate'], ['Justificativa suficiente aqui', now()->addDays(31), 'valida_ate']] as [$j, $ate, $campo]) {
            try {
                $svc->liberar($a, $j, $ate, $this->admin);
                $this->fail("Deveria recusar ({$campo})");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($campo, $e->errors());
            }
        }

        $apto = $this->ativo('gerador');
        $this->expectException(ValidationException::class);
        $svc->liberar($apto, 'Justificativa suficiente aqui', now()->addDay(), $this->admin);
    }

    public function test_liberacao_nao_apaga_a_inaptidao_e_grava_log_com_justificativa(): void
    {
        $a = $this->inapto();
        app(AptidaoLiberacaoService::class)->liberar($a, 'Equipamento critico para o cliente X', now()->addDays(2), $this->admin);

        $r = $this->motor->calcular($a->fresh());
        $this->assertSame(ResultadoAptidao::INAPTO, $r->status, 'continua Inapto e visivel');
        $this->assertNotNull($r->liberacao);
        $this->assertNotNull(Asset::withoutGlobalScopes()->find($a->id)->aptidao_liberada_ate);

        $log = Activity::query()->where('description', 'Aptidão: liberação excepcional concedida')->latest()->first();
        $this->assertSame('Equipamento critico para o cliente X', $log->properties['justificativa']);
        $this->assertSame('req:aterramento', $log->properties['motivos'][0]['codigo']);
        $this->assertSame((string) $this->admin->id, (string) $log->causer_id);
    }

    public function test_nao_duplica_liberacao_e_revogar_bloqueia_de_novo(): void
    {
        $a = $this->inapto();
        $svc = app(AptidaoLiberacaoService::class);
        $l = $svc->liberar($a, 'Primeira liberacao justificada', now()->addDay(), $this->admin);

        try {
            $svc->liberar($a, 'Segunda liberacao justificada', now()->addDay(), $this->admin);
            $this->fail('Deveria recusar liberacao duplicada');
        } catch (ValidationException) {
        }

        $svc->revogar($l, 'Risco nao e mais aceitavel', $this->admin);

        $this->assertNull(AptidaoLiberacao::vigente($a));
        $this->assertNotNull(app(AptidaoBloqueio::class)->motivo($a->fresh()));
        $this->assertSame('Risco nao e mais aceitavel', Activity::query()->where('description', 'Aptidão: liberação excepcional revogada')->latest()->first()->properties['justificativa']);
        $this->assertNull(Asset::withoutGlobalScopes()->find($a->id)->aptidao_liberada_ate);
    }

    public function test_liberacao_nao_levanta_bloqueio_de_regra_especial_nem_cadastro_incompleto(): void
    {
        // guindaste sem plano de icamento + um IMP reprovado (Inapto): liberar nao basta, a regra especial continua bloqueando
        $guindaste = $this->ativo('guindaste');
        $this->cumprir($guindaste, $this->req('guindaste', 'freios'), 'reprovado');
        app(AptidaoLiberacaoService::class)->liberar($guindaste->fresh(), 'Liberacao do gestor para teste', now()->addDay(), $this->admin);

        $m = app(AptidaoBloqueio::class)->motivo($guindaste->fresh());
        $this->assertNotNull($m);
        $this->assertStringContainsString('icamento', $m, 'a regra especial de bloqueio e citada');

        // cadastro incompleto: nao existe liberacao (so Inapto pode ser liberado)
        $this->expectException(ValidationException::class);
        app(AptidaoLiberacaoService::class)->liberar($this->ativo(null), 'Justificativa suficiente aqui', now()->addDay(), $this->admin);
    }

    // ============================================================ OS automatica

    public function test_imp_reprovado_abre_os_corretiva_e_vincula_o_cumprimento(): void
    {
        $a = $this->ativo('gerador');
        $c = $this->cumprir($a, $this->req('gerador', 'aterramento'), 'reprovado', ['observacao' => 'cabo solto']);

        $os = MaintenanceOrder::withoutGlobalScopes()->where('asset_id', $a->id)->get();
        $this->assertCount(1, $os);
        $this->assertSame(MaintenanceOrder::TYPE_CORRECTIVE, $os[0]->maintenance_type);
        $this->assertSame('Aberto', $os[0]->status);
        $this->assertSame('aptidao_auto', $os[0]->origin);
        $this->assertStringContainsString('Aterramento', $os[0]->description);
        $this->assertStringContainsString('cabo solto', $os[0]->description);
        $this->assertSame($os[0]->id, $c->fresh()->maintenance_order_id);
    }

    public function test_nao_abre_os_para_alr_ok_imp_sem_gera_os_ou_chave_desligada(): void
    {
        $a = $this->ativo('gerador');
        $this->cumprir($a, $this->req('gerador', 'cabos-conexoes'), 'reprovado'); // ALR
        $this->cumprir($a, $this->req('gerador', 'aterramento'), 'ok');           // OK
        $this->req('gerador', 'oleo')->update(['gera_os' => false]);
        $this->cumprir($a, $this->req('gerador', 'oleo'), 'reprovado');           // IMP sem gera_os

        $this->assertSame(0, MaintenanceOrder::withoutGlobalScopes()->where('asset_id', $a->id)->count());

        [$off] = $this->novoTenant(false);
        $b = Asset::factory()->create(['tenant_id' => $off->id, 'status' => Asset::STATUS_DISPONIVEL]);
        $c = CatalogoCumprimento::create(['tenant_id' => $off->id, 'asset_id' => $b->id, 'requisito_id' => $this->req('gerador', 'aterramento')->id, 'resultado' => 'reprovado', 'realizado_em' => now(),
            'snapshot' => ['efeito' => 'IMP', 'gera_os' => true, 'nome' => 'x']]);
        $this->assertSame(0, MaintenanceOrder::withoutGlobalScopes()->where('asset_id', $b->id)->count());
        $this->assertNull($c->fresh()->maintenance_order_id);
    }

    public function test_reprovar_de_novo_com_os_aberta_nao_duplica_e_com_os_fechada_abre_nova(): void
    {
        $a = $this->ativo('gerador');
        $req = $this->req('gerador', 'aterramento');
        $c1 = $this->cumprir($a, $req, 'reprovado');
        $c2 = $this->cumprir($a, $req, 'reprovado');

        $this->assertSame(1, MaintenanceOrder::withoutGlobalScopes()->where('asset_id', $a->id)->count());
        $this->assertSame($c1->fresh()->maintenance_order_id, $c2->fresh()->maintenance_order_id);

        MaintenanceOrder::withoutGlobalScopes()->whereKey($c1->fresh()->maintenance_order_id)->update(['status' => 'Concluída']);
        $c3 = $this->cumprir($a, $req, 'reprovado');

        $this->assertSame(2, MaintenanceOrder::withoutGlobalScopes()->where('asset_id', $a->id)->count());
        $this->assertNotSame($c1->fresh()->maintenance_order_id, $c3->fresh()->maintenance_order_id);
    }

    public function test_fechar_a_os_nao_aprova_o_item_so_um_novo_checklist_ok(): void
    {
        $a = $this->ativo('gerador');
        $req = $this->req('gerador', 'aterramento');
        $c = $this->cumprir($a, $req, 'reprovado');
        MaintenanceOrder::withoutGlobalScopes()->whereKey($c->fresh()->maintenance_order_id)->update(['status' => 'Concluída']);

        $this->assertSame(ResultadoAptidao::INAPTO, $this->motor->calcular($a->fresh())->status, 'OS fechada nao reinspeciona o item');

        $this->cumprir($a, $req, 'ok', ['realizado_em' => now()->addMinute()]);
        $r = $this->motor->calcular($a->fresh());
        $this->assertNotSame(ResultadoAptidao::INAPTO, $r->status);
        $this->assertNotContains('req:aterramento', array_column($r->motivos, 'codigo'), 'o item reinspecionado sai dos motivos');
    }

    // ============================================================ checklist do campo

    public function test_servico_de_checklist_registra_respostas_abre_os_e_resume(): void
    {
        $a = $this->ativo('gerador');
        $resp = [
            $this->req('gerador', 'oleo')->id => ['resultado' => 'ok'],
            $this->req('gerador', 'aterramento')->id => ['resultado' => 'reprovado', 'observacao' => 'sem terra'],
            $this->req('gerador', 'cabos-conexoes')->id => ['resultado' => 'ok'],
        ];

        $r = app(ChecklistAptidaoService::class)->registrar($a, $resp, $this->admin);

        $this->assertSame(3, $r['registrados']);
        $this->assertSame(['Aterramento'], $r['reprovados']);
        $this->assertCount(1, $r['os']);
        $this->assertSame('inapto', $r['status']);
        $this->assertSame(3, CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->where('origem', 'campo')->count());
        $this->assertSame((string) $this->admin->id, (string) CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $a->id)->first()->registrado_por);
    }

    public function test_checklist_so_aceita_itens_imp_alr_do_grupo_do_ativo(): void
    {
        $svc = app(ChecklistAptidaoService::class);
        $gerador = $this->ativo('gerador');
        $doGuindaste = $this->req('guindaste', 'freios');
        $laudo = $this->req('guindaste', 'laudo-periodico'); // INSP: nao e checklist

        foreach ([[$doGuindaste->id => ['resultado' => 'ok']], [$laudo->id => ['resultado' => 'ok']], []] as $resp) {
            try {
                $svc->registrar($gerador, $resp, $this->admin);
                $this->fail('Deveria recusar');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('respostas', $e->errors());
            }
        }

        $this->assertSame(0, CatalogoCumprimento::withoutGlobalScopes()->where('asset_id', $gerador->id)->count());
    }

    public function test_tela_do_campo_mostra_so_o_checklist_do_grupo_e_envia(): void
    {
        $a = $this->ativo('gerador');
        $tecnico = $this->usuario($this->tenant, permissoes: ['ler_catalogo_equipamentos']);
        $this->actingAs($tecnico);

        $c = Livewire::test(ChecklistAptidaoMobile::class)->call('escolher', $a->id);
        $c->assertSee('Aterramento')->assertDontSee('Plano de içamento')->assertDontSee('Cabos de aço');

        $oleo = $this->req('gerador', 'oleo');
        $aterr = $this->req('gerador', 'aterramento');
        $c->call('marcar', $oleo->id, 'ok')->call('marcar', $aterr->id, 'reprovado')->call('enviar')->assertSee('Aptidão: Inapto')->assertSee('OS aberta');

        $this->assertSame(1, MaintenanceOrder::withoutGlobalScopes()->where('asset_id', $a->id)->where('origin', 'aptidao_auto')->count());
    }

    public function test_tela_do_campo_exige_chave_ligada_e_permissao(): void
    {
        $this->actingAs($this->usuario($this->tenant)); // sem permissao
        Livewire::test(ChecklistAptidaoMobile::class)->assertForbidden();

        [$off, $adminOff] = $this->novoTenant(false);
        $this->actingAs($adminOff);
        Livewire::test(ChecklistAptidaoMobile::class)->assertForbidden(); // a policy ja nega sem a chave do contrato
    }

    public function test_atalho_do_campo_esta_configurado_com_rota_e_gate_pela_policy_do_catalogo(): void
    {
        $item = collect(config('campo.itens'))->firstWhere('chave', 'checklist_aptidao');

        $this->assertNotNull($item);
        $this->assertSame(CatalogoItem::class, $item['modelo']);
        $this->assertSame(['tecnico'], $item['perfis']);
        $this->assertStringContainsString('/admin/checklist-aptidao', route($item['rota']));
    }

    // ============================================================ paineis e aba do ativo

    public function test_painel_por_grupo_e_por_motivo(): void
    {
        $this->inapto();
        $this->inapto();
        $b = $this->ativo('gerador');
        $this->cumprir($b, $this->req('gerador', 'cabos-conexoes'), 'reprovado');
        $this->ativo('empilhadeira');
        Asset::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->get()->each(fn ($x) => $this->motor->recalcular($x));

        $this->assertTrue(AptidaoPorGrupoWidget::canView());
        $gerador = AssetCategory::query()->whereHas('assets')->withCount(['assets as inaptos_count' => fn ($q) => $q->where('aptidao_status', 'inapto')])->where('id', $this->g('gerador')->id)->first();
        $this->assertSame(2, $gerador->inaptos_count);
        Livewire::test(AptidaoPorGrupoWidget::class)->assertCanSeeTableRecords([$this->g('gerador'), $this->g('empilhadeira')]);

        $motivos = (new AptidaoMotivosWidget)->motivos();
        $this->assertSame(2, $motivos[0]['ativos'], 'o motivo mais frequente de Inapto vem primeiro');
        $this->assertStringContainsString('Aterramento', $motivos[0]['texto']);
    }

    public function test_paineis_somem_com_a_chave_desligada(): void
    {
        [$off, $adminOff] = $this->novoTenant(false);
        $this->actingAs($adminOff);

        $this->assertFalse(AptidaoPorGrupoWidget::canView());
        $this->assertFalse(AptidaoMotivosWidget::canView());
    }

    public function test_aba_do_ativo_libera_e_revoga_so_com_a_permissao(): void
    {
        $a = $this->inapto();
        $this->motor->recalcular($a);

        $t = Livewire::test(CumprimentosRelationManager::class, ['ownerRecord' => $a->fresh(), 'pageClass' => EditAsset::class]);
        $t->assertTableActionVisible('liberar')->assertTableActionHidden('revogar_liberacao')
            ->callTableAction('liberar', data: ['justificativa' => 'Liberacao pelo gestor responsavel', 'valida_ate' => now()->addDay()->toDateTimeString()])
            ->assertHasNoTableActionErrors();

        $this->assertNotNull(AptidaoLiberacao::vigente($a));

        $t2 = Livewire::test(CumprimentosRelationManager::class, ['ownerRecord' => $a->fresh(), 'pageClass' => EditAsset::class]);
        $t2->assertTableActionHidden('liberar')->assertTableActionVisible('revogar_liberacao')
            ->callTableAction('revogar_liberacao', data: ['justificativa' => 'Nao e mais necessario']);
        $this->assertNull(AptidaoLiberacao::vigente($a));

        // quem so le/gerencia o catalogo nao ve a acao
        $this->actingAs($this->usuario($this->tenant, permissoes: ['ler_catalogo_equipamentos', 'editar_catalogo_equipamentos']));
        Livewire::test(CumprimentosRelationManager::class, ['ownerRecord' => $a->fresh(), 'pageClass' => EditAsset::class])->assertTableActionHidden('liberar');
    }
}
