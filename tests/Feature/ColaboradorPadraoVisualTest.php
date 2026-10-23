<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Support\Colaborador\Modulos;
use Tests\Feature\Modulos\Detalhado\PerfisTestCase;

/**
 * Padrao visual do app do colaborador (/colaborador): cabecalho azul pelas secoes do layout,
 * barra de icones por funcao liberada e sem barra no login e na tela offline.
 * Rode com DB_DATABASE descartavel (ver PerfisTestCase).
 */
class ColaboradorPadraoVisualTest extends PerfisTestCase
{
    private const UA_CELULAR = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private function comAppDoColaborador(): void
    {
        $plan = Plan::withoutGlobalScopes()->find($this->tenantA->plan_id);
        $plan->forceFill(['features' => array_values(array_unique(array_merge((array) $plan->features, [Modulos::FEATURE])))])->save();
        $this->tenantA->refresh();
    }

    private function abrir(string $url, string $perfil = 'admin')
    {
        return $this->actingAs($this->a[$perfil])->withHeader('User-Agent', self::UA_CELULAR)->get($url);
    }

    public function test_inicio_mostra_cabecalho_azul_e_barra_de_icones(): void
    {
        $this->comAppDoColaborador();

        $this->abrir('/colaborador')->assertOk()
            ->assertSee('from-blue-600', false)
            ->assertSee('Navegação principal', false)
            ->assertSee('Início');
    }

    public function test_tela_interna_usa_o_titulo_do_cabecalho_azul_e_o_voltar(): void
    {
        $this->comAppDoColaborador();

        $resposta = $this->abrir('/colaborador/logistica')->assertOk();

        $resposta->assertSee('from-blue-600', false)->assertSee('← Início', false)->assertSee('Logística');
    }

    public function test_barra_de_icones_so_mostra_funcoes_que_a_pessoa_pode_usar(): void
    {
        $this->comAppDoColaborador();
        $permitidos = array_column(Modulos::para($this->a['admin']), 'chave');
        $this->assertContains('ponto', $permitidos);

        $html = $this->abrir('/colaborador')->getContent();
        $this->assertStringContainsString('aria-label="Navegação principal"', $html);
        $this->assertStringContainsString('Ponto', $html);

        // colaborador sem nenhuma permissao: so o Inicio na barra (nenhuma funcao liberada)
        $semPermissao = array_column(Modulos::para($this->a['colaborador']), 'chave');
        $this->assertNotContains('ponto', $semPermissao);
    }

    public function test_login_e_tela_offline_nao_mostram_a_barra_de_icones(): void
    {
        $this->withHeader('User-Agent', self::UA_CELULAR)->get('/colaborador/entrar')->assertOk()
            ->assertDontSee('Navegação principal', false);

        $this->withHeader('User-Agent', self::UA_CELULAR)->get('/colaborador/offline')->assertOk()
            ->assertDontSee('Navegação principal', false);
    }

    public function test_css_do_modo_offline_esta_no_tema_claro(): void
    {
        $js = file_get_contents(public_path('js/colab-offline.js'));

        $this->assertStringContainsString('background:#2563eb', $js, 'botao azul do padrao');
        $this->assertStringNotContainsString('color:#f4f4f5', $js, 'texto claro sobre fundo claro nao pode voltar');
        $this->assertStringNotContainsString('background:#18181b', $js, 'cartao escuro nao pode voltar');
    }
}
