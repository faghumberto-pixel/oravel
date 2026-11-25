<?php

namespace Tests\Feature;

use App\Console\Commands\SiteIntelligenceGerar;
use App\Domain\Intelligence\Models\IntelligenceTestimonial;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Filament\Resources\IntelligenceImportResource;
use App\Support\IntelligenceSite\Exemplo;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use ZipArchive;

/**
 * Página oravel.com.br/intelligence (HTML estático gerado de Blade + config). Os testes lêem o arquivo VERSIONADO em
 * public_html/intelligence, que é o que vai para o site.
 */
class IntelligenceSitePaginaTest extends TestCase
{
    use DatabaseTransactions;

    private const PASTA = 'public_html/intelligence';

    private function html(): string
    {
        return file_get_contents(base_path(self::PASTA.'/index.html'));
    }

    private function xpath(?string $html = null): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.($html ?? $this->html()));
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    // ---- a página ----

    public function test_arquivo_versionado_esta_em_dia_com_o_config(): void
    {
        $this->assertSame(0, Artisan::call('site:intelligence', ['--check' => true]), 'rode: php artisan site:intelligence');
    }

    public function test_pagina_tem_titulo_descricao_e_open_graph_para_compartilhar(): void
    {
        $h = $this->html();
        $this->assertStringContainsString('Veja onde e como sua locadora pode melhorar.', $h);
        foreach (['<title>', 'name="description"', 'rel="canonical"', 'property="og:title"', 'property="og:description"', 'property="og:image"', 'name="twitter:card"'] as $tag) {
            $this->assertStringContainsString($tag, $h, "falta $tag");
        }
        $this->assertFileExists(base_path(self::PASTA.'/'.SiteIntelligenceGerar::ARQUIVO_OG));
        [$w, $alt] = getimagesize(base_path(self::PASTA.'/'.SiteIntelligenceGerar::ARQUIVO_OG));
        $this->assertSame([1200, 630], [$w, $alt]);
    }

    public function test_so_existe_um_destino_de_cta_e_o_texto_e_o_mesmo(): void
    {
        $x = $this->xpath();
        $ctas = $x->query('//*[@data-cta]');
        $this->assertGreaterThanOrEqual(3, $ctas->length, 'topo, rodapé fixo e final');

        $destinos = [];
        foreach ($ctas as $c) {
            $destinos[] = $c->getAttribute('href');
            $this->assertSame('Enviar minha planilha', trim($c->textContent));
        }
        $this->assertCount(1, array_unique($destinos), 'todos os CTAs têm de apontar para o mesmo lugar');
        $this->assertSame(rtrim(config('intelligence_site.app_url'), '/').'/intelligence/teste-gratis', $destinos[0]);

        // e o formulário do modal envia para esse mesmo destino
        $this->assertSame($destinos[0], $x->query('//dialog//form')->item(0)->getAttribute('action'));
    }

    public function test_nenhum_link_ou_ancora_quebrado(): void
    {
        $x = $this->xpath();
        $ids = [];
        foreach ($x->query('//*[@id]') as $n) {
            $ids[] = $n->getAttribute('id');
        }
        $site = rtrim(config('intelligence_site.site_url'), '/');
        $app = rtrim(config('intelligence_site.app_url'), '/');
        $checados = 0;

        foreach ($x->query('//a[@href]|//link[@href][@rel!="stylesheet" and @rel!="preconnect" and @rel!="canonical"]|//script[@src]') as $n) {
            $href = $n->getAttribute($n->hasAttribute('href') ? 'href' : 'src');
            $checados++;

            if (str_starts_with($href, '#')) {
                $this->assertContains(substr($href, 1), $ids, "âncora sem destino: $href");

                continue;
            }
            if (str_starts_with($href, $app) || str_starts_with($href, 'https://fonts.')) {
                continue; // app: coberto por teste de rota; fontes: externo
            }
            $caminho = parse_url(str_starts_with($href, $site) ? substr($href, strlen($site)) : $href, PHP_URL_PATH);
            $this->assertNotNull($caminho, "link ilegível: $href");
            $this->assertTrue(! str_starts_with($href, 'http') || str_starts_with($href, $site), "link externo inesperado: $href");

            $base = str_starts_with($caminho, '/') ? base_path('public_html'.$caminho) : base_path(self::PASTA.'/'.$caminho);
            $existe = is_file($base) || is_file(rtrim($base, '/').'/index.html') || is_file(rtrim($base, '/').'.html') || is_file(rtrim($base, '/').'.php');
            $this->assertTrue($existe, "link quebrado: $href");
        }
        $this->assertGreaterThan(8, $checados);
    }

    public function test_rota_do_cadastro_existe_no_app(): void
    {
        $this->assertSame(route('intelligence.trial.store', [], false), '/intelligence/teste-gratis');
        $this->get('/intelligence/teste-gratis')->assertOk();
    }

    public function test_sem_whatsapp_chat_nem_textos_proibidos(): void
    {
        $h = $this->html();
        foreach (['wa.me', 'whatsapp.com/send', 'Online agora', 'wa-chat', '#contato', '/contato.php', 'Sem cadastro', 'sem cadastro', 'Quem fez', 'Humberto'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $h, "não pode aparecer: $proibido");
        }
        $this->assertStringContainsString('Leva 1 minuto. Sem senha e sem cartão.', $h);
    }

    public function test_resposta_de_seguranca_fala_de_nuvem_e_backup_sem_citar_outros_usuarios(): void
    {
        $x = $this->xpath();
        $r = $x->query('//details[summary[normalize-space()="Meus dados ficam seguros?"]]/p')->item(0)->textContent;

        foreach (['Google Cloud', 'São Paulo', 'HTTPS', 'backup diário', '30 dias', 'snapshot diário'] as $deve) {
            $this->assertStringContainsString($deve, $r);
        }
        // não levanta a hipótese de "outros usuários verem os dados"
        foreach (['outra locadora', 'outras locadoras', 'outros usuários', 'nenhuma outra', 'só a sua empresa'] as $nao) {
            $this->assertStringNotContainsStringIgnoringCase($nao, $r, "não deve citar: $nao");
        }
    }

    public function test_tem_as_seis_secoes_pedidas_e_um_unico_h1(): void
    {
        $x = $this->xpath();
        $this->assertSame(1, $x->query('//h1')->length);
        foreach (['O que você descobre', 'Como funciona', 'O que vem na leitura', 'Preço e confiança', 'Perguntas frequentes'] as $titulo) {
            $this->assertGreaterThan(0, $x->query('//h2[normalize-space()="'.$titulo.'"]')->length, "falta a seção $titulo");
        }
        $this->assertSame(5, $x->query('//details')->length, 'FAQ com 5 perguntas');
    }

    // ---- números: fonte única ----

    public function test_numeros_do_exemplo_vem_so_do_dataset(): void
    {
        $ex = Exemplo::doConfig();
        $h = $this->html();

        // o que a página mostra é exatamente o que o dataset calcula
        foreach ([
            (string) $ex->indice('out'),
            Exemplo::delta($ex->indiceVariacao()).' em dois meses',
            Exemplo::pct($ex->disponibilidade('out')),
            Exemplo::delta($ex->disponibilidadePontos()).' pontos',
            Exemplo::delta($ex->atrasoPontos(), 1).' pontos',   // −2,7
            Exemplo::brl($ex->custoParadoMes()),
            $ex->parados().' de '.$ex->frota(),
        ] as $esperado) {
            $this->assertStringContainsString($esperado, $h, "esperava $esperado");
        }
        $this->assertSame('+16', Exemplo::delta($ex->indiceVariacao()));
        $this->assertSame('+13', Exemplo::delta($ex->disponibilidadePontos()));
        $this->assertSame('−2,7', Exemplo::delta($ex->atrasoPontos(), 1));
        $this->assertSame('+7', Exemplo::delta($ex->oficinaVariacao()));

        // números da página antiga, que contradiziam o dataset
        foreach (['R$ 52 mil', '78,3', '90,9', '11,1', '54 para 48', '468.000'] as $velho) {
            $this->assertStringNotContainsString($velho, $h);
        }
    }

    public function test_mudar_o_dataset_muda_todos_os_numeros_da_pagina(): void
    {
        config(['intelligence_site.exemplo.indice.out' => 83, 'intelligence_site.exemplo.disponibilidade.out' => 93, 'intelligence_site.exemplo.atraso_pct.out' => 3.1]);
        $h = SiteIntelligenceGerar::renderizar();

        $this->assertStringContainsString('+22 em dois meses', $h);    // 83 - 61
        $this->assertStringContainsString('+15 pontos', $h);           // 93 - 78
        $this->assertStringContainsString('−3,5 pontos', $h);          // 3,1 - 6,6
        $this->assertStringNotContainsString('+16 em dois meses', $h);
        $this->assertStringNotContainsString('+13 pontos', $h);
        $this->assertStringNotContainsString('−2,7', $h);
        // 122 - round(122 * 0,93) = 9 equipamentos parados; 9 x 380 x 18
        $this->assertStringContainsString('9 de 122', $h);
        $this->assertStringContainsString(Exemplo::brl(9 * 380 * 18), $h);
    }

    public function test_preco_e_prazo_vem_do_config_e_nao_de_texto_digitado(): void
    {
        $this->assertStringContainsString('R$ 39,90/mês', $this->html());

        config(['intelligence_trial.plano.preco' => 49.9, 'intelligence_trial.dias' => 45]);
        $h = SiteIntelligenceGerar::renderizar();
        $this->assertStringContainsString('R$ 49,90/mês', $h);
        $this->assertStringContainsString('começa 45 dias após', $h);
        $this->assertStringNotContainsString('R$ 39,90', $h);
    }

    public function test_calculadora_abre_com_valores_de_exemplo_e_nunca_zerada(): void
    {
        $ex = Exemplo::doConfig();
        $x = $this->xpath();
        $this->assertSame((string) $ex->parados(), $x->query('//input[@id="c-qtd"]')->item(0)->getAttribute('value'));
        $this->assertSame((string) $ex->diaria(), $x->query('//input[@id="c-diaria"]')->item(0)->getAttribute('value'));
        $this->assertSame(Exemplo::brl($ex->custoParadoMes()), trim($x->query('//*[@id="c-mes"]')->item(0)->textContent));
        $this->assertStringNotContainsString('R$ 0<', $this->html());
        $this->assertSame(1, $x->query('//*[@class="calc"]')->length, 'só uma calculadora');
    }

    public function test_todo_exemplo_aparece_com_o_rotulo_ilustrativo(): void
    {
        $rotulo = config('intelligence_site.rotulo_exemplo');
        $this->assertSame('Exemplo ilustrativo, com dados fictícios', $rotulo);
        $x = $this->xpath();

        $mocks = $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " mock ")]');
        $this->assertGreaterThanOrEqual(6, $mocks->length);
        foreach ($mocks as $m) {
            $leg = $x->query('.//*[contains(@class,"ilustrativo")]', $m);
            $this->assertSame(1, $leg->length, 'tela de exemplo sem rótulo');
            $this->assertSame($rotulo, trim($leg->item(0)->textContent));
            $this->assertNotSame('', $x->query('.//*[@role="img"]/@aria-label', $m)->item(0)?->nodeValue ?? '', 'tela de exemplo sem texto alternativo');
        }
        $this->assertStringContainsString($rotulo, $x->query('//*[@class="res"]')->item(0)->textContent, 'a calculadora também é exemplo');
    }

    // ---- modelo da planilha ----

    public function test_link_do_modelo_baixa_um_xlsx_valido_com_as_colunas_do_sistema(): void
    {
        $x = $this->xpath();
        $links = $x->query('//a[contains(@href,".xlsx")]');
        $this->assertGreaterThan(0, $links->length);
        $arquivo = base_path(self::PASTA.'/'.$links->item(0)->getAttribute('href'));
        $this->assertFileExists($arquivo);
        $this->assertTrue($links->item(0)->hasAttribute('download'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($arquivo) === true, 'não é um zip/xlsx válido');
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $zip->close();

        $this->assertTrue(SiteIntelligenceGerar::modeloEmDia($arquivo), 'o modelo versionado não bate com as colunas do Catalogo: rode php artisan site:intelligence --forcar');
    }

    // ---- depoimentos ----

    public function test_sem_depoimento_aprovado_nao_aparece_nada(): void
    {
        IntelligenceTestimonial::query()->delete();

        $this->getJson('/api/intelligence/depoimentos')->assertOk()->assertExactJson([]);

        $x = $this->xpath();
        $sec = $x->query('//*[@id="depoimentos"]')->item(0);
        $this->assertNotNull($sec);
        $this->assertTrue($sec->hasAttribute('hidden'), 'a seção nasce oculta');
        $this->assertSame('', trim($sec->textContent), 'nenhum depoimento fictício no HTML');
    }

    public function test_so_aparece_depoimento_aprovado_e_autorizado_na_ordem(): void
    {
        IntelligenceTestimonial::query()->delete();
        IntelligenceTestimonial::create(['nome' => 'Sem aprovar', 'texto' => 'a', 'autorizacao_uso' => true, 'aprovado' => false]);
        IntelligenceTestimonial::create(['nome' => 'Segundo', 'cargo' => 'Gerente', 'empresa' => 'Locadora X', 'texto' => 'Texto 2', 'autorizacao_uso' => true, 'aprovado' => true, 'ordem' => 2]);
        IntelligenceTestimonial::create(['nome' => 'Primeiro', 'texto' => 'Texto 1', 'autorizacao_uso' => true, 'aprovado' => true, 'ordem' => 1]);

        $r = $this->getJson('/api/intelligence/depoimentos')->assertOk();
        $this->assertSame(['Primeiro', 'Segundo'], array_column($r->json(), 'nome'));
        $this->assertSame(['nome', 'cargo', 'empresa', 'texto'], array_keys($r->json()[1]), 'só os campos públicos');
    }

    public function test_banco_nao_deixa_aprovar_sem_autorizacao_de_uso(): void
    {
        if (\DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('constraint só existe no Postgres');
        }
        $this->expectException(QueryException::class);
        IntelligenceTestimonial::create(['nome' => 'X', 'texto' => 'y', 'autorizacao_uso' => false, 'aprovado' => true]);
    }

    public function test_api_de_depoimentos_pode_ser_lida_pelo_site_e_so_expoe_campos_publicos(): void
    {
        $r = $this->getJson('/api/intelligence/depoimentos', ['Origin' => 'https://oravel.com.br'])->assertOk();
        $this->assertContains($r->headers->get('Access-Control-Allow-Origin'), ['*', 'https://oravel.com.br'], 'o site precisa conseguir ler');
        $this->assertStringContainsString('public', (string) $r->headers->get('Cache-Control'));
        $this->assertFalse($r->headers->has('Set-Cookie'), 'resposta pública não pode abrir sessão');
    }

    // ---- cadastro vindo do site ----

    private function cadastrarSite(array $over = [], array $headers = [])
    {
        return $this->withHeaders($headers)->post('/intelligence/teste-gratis', array_merge([
            'nome' => 'Maria Silva', 'empresa' => 'Locadora Site', 'whatsapp' => '(11) 99999-0777', 'aceite' => '1',
        ], $over));
    }

    public function test_cadastro_vindo_do_site_cria_a_conta_e_abre_a_tela_de_envio(): void
    {
        Mail::fake();
        $this->cadastrarSite([], ['Origin' => 'https://oravel.com.br'])->assertRedirect(IntelligenceImportResource::getUrl('index'));
        $this->assertTrue(IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', '+5511999990777')->exists());
    }

    public function test_cadastro_de_origem_desconhecida_e_recusado(): void
    {
        Mail::fake();
        $this->cadastrarSite([], ['Origin' => 'https://site-malicioso.test'])->assertRedirect(route('intelligence.trial.create'))->assertSessionHasErrors('nome');
        $this->assertFalse(IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', '+5511999990777')->exists());
    }

    public function test_erro_de_validacao_volta_para_a_tela_do_app_e_nao_para_a_pagina_do_site(): void
    {
        $this->cadastrarSite(['whatsapp' => '123'], ['Origin' => 'https://oravel.com.br', 'Referer' => 'https://oravel.com.br/intelligence/'])
            ->assertRedirect(route('intelligence.trial.create'))->assertSessionHasErrors('whatsapp');
    }

    // ---- funil ----

    public function test_comando_do_funil_roda(): void
    {
        $this->artisan('intelligence:funil', ['--dias' => 7])->expectsOutputToContain('Cadastro concluído')->assertSuccessful();
    }
}
