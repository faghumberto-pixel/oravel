<?php

namespace Tests\Feature;

use App\Filament\Pages\AtualizacoesCatalogo;
use App\Filament\Resources\CatalogoGrupoResource\Pages\ListCatalogoGrupos;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\CatalogoCumprimento;
use App\Models\CatalogoItem;
use App\Models\CatalogoPreventiva;
use App\Models\CatalogoRequisito;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Catalogo\CatalogoInstalador;
use App\Services\Catalogo\CatalogoTemplateService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * G1 fase 6: atualizacao de template item a item, ignorar, restaurar padrao (item/grupo), previa de impacto.
 * SEGURANCA: so roda em banco com "test" no nome.
 */
class G1AtualizacaoTemplateTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    private CatalogoTemplateService $svc;

    /** @var array<int, string> */
    private array $dirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! str_contains((string) DB::connection()->getDatabaseName(), 'test')) {
            $this->markTestSkipped('Recusado: banco nao parece descartavel (nome sem "test").');
        }

        $this->svc = app(CatalogoTemplateService::class);
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [CatalogoItem::FEATURE_KEY => true, 'tabela_assets' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'g1t-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = $this->usuario(true);
        app(CatalogoInstalador::class)->instalar($this->tenant);
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        foreach ($this->dirs as $d) {
            array_map('unlink', glob($d.'/*') ?: []);
            @rmdir($d);
        }

        parent::tearDown();
    }

    private function usuario(bool $admin = false, array $permissoes = []): User
    {
        $u = User::create(['name' => 'U', 'email' => 'u-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $u->forceFill(['email_verified_at' => now()])->save();

        if ($admin) {
            $u->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        }

        foreach ($permissoes as $p) {
            $u->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }

        return $u;
    }

    /** Copia o template real para um diretorio temporario e aplica a mutacao no compressor.yaml (e na biblioteca). */
    private function template(?callable $compressor = null, ?callable $biblioteca = null, array $extras = []): string
    {
        $dir = sys_get_temp_dir().'/g1tpl_'.uniqid();
        mkdir($dir);
        $this->dirs[] = $dir;

        foreach (glob(CatalogoInstalador::diretorio().'/*.yaml') as $f) {
            copy($f, $dir.'/'.basename($f));
        }

        if ($compressor) {
            $d = Yaml::parseFile($dir.'/compressor.yaml');
            $d['versao'] = 2;
            $compressor($d);
            file_put_contents($dir.'/compressor.yaml', Yaml::dump($d, 8, 2));
        }

        if ($biblioteca) {
            $b = Yaml::parseFile($dir.'/_biblioteca.yaml');
            $biblioteca($b);
            file_put_contents($dir.'/_biblioteca.yaml', Yaml::dump($b, 8, 2));
        }

        foreach ($extras as $nome => $conteudo) {
            file_put_contents($dir.'/'.$nome, $conteudo);
        }

        return $dir;
    }

    /** Template v2 padrao dos testes: muda varios itens do compressor (sensivel e nao sensivel), adiciona e remove. */
    private function v2(): string
    {
        return $this->template(function (array &$d) {
            foreach ($d['requisitos'] as &$r) {
                if ($r['slug'] === 'oleo') {
                    $r['efeito'] = 'ALR';            // IMP -> ALR: SENSIVEL
                    unset($r['gera_os']);
                }
                if ($r['slug'] === 'vazamentos-de-ar') {
                    $r['efeito'] = 'IMP';            // endurece: nao sensivel
                }
            }
            unset($r);
            $d['requisitos'] = array_values(array_filter($d['requisitos'], fn ($r) => $r['slug'] !== 'mangueiras-engates')); // saiu do padrao
            $d['requisitos'][] = ['slug' => 'seguro', 'item' => 'seguro', 'efeito' => 'DOC'];                                // novo
            foreach ($d['preventivas'] as &$p) {
                if ($p['slug'] === 'revisao-500h') {
                    $p['intervalo'] = 600;           // amplia: SENSIVEL
                }
            }
            unset($p);
            $d['atributos'][] = ['slug' => 'ruido', 'rotulo' => 'Nivel de ruido', 'tipo' => 'numero', 'unidade' => 'dB', 'obrigatorio' => false];
        }, function (array &$b) {
            foreach ($b['itens'] as &$i) {
                if ($i['slug'] === 'manometro') {
                    $i['nome'] = 'Manometro (indicador de pressao)';
                }
            }
        });
    }

    private function g(string $slug = 'compressor'): AssetCategory
    {
        return AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', $slug)->firstOrFail();
    }

    private function req(string $slug): CatalogoRequisito
    {
        return CatalogoRequisito::withoutGlobalScopes()->withTrashed()->where('grupo_id', $this->g()->id)->where('slug', $slug)->firstOrFail();
    }

    private function prev(string $slug): CatalogoPreventiva
    {
        return CatalogoPreventiva::withoutGlobalScopes()->where('grupo_id', $this->g()->id)->where('slug', $slug)->firstOrFail();
    }

    private function diff(?string $dir = null): array
    {
        return $this->svc->diferencas($this->tenant, $dir);
    }

    // ============================================================ diff

    public function test_catalogo_recem_instalado_nao_tem_diferencas(): void
    {
        $this->assertSame([], $this->diff());
        $this->assertSame(0, $this->svc->pendentes($this->tenant));
    }

    public function test_template_novo_gera_diff_item_a_item_sem_aplicar_nada(): void
    {
        $dir = $this->v2();
        $antes = [$this->req('oleo')->only(['efeito', 'gera_os']), $this->prev('revisao-500h')->intervalo];

        $d = $this->diff($dir);

        $this->assertSame('atualizar', $d['requisitos:compressor:oleo']['acao']);
        $this->assertSame(['atual' => 'IMP', 'padrao' => 'ALR'], $d['requisitos:compressor:oleo']['campos']['efeito']);
        $this->assertSame('atualizar', $d['requisitos:compressor:vazamentos-de-ar']['acao']);
        $this->assertSame('atualizar', $d['preventivas:compressor:revisao-500h']['acao']);
        $this->assertSame('novo', $d['requisitos:compressor:seguro']['acao']);
        $this->assertSame('novo', $d['atributos:compressor:ruido']['acao']);
        $this->assertSame('atualizar', $d['item::manometro']['acao']);
        $this->assertSame('removido_do_padrao', $d['requisitos:compressor:mangueiras-engates']['acao']);
        $this->assertArrayNotHasKey('grupo:compressor:compressor', $d, 'so a versao mudou: o perfil do grupo e igual');

        $this->assertSame($antes, [$this->req('oleo')->only(['efeito', 'gera_os']), $this->prev('revisao-500h')->intervalo], 'diff nao grava nada');
        $this->assertNotNull($this->req('mangueiras-engates'));
    }

    public function test_grupo_novo_no_template_aparece_e_so_instala_ao_aceitar(): void
    {
        $dir = $this->template(extras: ['betoneira.yaml' => <<<'YAML'
versao: 1
grupo: {slug: betoneira, nome: Betoneira, medidor: horimetro, criticidade_padrao: baixa, exige_operador: false, ordem: 90}
atributos: []
requisitos:
  - {slug: freios, item: freios, efeito: IMP, gera_os: true}
preventivas: []
operadores: []
pecas_criticas: []
sla: []
regras: []
YAML]);

        $d = $this->diff($dir);
        $this->assertSame('grupo_novo', $d['grupo_novo::betoneira']['acao']);
        $this->assertSame(0, AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'betoneira')->count());

        $this->svc->aceitar($this->tenant, ['grupo_novo::betoneira'], null, $this->admin, $dir);

        $this->assertSame(1, AssetCategory::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('slug', 'betoneira')->count());
        $this->assertSame([], array_filter($this->diff($dir), fn ($e) => $e['acao'] === 'grupo_novo'));
    }

    public function test_reordenar_grupo_nao_vira_diferenca(): void
    {
        $this->g()->update(['ordem' => 999]);

        $this->assertSame([], $this->diff());
    }

    // ============================================================ aceitar

    public function test_aceitar_aplica_so_o_que_foi_marcado_e_mantem_nao_personalizado(): void
    {
        $dir = $this->v2();

        $r = $this->svc->aceitar($this->tenant, ['requisitos:compressor:vazamentos-de-ar', 'requisitos:compressor:seguro'], null, $this->admin, $dir);

        $this->assertSame(2, $r['aplicadas']);
        $v = $this->req('vazamentos-de-ar');
        $this->assertSame('IMP', $v->efeito);
        $this->assertFalse($v->personalizado, 'aceitar o padrao nao o torna personalizado');
        $this->assertSame(2, $v->versao_template);
        $this->assertSame('DOC', $this->req('seguro')->efeito);
        $this->assertSame('sistema', $this->req('seguro')->origem);
        // o que nao foi marcado fica como estava
        $this->assertSame('IMP', $this->req('oleo')->efeito);
        $this->assertSame(500, $this->prev('revisao-500h')->intervalo);
    }

    public function test_atualizacao_sensivel_exige_justificativa_e_nao_aplica_nada_sem_ela(): void
    {
        $dir = $this->v2();
        $chaves = ['requisitos:compressor:vazamentos-de-ar', 'requisitos:compressor:oleo', 'preventivas:compressor:revisao-500h'];

        try {
            $this->svc->aceitar($this->tenant, $chaves, null, $this->admin, $dir);
            $this->fail('IMP->ALR e ampliar intervalo exigem justificativa');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('justificativa', $e->errors());
        }

        $this->assertSame('ALR', $this->req('vazamentos-de-ar')->efeito, 'atomico: nem o item nao sensivel foi aplicado');
        $this->assertSame('IMP', $this->req('oleo')->efeito);

        $r = $this->svc->aceitar($this->tenant, $chaves, 'Fabricante revisou o manual (rev. 2)', $this->admin, $dir);

        $this->assertCount(2, $r['sensiveis']);
        $this->assertSame('ALR', $this->req('oleo')->efeito);
        $this->assertSame(600, $this->prev('revisao-500h')->intervalo);
        $log = Activity::query()->where('description', 'Catalogo: atualizacao de template aceita')->latest()->first();
        $this->assertSame('Fabricante revisou o manual (rev. 2)', $log->properties['justificativa']);
        $this->assertCount(2, $log->properties['mudancas_sensiveis']);
        $this->assertSame((string) $this->admin->id, (string) $log->causer_id);
    }

    public function test_item_personalizado_nunca_e_alterado_por_aceite(): void
    {
        $dir = $this->v2();
        $this->prev('revisao-500h')->update(['intervalo' => 450]); // tenant personaliza (hook marca personalizado)
        $this->assertTrue($this->prev('revisao-500h')->personalizado);

        $d = $this->diff($dir);
        $this->assertSame('personalizado', $d['preventivas:compressor:revisao-500h']['acao']);
        $this->assertNotContains('preventivas:compressor:revisao-500h', array_keys(array_filter($d, fn ($e) => in_array($e['acao'], CatalogoTemplateService::ACOES_ACEITAVEIS, true))));

        try {
            $this->svc->aceitar($this->tenant, ['preventivas:compressor:revisao-500h'], 'tentando aceitar um personalizado', $this->admin, $dir);
            $this->fail('Personalizado nao pode ser aceito');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('chaves', $e->errors());
        }

        // aceitar outros itens tambem nao encosta nele
        $this->svc->aceitar($this->tenant, ['requisitos:compressor:vazamentos-de-ar'], null, $this->admin, $dir);
        $this->assertSame(450, $this->prev('revisao-500h')->intervalo);
    }

    public function test_item_arquivado_pelo_tenant_nao_volta_por_aceite_nem_por_reinstalar(): void
    {
        $this->req('oleo')->delete(); // tenant arquivou
        $dir = $this->v2();

        $d = $this->diff($dir);
        $this->assertSame('arquivado', $d['requisitos:compressor:oleo']['acao']);

        try {
            $this->svc->aceitar($this->tenant, ['requisitos:compressor:oleo'], 'x tentativa', $this->admin, $dir);
            $this->fail('arquivado nao e aceitavel');
        } catch (ValidationException) {
        }

        app(CatalogoInstalador::class)->instalar($this->tenant, $dir);
        $this->assertTrue($this->req('oleo')->trashed());
    }

    public function test_ignorar_mantem_a_versao_do_tenant_e_para_de_oferecer(): void
    {
        $dir = $this->v2();

        $this->svc->ignorar($this->tenant, ['requisitos:compressor:vazamentos-de-ar'], $this->admin, $dir);

        $v = $this->req('vazamentos-de-ar');
        $this->assertSame('ALR', $v->efeito);
        $this->assertTrue($v->personalizado);
        $this->assertSame('personalizado', $this->diff($dir)['requisitos:compressor:vazamentos-de-ar']['acao']);
        $this->assertSame('Catalogo: atualizacao de template ignorada', Activity::query()->latest()->first()->description);

        $this->expectException(ValidationException::class);
        $this->svc->ignorar($this->tenant, ['requisitos:compressor:seguro'], $this->admin, $dir); // novo nao se ignora
    }

    public function test_itens_removidos_do_padrao_ficam_no_catalogo_do_tenant(): void
    {
        $dir = $this->v2();

        $this->assertSame('removido_do_padrao', $this->diff($dir)['requisitos:compressor:mangueiras-engates']['acao']);
        $this->svc->aceitar($this->tenant, array_keys(array_filter($this->diff($dir), fn ($e) => in_array($e['acao'], CatalogoTemplateService::ACOES_ACEITAVEIS, true))), 'aceitando tudo do v2', $this->admin, $dir);

        $this->assertFalse($this->req('mangueiras-engates')->trashed());
    }

    // ============================================================ restaurar padrao

    public function test_restaurar_item_volta_ao_padrao_com_justificativa_e_log(): void
    {
        $this->req('oleo')->update(['efeito' => 'ALR', 'validade_dias' => 5]);
        $chave = 'requisitos:compressor:oleo';

        try {
            $this->svc->restaurar($this->tenant, [$chave], '', $this->admin);
            $this->fail('restaurar exige justificativa');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('justificativa', $e->errors());
        }
        $this->assertSame('ALR', $this->req('oleo')->efeito);

        $this->svc->restaurar($this->tenant, [$chave], 'Voltando ao padrao do fabricante', $this->admin);

        $r = $this->req('oleo');
        $this->assertSame('IMP', $r->efeito);
        $this->assertNull($r->validade_dias);
        $this->assertFalse($r->personalizado);
        $log = Activity::query()->where('description', 'Catalogo: restaurado ao padrao')->latest()->first();
        $this->assertSame('Voltando ao padrao do fabricante', $log->properties['justificativa']);
        $this->assertSame('ALR', $log->properties['itens'][0]['campos']['efeito']['atual']);
    }

    public function test_restaurar_item_arquivado_reativa(): void
    {
        $this->req('oleo')->update(['ativo' => false]);
        $this->req('oleo')->delete();

        $this->svc->restaurar($this->tenant, ['requisitos:compressor:oleo'], 'Reativando o item padrao', $this->admin);

        $r = $this->req('oleo');
        $this->assertFalse($r->trashed());
        $this->assertTrue($r->ativo);
    }

    public function test_restaurar_grupo_repoe_o_padrao_e_preserva_o_que_o_tenant_criou(): void
    {
        $g = $this->g();
        $g->update(['criticidade_padrao' => 'baixa', 'name' => 'Compressor nosso']);
        $this->req('oleo')->update(['efeito' => 'ALR']);
        $this->prev('revisao-1000h')->update(['ativo' => false]);
        $this->req('dreno-reservatorio')->delete();
        $meu = CatalogoRequisito::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'grupo_id' => $g->id, 'item_id' => $this->req('oleo')->item_id,
            'slug' => 'meu-requisito', 'efeito' => 'ALR', 'origem' => 'tenant', 'personalizado' => true, 'ativo' => true]);
        $g->update(['ordem' => 77]);

        $this->svc->restaurarGrupo($this->tenant, $g->fresh(), 'Padronizar com a matriz', $this->admin);

        $g = $g->fresh();
        $this->assertSame('media', $g->criticidade_padrao);
        $this->assertSame('Compressor', $g->name);
        $this->assertFalse($g->personalizado);
        $this->assertSame(77, (int) $g->ordem, 'a ordem escolhida pelo tenant e preservada');
        $this->assertSame('IMP', $this->req('oleo')->efeito);
        $this->assertTrue($this->prev('revisao-1000h')->ativo);
        $this->assertFalse($this->req('dreno-reservatorio')->trashed());
        $this->assertSame('ALR', $meu->fresh()->efeito, 'o que o tenant criou fica intacto');
        $this->assertSame([], array_filter($this->diff(), fn ($e) => $e['grupo'] === 'compressor' && $e['acao'] !== 'removido_do_padrao'));
    }

    public function test_nao_restaura_grupo_criado_pelo_tenant(): void
    {
        $meu = AssetCategory::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Meu grupo', 'slug' => 'meu-grupo', 'is_active' => true, 'origem' => 'tenant']);

        $this->expectException(ValidationException::class);
        $this->svc->restaurarGrupo($this->tenant, $meu, 'nao ha padrao para restaurar', $this->admin);
    }

    // ============================================================ impacto e recalculo

    public function test_previa_de_impacto_simula_sem_gravar(): void
    {
        $dir = $this->v2();
        $a = Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => $this->g()->id, 'horimetro_atual' => 10]);
        CatalogoCumprimento::create(['tenant_id' => $this->tenant->id, 'asset_id' => $a->id, 'requisito_id' => $this->req('vazamentos-de-ar')->id, 'resultado' => 'reprovado', 'realizado_em' => now()]);
        $logs = Activity::query()->count();

        $p = $this->svc->previa($this->tenant, ['requisitos:compressor:vazamentos-de-ar'], 'aceitar', $dir);

        $this->assertSame(1, $p['total']);
        $this->assertSame(1, $p['mudaram']);
        $this->assertSame(['apto_restricao>inapto' => 1], $p['transicoes']);
        $this->assertSame('ALR', $this->req('vazamentos-de-ar')->efeito, 'previa nao grava');
        $this->assertSame($logs, Activity::query()->count(), 'previa nao gera log');
        $this->assertSame(1, $this->req('vazamentos-de-ar')->versao_template);
    }

    public function test_aceitar_recalcula_a_aptidao_dos_ativos_do_grupo(): void
    {
        $dir = $this->v2();
        $a = Asset::factory()->create(['tenant_id' => $this->tenant->id, 'asset_category_id' => $this->g()->id, 'horimetro_atual' => 10]);
        CatalogoCumprimento::create(['tenant_id' => $this->tenant->id, 'asset_id' => $a->id, 'requisito_id' => $this->req('vazamentos-de-ar')->id, 'resultado' => 'reprovado', 'realizado_em' => now()]);
        $this->assertSame('apto_restricao', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);

        $this->svc->aceitar($this->tenant, ['requisitos:compressor:vazamentos-de-ar'], null, $this->admin, $dir);

        $this->assertSame('inapto', Asset::withoutGlobalScopes()->find($a->id)->aptidao_status);
    }

    public function test_isolamento_entre_tenants(): void
    {
        $plano = Plan::create(['name' => 'P2 '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [CatalogoItem::FEATURE_KEY => true]]);
        $outro = Tenant::create(['name' => 'T2 '.uniqid(), 'slug' => 'g1t2-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        app(CatalogoInstalador::class)->instalar($outro);
        $dir = $this->v2();

        $this->svc->aceitar($this->tenant, ['requisitos:compressor:vazamentos-de-ar'], null, $this->admin, $dir);

        $alheio = CatalogoRequisito::withoutGlobalScopes()->where('tenant_id', $outro->id)->where('slug', 'vazamentos-de-ar')->firstOrFail();
        $this->assertSame('ALR', $alheio->efeito, 'o aceite de um tenant nao toca o de outro');
        $this->assertSame('atualizar', $this->svc->diferencas($outro, $dir)['requisitos:compressor:vazamentos-de-ar']['acao']);
    }

    // ============================================================ telas

    public function test_pagina_lista_personalizados_e_restaura_item_pela_tela(): void
    {
        $this->req('oleo')->update(['efeito' => 'ALR']);
        $chave = 'requisitos:compressor:oleo';

        Livewire::test(AtualizacoesCatalogo::class)
            ->assertSee('Personalizados com versão nova do padrão')->assertSee('Nivel/condicao do oleo')->assertSee('Restaurar ao padrão')
            ->callAction('restaurar', data: ['justificativa' => 'Voltar ao padrao pela tela'], arguments: ['chave' => $chave])
            ->assertHasNoActionErrors();

        $this->assertSame('IMP', $this->req('oleo')->efeito);
    }

    public function test_pagina_aceita_atualizacao_marcada_pela_tela(): void
    {
        // tenant com um item "desatualizado" em relacao ao padrao real, sem ser personalizado (update sem eventos)
        CatalogoPreventiva::withoutGlobalScopes()->whereKey($this->prev('revisao-500h')->id)->update(['intervalo' => 400]);

        Livewire::test(AtualizacoesCatalogo::class)
            ->assertSee('Atualizações disponíveis')->assertSee('Alterado no padrão')
            ->set('selecionadas', ['preventivas:compressor:revisao-500h'])
            ->callAction('aceitar', data: ['justificativa' => 'Adotando o padrao atual'])
            ->assertHasNoActionErrors();

        $this->assertSame(500, $this->prev('revisao-500h')->intervalo);
    }

    public function test_pagina_nao_oferece_acoes_a_quem_so_le(): void
    {
        $this->req('oleo')->update(['efeito' => 'ALR']);
        $this->actingAs($this->usuario(permissoes: ['ler_catalogo_equipamentos']));

        Livewire::test(AtualizacoesCatalogo::class)->assertSee('Nivel/condicao do oleo')->assertDontSee('Restaurar ao padrão');
    }

    public function test_acao_restaurar_padrao_do_grupo_na_lista(): void
    {
        $this->g()->update(['criticidade_padrao' => 'baixa']);

        Livewire::test(ListCatalogoGrupos::class)
            ->callTableAction('restaurar_padrao', $this->g(), data: ['justificativa' => 'Padronizar com a matriz'])
            ->assertHasNoTableActionErrors();

        $this->assertSame('media', $this->g()->criticidade_padrao);

        $this->actingAs($this->usuario(permissoes: ['ler_catalogo_equipamentos', 'editar_catalogo_equipamentos']));
        // quem gerencia o catalogo ve; quem so le nao
        $this->actingAs($this->usuario(permissoes: ['ler_catalogo_equipamentos']));
        Livewire::test(ListCatalogoGrupos::class)->assertTableActionHidden('restaurar_padrao', $this->g());
    }
}
