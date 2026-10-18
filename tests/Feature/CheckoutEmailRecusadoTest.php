<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentSignature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use App\Rules\EmailSemErroDeDigitacao;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Caso real de 18/10/2026: o e-mail digitado com erro (gamil.com) foi recusado pelo Asaas ao criar o checkout e o cliente caiu numa tela de
 * "entraremos em contato". Agora o erro é pego no cadastro e, se acontecer mesmo assim, a tela deixa corrigir e pagar na hora.
 */
class CheckoutEmailRecusadoTest extends TestCase
{
    use DatabaseTransactions;

    private bool $emailRecusado = true;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.api_key' => 'KEY', 'services.asaas.base_url' => 'https://asaas.test/v3']);
        $this->emailRecusado = true;
        Http::fake(['asaas.test/*' => function ($r) {
            $url = $r->url();

            return match (true) {
                str_contains($url, '/checkouts') && $this->emailRecusado => Http::response(['errors' => [['code' => 'invalid_object', 'description' => 'O campo email é inválido.']]], 400),
                str_contains($url, '/checkouts') => Http::response(['id' => 'che_1', 'link' => 'https://asaas.test/checkout/che_1'], 200),
                str_contains($url, '/customers') => Http::response(['id' => 'cus_1', 'data' => []], 200),
                default => Http::response([], 200),
            };
        }]);
        Cache::flush();
    }

    private function vitrine(): Plan
    {
        $p = Pacotes::planoDeVitrine('manutencao');
        $p->update(['base_price' => 199, 'price' => 199, 'is_active' => true, 'implementation_fee' => 0]);

        return $p->fresh();
    }

    private function payload(Plan $plano, array $extra = []): array
    {
        return $extra + [
            'plan_id' => $plano->id, 'company_name' => 'Empresa '.uniqid(), 'segment' => Client::NICHE_CONSTRUCAO_CIVIL, 'equipment_types' => ['gerador'],
            'cpf_cnpj' => '123.456.789-09', 'cep' => '13480-000', 'logradouro' => 'Rua A', 'numero' => '1', 'bairro' => 'Centro', 'cidade' => 'Limeira', 'uf' => 'SP',
            'admin_name' => 'Admin', 'admin_email' => 'adm-'.uniqid().'@oravel.com.br', 'telefone' => '(19) 99999-9999', 'admin_password' => 'senha12345',
            'forma_pagamento' => 'cartao', 'terms_accepted' => '1',
        ];
    }

    /** Cadastra e assina o contrato; devolve [tenant, token]. */
    private function cadastrarEAssinar(string $email): array
    {
        $plano = $this->vitrine();
        $this->post('/assinar', $this->payload($plano, ['admin_email' => $email, 'company_name' => 'Empresa do Teste']))->assertSessionHasNoErrors()->assertRedirect();
        $t = Tenant::where('name', 'Empresa do Teste')->firstOrFail();
        $assinatura = DocumentSignature::where('signable_id', $t->id)->firstOrFail();

        $this->postJson("/assinatura/{$assinatura->token}/assinar", [
            'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'signer_name' => 'Admin',
        ])->assertOk();

        return [$t, $assinatura->token];
    }

    public function test_common_provider_typos_are_caught_and_real_addresses_pass(): void
    {
        foreach (['joao@gamil.com' => 'gmail.com', 'ana@hotmial.com' => 'hotmail.com', 'rui@gmail.con' => 'gmail.com', 'bia@outlok.com' => 'outlook.com', 'li@yaho.com.br' => 'yahoo.com.br'] as $email => $esperado) {
            $this->assertSame($esperado, EmailSemErroDeDigitacao::sugestao($email), $email);
        }

        foreach (['joao@gmail.com', 'ana@hotmail.com', 'financeiro@empresa.com.br', 'x@oravel.com.br', 'a@uol.com.br', 'b@outlook.com'] as $ok) {
            $this->assertNull(EmailSemErroDeDigitacao::sugestao($ok), $ok);
        }
    }

    public function test_signup_refuses_a_mistyped_email_with_a_helpful_message(): void
    {
        $plano = $this->vitrine();

        $r = $this->post('/assinar', $this->payload($plano, ['admin_email' => 'anglewebagencia@gamil.com']));

        $r->assertSessionHasErrors('admin_email');
        $this->assertStringContainsString('@gmail.com', session('errors')->first('admin_email'));
        $this->assertSame(0, Tenant::where('name', 'like', 'Empresa %')->whereHas('adminUser', fn ($q) => $q->where('email', 'anglewebagencia@gamil.com'))->count());
    }

    public function test_when_the_emitter_refuses_the_email_the_client_can_fix_it_and_goes_to_payment(): void
    {
        [$t, $token] = $this->cadastrarEAssinar('cliente-'.uniqid().'@semprovedor.com.br');

        $r = $this->get(route('checkout.continue', ['token' => $token]));
        $r->assertRedirect(route('checkout.pending', ['t' => $token]));
        $this->assertSame('email', session('motivo_checkout'));

        // A equipe é avisada na linha do tempo.
        $evento = TenantEvent::withoutGlobalScopes()->where('tenant_id', $t->id)->where('event_type', TenantEvent::CHECKOUT_FALHOU)->first();
        $this->assertNotNull($evento);
        $this->assertStringContainsString('email', mb_strtolower($evento->description));

        // A tela explica e oferece o e-mail para corrigir, com o WhatsApp como saída.
        $this->withSession(['motivo_checkout' => 'email'])->get(route('checkout.pending', ['t' => $token]))->assertOk()
            ->assertSee('Falta só o pagamento')->assertSee('E-mail correto')->assertSee('Corrigir e ir para o pagamento')->assertSee('wa.me/5519999332615', false);

        // Corrigir o e-mail com outro erro de digitação é barrado; um e-mail aceito segue para o pagamento.
        $this->post(route('checkout.fix-email', $token), ['email' => 'cliente@gamil.com'])->assertSessionHasErrors('email');

        $this->emailRecusado = false;
        $novo = 'correto-'.uniqid().'@empresa.com.br';
        $this->post(route('checkout.fix-email', $token), ['email' => $novo])->assertRedirect(route('checkout.continue', ['token' => $token]));
        $this->assertSame($novo, $t->fresh()->adminUser->email);

        $this->get(route('checkout.continue', ['token' => $token]))->assertRedirect('https://asaas.test/checkout/che_1');
    }

    public function test_other_failures_show_try_again_and_an_unknown_link_shows_the_generic_page(): void
    {
        [$t, $token] = $this->cadastrarEAssinar('geral-'.uniqid().'@empresa.com.br');

        $this->withSession(['motivo_checkout' => 'geral'])->get(route('checkout.pending', ['t' => $token]))->assertOk()
            ->assertSee('Tentar de novo')->assertSee(route('checkout.continue', ['token' => $token]), false)->assertDontSee('E-mail correto');

        $t->adminUser->forceFill(['email' => 'cliente@gamil.com'])->save();
        $this->get(route('checkout.pending', ['t' => $token]))->assertOk()->assertSee('E-mail correto');

        $this->get(route('checkout.pending'))->assertOk()->assertSee('Recuperar meu link de pagamento')->assertDontSee('E-mail correto');
        $this->get(route('checkout.pending', ['t' => 'token-que-nao-existe']))->assertOk()->assertDontSee('E-mail correto');
    }

    public function test_fixing_the_email_is_only_possible_for_a_signed_contract_that_has_not_paid_yet(): void
    {
        [$t, $token] = $this->cadastrarEAssinar('fixa-'.uniqid().'@empresa.com.br');
        $outro = User::create(['name' => 'Outro', 'email' => 'ocupado-'.uniqid().'@empresa.com.br', 'password' => bcrypt('x')]);

        $this->post(route('checkout.fix-email', $token), ['email' => $outro->email])->assertSessionHasErrors('email');
        $this->post(route('checkout.fix-email', 'token-invalido'), ['email' => 'x@empresa.com.br'])->assertNotFound();

        $t->forceFill(['asaas_subscription_id' => 'sub_1'])->save();
        $this->post(route('checkout.fix-email', $token), ['email' => 'novo-'.uniqid().'@empresa.com.br'])->assertNotFound();
    }
}
