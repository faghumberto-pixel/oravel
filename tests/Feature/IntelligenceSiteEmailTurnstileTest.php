<?php

namespace Tests\Feature;

use App\Console\Commands\SiteIntelligenceGerar;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/** Formulário da página oravel.com.br/intelligence: e-mail obrigatório e widget do Turnstile (sem tocar no banco). */
class IntelligenceSiteEmailTurnstileTest extends TestCase
{
    public function test_formulario_do_site_pede_email_e_avisa_que_confirma_pelo_email(): void
    {
        config(['services.turnstile.site_key' => null]);
        $html = SiteIntelligenceGerar::renderizar();

        $this->assertMatchesRegularExpression('/<input[^>]*type="email"[^>]*name="email"[^>]*required/', $html);
        $this->assertStringContainsString('confirma pelo e-mail', $html);
        $this->assertStringContainsString('Enviamos um link para você confirmar o e-mail', $html);
        // os campos que o app exige continuam: nome, empresa, WhatsApp, aceite e o honeypot
        foreach (['nome', 'empresa', 'whatsapp', 'aceite', 'site'] as $campo) {
            $this->assertStringContainsString('name="'.$campo.'"', $html);
        }
    }

    public function test_widget_do_turnstile_so_aparece_com_a_chave_publica(): void
    {
        config(['services.turnstile.site_key' => null]);
        $this->assertStringNotContainsString('<div class="cf-turnstile"', SiteIntelligenceGerar::renderizar());

        config(['services.turnstile.site_key' => 'CHAVE_PUBLICA_X']);
        $html = SiteIntelligenceGerar::renderizar();
        $this->assertStringContainsString('<div class="cf-turnstile" data-sitekey="CHAVE_PUBLICA_X"', $html);
        // o script do Turnstile só carrega quando o formulário abre (não pesa na página) e o envio espera o desafio
        $this->assertStringNotContainsString('<script src="https://challenges.cloudflare.com', $html);
        $this->assertStringContainsString('challenges.cloudflare.com/turnstile/v0/api.js', $html);
        $this->assertStringContainsString('Aguarde a verificação anti-robô', $html);
    }

    public function test_comando_para_publicar_recusa_gerar_sem_a_chave_publica(): void
    {
        config(['services.turnstile.site_key' => null]);
        $pasta = sys_get_temp_dir().'/site-intel-'.uniqid();

        $this->assertSame(1, Artisan::call('site:intelligence', ['--out' => $pasta, '--exigir-turnstile' => true]));
        $this->assertFileDoesNotExist("$pasta/index.html");

        $this->assertSame(0, Artisan::call('site:intelligence', ['--out' => $pasta, '--exigir-turnstile' => true, '--turnstile-key' => 'CHAVE_PUBLICA_X']));
        $this->assertStringContainsString('data-sitekey="CHAVE_PUBLICA_X"', file_get_contents("$pasta/index.html"));

        array_map('unlink', glob("$pasta/*"));
        rmdir($pasta);
    }
}
