<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Iscas (materiais gratuitos) do site institucional: public_html/materiais-gratuitos/<slug>/ + lead-isca.php.
 * Garante que cada isca nova esteja ligada de ponta a ponta (página, origem, sitemap, índice) e que o
 * lead-isca.php responda certo nos caminhos que não dependem de rede (Turnstile/CRM/SMTP).
 */
class IscaSiteInstitucionalTest extends TestCase
{
    private string $site;

    protected function setUp(): void
    {
        parent::setUp();
        $this->site = base_path('public_html');
    }

    /** @return array<string, array{pagina: string, arquivo: string}> origem => dados, lidos do próprio lead-isca.php */
    private function iscasDoPhp(): array
    {
        $codigo = file_get_contents($this->site.'/lead-isca.php');
        preg_match_all("/'(isca-[a-z0-9-]+)' => \[(.*?)\n    \],/s", $codigo, $m, PREG_SET_ORDER);
        $iscas = [];
        foreach ($m as $bloco) {
            preg_match("/'pagina'\s*=> '([^']+)'/", $bloco[2], $p);
            preg_match("/'arquivo'\s*=> '([^']+)'/", $bloco[2], $a);
            $iscas[$bloco[1]] = ['pagina' => $p[1], 'arquivo' => $a[1]];
        }

        return $iscas;
    }

    public function test_cada_isca_tem_pagina_formulario_origem_sitemap_e_card_no_indice(): void
    {
        $iscas = $this->iscasDoPhp();
        $this->assertNotEmpty($iscas);

        $sitemap = file_get_contents($this->site.'/sitemap.xml');
        $indice = file_get_contents($this->site.'/materiais-gratuitos/index.html');
        $this->assertStringContainsString('https://oravel.com.br/materiais-gratuitos/</loc>', $sitemap);

        foreach ($iscas as $origem => $d) {
            // mesma regra do InboundLeadController do app
            $this->assertMatchesRegularExpression('/^[a-z0-9-]{1,40}$/', $origem);

            $html = file_get_contents($this->site.$d['pagina'].'index.html');
            $this->assertStringContainsString('action="/lead-isca.php"', $html);
            $this->assertStringContainsString('name="origem" value="'.$origem.'"', $html);
            $this->assertStringContainsString('name="website"', $html);          // honeypot
            $this->assertStringContainsString('cf-turnstile', $html);
            $this->assertStringContainsString('<title>', $html);
            $this->assertStringContainsString('name="description"', $html);
            $this->assertStringContainsString('https://oravel.com.br'.$d['pagina'], $html); // canonical
            $this->assertStringContainsString('Desenvolvimento e tecnologia', $html);
            $this->assertStringContainsString('#F26B1D', $html);

            $this->assertStringContainsString('https://oravel.com.br'.$d['pagina'].'</loc>', $sitemap);
            $this->assertStringContainsString('href="'.$d['pagina'].'"', $indice);
        }
    }

    public function test_arquivo_da_isca_nunca_fica_dentro_do_docroot_publico(): void
    {
        foreach ($this->iscasDoPhp() as $d) {
            $this->assertFileDoesNotExist($this->site.'/materiais-gratuitos/'.$d['arquivo']);
            $this->assertFileDoesNotExist($this->site.'/'.$d['arquivo']);
        }
        // a planilha-fonte vive em storage/app/private (mesma do Mailable do app)
        $this->assertFileExists(storage_path('app/private/Oravel_Controle_Manutencao_Preventiva.xlsx'));
    }

    /** @return array{0: array<int, string>, 1: string} headers e saída do lead-isca.php rodado em CLI */
    private function rodar(string $metodo, array $post): array
    {
        $dir = sys_get_temp_dir().'/isca-'.bin2hex(random_bytes(4));
        mkdir($dir.'/public_html/inc/PHPMailer', 0777, true);
        // header() em CLI nao fica registrado em headers_list(): na copia de teste, troca por uma funcao que imprime.
        file_put_contents($dir.'/public_html/lead-isca.php', str_replace(' header(', ' capturar_header(', file_get_contents($this->site.'/lead-isca.php')));
        foreach (['Exception', 'PHPMailer', 'SMTP'] as $f) {
            file_put_contents($dir."/public_html/inc/PHPMailer/{$f}.php", "<?php\nnamespace PHPMailer\\PHPMailer;\nif ('{$f}' === 'PHPMailer') { class PHPMailer { const ENCRYPTION_SMTPS = 'ssl'; const ENCODING_BASE64 = 'base64'; } }\nif ('{$f}' === 'Exception') { class Exception extends \\Exception {} }\n");
        }
        file_put_contents($dir.'/public_html/inc/lead-config.php', "<?php\nreturn ['turnstile_secret' => 'x'];\n");
        $runner = $dir.'/runner.php';
        file_put_contents($runner, '<?php $_SERVER["REQUEST_METHOD"] = '.var_export($metodo, true).'; $_POST = '.var_export($post, true).';'
            .' function capturar_header(string $h, bool $replace = true, int $code = 0): void { echo "\nHDR:" . $h; }'
            .' require '.var_export($dir.'/public_html/lead-isca.php', true).';');

        $saida = shell_exec('php -d display_errors=1 '.escapeshellarg($runner).' 2>&1') ?? '';
        $leadsGravados = is_dir($dir.'/leads-site');
        array_map('unlink', glob($dir.'/public_html/inc/PHPMailer/*.php'));
        array_map('unlink', glob($dir.'/public_html/inc/*.php'));
        @unlink($dir.'/public_html/lead-isca.php');
        @unlink($runner);

        preg_match_all('/^HDR:(.*)$/m', $saida, $h);
        $headers = array_map('trim', $h[1]);

        return [$headers, ($leadsGravados ? 'LEAD-GRAVADO ' : '').$saida];
    }

    private function location(array $headers): string
    {
        foreach ($headers as $h) {
            if (stripos($h, 'Location:') === 0) {
                return trim(substr($h, 9));
            }
        }

        return '';
    }

    public function test_lead_isca_php_nos_caminhos_sem_rede(): void
    {
        $pagina = '/materiais-gratuitos/planilha-manutencao-preventiva/';
        $ok = ['origem' => 'isca-planilha-manutencao', 'name' => 'Maria Souza', 'company' => 'Locadora X', 'email' => 'maria@example.com', 'phone' => '(19) 99999-1234'];

        // GET e origem desconhecida voltam para o índice, sem ler arquivo nem gravar nada
        [$h] = $this->rodar('GET', []);
        $this->assertSame('/materiais-gratuitos/', $this->location($h));
        [$h, $out] = $this->rodar('POST', ['origem' => '../../etc/passwd'] + $ok);
        $this->assertSame('/materiais-gratuitos/', $this->location($h));
        $this->assertStringNotContainsString('LEAD-GRAVADO', $out);

        // honeypot: finge sucesso, não grava lead
        [$h, $out] = $this->rodar('POST', $ok + ['website' => 'http://spam']);
        $this->assertSame($pagina.'?enviado=1#baixar', $this->location($h));
        $this->assertStringNotContainsString('LEAD-GRAVADO', $out);

        // dados inválidos: erro=dados, sem gravar
        [$h, $out] = $this->rodar('POST', ['email' => 'invalido'] + $ok);
        $this->assertSame($pagina.'?erro=dados#baixar', $this->location($h));
        $this->assertStringNotContainsString('LEAD-GRAVADO', $out);

        [$h] = $this->rodar('POST', ['phone' => ''] + $ok);
        $this->assertSame($pagina.'?erro=dados#baixar', $this->location($h));
    }

    /** @return array<int, string> páginas do site (sem backups) que têm o menu principal "Principal" */
    private function paginasComMenu(): array
    {
        $achadas = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->site, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $arq) {
            $f = $arq->getPathname();
            if (str_contains($f, '.bak') || ! preg_match('/\.(html|php)$/', $f) || str_contains($f, '/nova/')) {
                continue;
            }
            if (str_contains((string) file_get_contents($f), '<nav class="nav" aria-label="Principal">')) {
                $achadas[] = $f;
            }
        }

        return $achadas;
    }

    public function test_menu_do_site_tem_materiais_gratuitos_e_nao_tem_manutencao_logistica_locacao(): void
    {
        $paginas = $this->paginasComMenu();
        $this->assertGreaterThan(30, count($paginas));

        foreach ($paginas as $f) {
            $html = file_get_contents($f);
            $rel = str_replace($this->site.'/', '', $f);

            $this->assertStringContainsString('<a href="/materiais-gratuitos/">Materiais gratuitos</a>', $html, "Sem Materiais gratuitos no menu: {$rel}");
            foreach (['Manutenção' => '/software-manutencao-industrial/', 'Logística' => '/processo-logistica/', 'Locação' => '/erp-cmms/'] as $rotulo => $url) {
                $this->assertStringNotContainsString('<a href="'.$url.'">'.$rotulo.'</a><a href="/materiais-gratuitos/"', $html, "{$rotulo} ainda no menu: {$rel}");
                // dentro do bloco do menu principal e do menu do celular (antes de Contato)
                $this->assertStringNotContainsString('</div></div><a href="'.$url.'">'.$rotulo.'</a>', $html, "{$rotulo} ainda no menu: {$rel}");
            }
        }
    }
}
