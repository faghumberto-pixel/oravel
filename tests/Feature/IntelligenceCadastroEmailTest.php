<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Trial\Mail\TrialMail;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrialPendente;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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
            'nome' => 'Carlos', 'empresa' => 'Locadora Carlos', 'whatsapp' => '(19) 98888-7766', 'email' => 'Carlos@Locadora.test', 'aceite' => '1',
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
        $this->get(parse_url($link, PHP_URL_PATH))->assertOk()->assertSee('Confirmar e entrar');
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
}
