<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Services\DadosDeExemplo;
use App\Domain\Intelligence\Trial\Mail\TrialMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrialPendente;
use App\Http\Middleware\IniciaContagemDoTeste;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IntelligenceCadastroEmailTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['intelligence_trial.notificar_email' => 'dono@example.test', 'services.turnstile.secret' => null]);
    }

    private function cadastrar(array $over = [])
    {
        return $this->post('/intelligence/teste-gratis', array_merge([
            'nome' => 'Carlos', 'empresa' => 'Locadora Carlos', 'whatsapp' => '(19) 98888-7766', 'email' => 'Carlos@Locadora.test', 'senha' => 'Senha1234', 'aceite' => '1',
        ], $over));
    }

    private function link(): ?string
    {
        return Mail::sent(TrialMail::class)->filter(fn ($m) => str_contains((string) $m->link, '/intelligence/confirmar/'))->last()?->link;
    }

    private function conta(): ?IntelligenceTrial
    {
        return IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', '+5519988887766')->first();
    }

    public function test_link_do_email_confirma_uma_vez_so_e_o_get_nao_gasta_o_link(): void
    {
        $this->cadastrar()->assertRedirect(route('intelligence.trial.verifique'));
        $link = $this->link();
        $this->assertNotNull($link);
        $this->assertNotNull(IntelligenceTrialPendente::first(), 'fica pendente, sem conta');

        // leitor de e-mail/antivírus abrindo o link (GET) não cria conta nem gasta o link
        $this->get(parse_url($link, PHP_URL_PATH))->assertOk()->assertSee('Confirmar e-mail');
        $this->assertNull($this->conta());

        $this->post(parse_url($link, PHP_URL_PATH))->assertRedirect();
        $this->assertNotNull($this->conta());
        $this->assertSame('carlos@locadora.test', $this->conta()->user->email, 'e-mail normalizado em minúsculas');
        $this->assertSame(0, IntelligenceTrialPendente::count());

        // segundo clique: link já usado
        auth()->logout();
        $this->post(parse_url($link, PHP_URL_PATH))->assertRedirect(route('intelligence.trial.create'))->assertSessionHasErrors('nome');
        $this->assertGuest();
        $this->assertSame(1, IntelligenceTrial::withoutGlobalScopes()->where('whatsapp', '+5519988887766')->count());
    }

    public function test_link_vencido_ou_inventado_nao_cria_conta(): void
    {
        $this->cadastrar();
        $link = $this->link();
        $this->travel(25)->hours();

        $this->post(parse_url($link, PHP_URL_PATH))->assertRedirect(route('intelligence.trial.create'));
        $this->assertNull($this->conta());
        $this->post('/intelligence/confirmar/'.str_repeat('a', 48))->assertRedirect(route('intelligence.trial.create'));
    }

    public function test_email_que_ja_tem_usuario_nao_cria_pendente_e_a_resposta_e_a_mesma(): void
    {
        User::create(['name' => 'Já existe', 'email' => 'carlos@locadora.test', 'password' => bcrypt('x')]);

        $this->cadastrar()->assertRedirect(route('intelligence.trial.verifique'));

        $this->assertSame(0, IntelligenceTrialPendente::count());
        $this->assertNull($this->conta());
        Mail::assertSent(TrialMail::class, fn ($m) => $m->hasTo('carlos@locadora.test') && str_contains($m->assunto, 'já tem acesso'));
    }

    public function test_so_um_email_a_cada_2_minutos_por_endereco(): void
    {
        $this->cadastrar();
        $this->cadastrar(['nome' => 'Outra vez']);

        $this->assertSame(1, Mail::sent(TrialMail::class)->filter(fn ($m) => str_contains((string) $m->link, '/confirmar/'))->count());
    }

    public function test_whatsapp_que_ja_tem_teste_nao_gera_pendente_nem_conta_nova(): void
    {
        $this->cadastrar();
        $this->post(parse_url($this->link(), PHP_URL_PATH));
        auth()->logout();
        $contas = IntelligenceTrial::withoutGlobalScopes()->count();

        $this->cadastrar(['email' => 'outro@locadora.test'])->assertRedirect(route('intelligence.trial.verifique'));

        $this->assertSame(0, IntelligenceTrialPendente::count());
        $this->assertSame($contas, IntelligenceTrial::withoutGlobalScopes()->count());
        $this->assertGuest();
    }

    public function test_turnstile_so_e_exigido_com_chave_secreta_e_recusa_sem_token_ou_com_falha(): void
    {
        // sem chave secreta (dev/testes): segue
        $this->cadastrar()->assertRedirect(route('intelligence.trial.verifique'));
        IntelligenceTrialPendente::query()->delete();
        Cache::flush();

        config(['services.turnstile.secret' => 'segredo']);
        Http::fake(['challenges.cloudflare.com/*' => Http::sequence()->push(['success' => false])->push(['success' => true])]);

        // sem token: recusa sem nem consultar a Cloudflare
        $this->cadastrar()->assertRedirect(route('intelligence.trial.create'))->assertSessionHasErrors('nome');
        Http::assertNothingSent();
        $this->assertSame(0, IntelligenceTrialPendente::count());

        // Cloudflare diz que falhou
        $this->cadastrar(['cf-turnstile-response' => 'tok-ruim'])->assertSessionHasErrors('nome');
        $this->assertSame(0, IntelligenceTrialPendente::count());

        // Cloudflare aprova
        $this->cadastrar(['cf-turnstile-response' => 'tok-bom'])->assertRedirect(route('intelligence.trial.verifique'));
        $this->assertSame(1, IntelligenceTrialPendente::count());
        Http::assertSent(fn ($r) => $r['secret'] === 'segredo' && $r['response'] === 'tok-bom');
    }

    public function test_cloudflare_fora_do_ar_recusa_o_cadastro(): void
    {
        config(['services.turnstile.secret' => 'segredo']);
        Http::fake(['challenges.cloudflare.com/*' => Http::response('erro', 500)]);

        $this->cadastrar(['cf-turnstile-response' => 'qualquer'])->assertSessionHasErrors('nome');
        $this->assertSame(0, IntelligenceTrialPendente::count());
    }

    public function test_email_descartavel_e_invalido_sao_recusados(): void
    {
        $this->cadastrar(['email' => 'qualquer@mailinator.com'])->assertSessionHasErrors('email');
        $this->assertSame(0, IntelligenceTrialPendente::count());
        Mail::assertNothingSent();
    }

    public function test_teste_conta_30_dias_a_partir_do_primeiro_acesso_confirmado(): void
    {
        $this->assertSame('cadastro', config('intelligence_trial.inicio'), 'padrão: o relógio começa no primeiro acesso');

        $this->cadastrar();
        $this->assertNull($this->conta(), 'antes de confirmar o e-mail não há conta nem relógio');
        $this->post(parse_url($this->link(), PHP_URL_PATH));

        $trial = $this->conta();
        $this->assertNotNull($trial->trial_started_at, 'sem precisar enviar planilha');
        $this->assertSame(30, $trial->diasRestantes());
        $this->assertTrue($trial->trial_ends_at->isSameDay(now()->addDays(30)));
    }

    public function test_conta_antiga_sem_relogio_comeca_no_proximo_acesso_e_nunca_reinicia(): void
    {
        $this->cadastrar();
        $this->post(parse_url($this->link(), PHP_URL_PATH));
        $trial = $this->conta();
        $trial->forceFill(['trial_started_at' => null, 'trial_ends_at' => null])->save(); // conta criada antes desta regra

        $this->passarPeloMiddleware($trial->user); // o próximo acesso autenticado (entrou com e-mail e senha)
        $this->assertSame(30, $trial->fresh()->diasRestantes());

        // já tinha relógio andando: acessar de novo não renova os dias
        $trial->forceFill(['trial_started_at' => now()->subDays(5), 'trial_ends_at' => now()->addDays(25)])->save();
        $this->passarPeloMiddleware($trial->user);
        $this->assertSame(25, $trial->fresh()->diasRestantes());
    }

    public function test_primeiro_acesso_apos_o_login_mostra_os_dados_de_exemplo_uma_vez(): void
    {
        $this->cadastrar();
        $this->post(parse_url($this->link(), PHP_URL_PATH));
        $trial = $this->conta();
        $this->assertFalse(DadosDeExemplo::existe($trial->tenant_id), 'confirmar o e-mail não gera nada: o exemplo vem no 1º acesso');

        $this->passarPeloMiddleware($trial->user);

        $this->assertTrue(DadosDeExemplo::existe($trial->tenant_id));
        $this->assertNotNull($trial->fresh()->exemplo_oferecido_em);
    }

    private function passarPeloMiddleware($user, bool $novaSessao = true)
    {
        $request = Request::create('/admin');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));
        if ($novaSessao) {
            $request->session()->forget('intelligence_contagem_checada');
        }

        return (new IniciaContagemDoTeste)->handle($request, fn () => response('ok'));
    }

    public function test_qualquer_acesso_com_sessao_aberta_inicia_a_contagem_sem_precisar_sair_e_entrar(): void
    {
        $this->cadastrar();
        $this->post(parse_url($this->link(), PHP_URL_PATH));
        $trial = $this->conta();
        $trial->forceFill(['trial_started_at' => null, 'trial_ends_at' => null])->save(); // conta criada antes da regra, já logada no navegador

        $this->assertSame(200, $this->passarPeloMiddleware($trial->user)->getStatusCode());
        $this->assertSame(30, $trial->fresh()->diasRestantes());

        // com o relógio andando, outro acesso nunca renova
        $trial->forceFill(['trial_started_at' => now()->subDays(7), 'trial_ends_at' => now()->addDays(23)])->save();
        $this->passarPeloMiddleware($trial->user);
        $this->assertSame(23, $trial->fresh()->diasRestantes());
    }

    public function test_middleware_nao_faz_nada_fora_do_modo_cadastro_nem_para_quem_nao_tem_conta_de_teste(): void
    {
        $this->cadastrar();
        $this->post(parse_url($this->link(), PHP_URL_PATH));
        $trial = $this->conta();
        $trial->forceFill(['trial_started_at' => null, 'trial_ends_at' => null])->save();

        config(['intelligence_trial.inicio' => 'primeiro_envio']);
        $this->passarPeloMiddleware($trial->user);
        $this->assertNull($trial->fresh()->trial_started_at, 'no modo antigo só o 1º envio inicia a contagem');

        config(['intelligence_trial.inicio' => 'cadastro']);
        $comum = User::create(['name' => 'Sem teste', 'email' => 'sem@teste.test', 'password' => bcrypt('x')]);
        $this->assertSame(200, $this->passarPeloMiddleware($comum)->getStatusCode(), 'usuário sem tenant segue normalmente');
    }
}
