<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DocumentSignature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsaasCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(float $price = 297): Plan
    {
        return Plan::create([
            'name' => 'Plano Checkout '.uniqid(), 'price' => $price, 'base_price' => $price, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Plan $plan, array $overrides = []): array
    {
        return array_merge([
            'plan_id' => $plan->id,
            'company_name' => 'Empresa Checkout Teste',
            'segment' => Client::NICHE_CONSTRUCAO_CIVIL,
            'equipment_types' => ['gerador', 'munk'],
            'cpf_cnpj' => '123.456.789-09',
            'cep' => '13480-000',
            'logradouro' => 'Rua das Torres',
            'numero' => '100',
            'complemento' => 'Sala 1',
            'bairro' => 'Centro',
            'cidade' => 'Limeira',
            'uf' => 'SP',
            'admin_name' => 'Admin Checkout',
            'admin_email' => 'admin-checkout-'.uniqid().'@oravel.com.br',
            'telefone' => '(19) 99999-9999',
            'admin_password' => 'senha12345',
            'terms_accepted' => '1',
        ], $overrides);
    }

    public function test_checkout_form_preselects_plan_from_query_string(): void
    {
        $plan = $this->makePlan();

        $response = $this->get('/assinar?plano='.$plan->id);

        $response->assertOk();
        $response->assertSee($plan->name);
    }

    public function test_checkout_form_redirects_to_site_when_plan_id_is_invalid(): void
    {
        $response = $this->get('/assinar?plano=not-a-real-uuid');

        $response->assertRedirect('https://oravel.com.br/#planos');
    }

    public function test_checkout_form_redirects_to_site_when_plan_is_missing(): void
    {
        $response = $this->get('/assinar');

        $response->assertRedirect('https://oravel.com.br/#planos');
    }

    public function test_checkout_form_does_not_expose_a_plan_picker(): void
    {
        $plan = $this->makePlan();
        $otherPlan = $this->makePlan();

        $response = $this->get('/assinar?plano='.$plan->id);

        $response->assertOk();
        $response->assertSee($plan->name);
        $response->assertDontSee($otherPlan->name);
        $this->assertStringContainsString('type="hidden" name="plan_id" value="'.$plan->id.'"', $response->getContent());
        $this->assertStringNotContainsString('<select id="plan_id"', $response->getContent());
    }

    /**
     * Contrato de Assinatura obrigatório ANTES do pagamento (2026-09-23,
     * pedido do usuário: "ele não pode pagar se não assinar o contrato") --
     * store() agora manda pra assinatura eletrônica (/assinatura/{token}),
     * NÃO mais direto pro Checkout da Asaas. O Checkout só nasce depois,
     * em continueAfterSignature() -- ver os testes mais abaixo.
     */
    public function test_submitting_checkout_creates_tenant_and_redirects_to_contract_signature(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*/customers' => Http::response(['id' => 'cus_checkout'], 200),
        ]);

        $plan = $this->makePlan();

        $response = $this->post('/assinar', $this->validPayload($plan));

        $tenant = Tenant::where('name', 'Empresa Checkout Teste')->first();
        $this->assertNotNull($tenant);
        $this->assertSame($plan->id, $tenant->plan_id);
        $this->assertSame('cus_checkout', $tenant->asaas_customer_id);
        $this->assertSame(Client::NICHE_CONSTRUCAO_CIVIL, $tenant->segment);
        $this->assertSame(['gerador', 'munk'], $tenant->equipment_types);
        $this->assertSame('Limeira', $tenant->cidade);
        $this->assertSame('SP', $tenant->uf);
        $this->assertNotNull($tenant->terms_accepted_at);

        $admin = User::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdmin());
        $this->assertFalse((bool) $admin->is_approved, 'Acesso não pode ser liberado antes da confirmação de pagamento');
        $this->assertGuest();

        // Nenhum Checkout foi criado ainda -- só depois de assinar.
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/checkouts'));

        $signature = DocumentSignature::where('signable_type', Tenant::class)
            ->where('signable_id', $tenant->id)
            ->first();
        $this->assertNotNull($signature, 'Contrato de Assinatura deveria ter sido criado');
        $this->assertSame($tenant->id, $signature->tenant_id, 'tenant_id do contrato é o próprio tenant (ver Tenant::getTenantIdAttribute())');
        $this->assertSame('Admin Checkout', $signature->signer_name);
        $this->assertFalse($signature->is_signed);

        $response->assertRedirect(route('signature.sign', ['token' => $signature->token]));
    }

    public function test_signing_the_subscription_contract_redirects_to_checkout_which_creates_the_payment_link(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*/customers' => Http::response(['id' => 'cus_signed'], 200),
            'sandbox.asaas.com/*/checkouts' => Http::response(['id' => 'che_signed', 'link' => 'https://sandbox.asaas.com/checkoutSession/show/che_signed'], 200),
        ]);

        $plan = $this->makePlan();
        $this->post('/assinar', $this->validPayload($plan, [
            'company_name' => 'Empresa Assinou Contrato',
            'admin_email' => 'admin-assinou-'.uniqid().'@oravel.com.br',
        ]));

        $tenant = Tenant::where('name', 'Empresa Assinou Contrato')->firstOrFail();
        $signature = DocumentSignature::where('signable_id', $tenant->id)->firstOrFail();

        // Assina o contrato via o mesmo endpoint público usado por
        // Contract/MaintenanceOrder (PublicSignatureController::store()).
        $signResponse = $this->postJson("/assinatura/{$signature->token}/assinar", [
            'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            'signer_name' => 'Admin Checkout',
        ]);

        $signResponse->assertOk();
        $signResponse->assertJsonPath('redirect', route('checkout.continue', ['token' => $signature->token]));

        $tenant->refresh();
        $this->assertTrue($signature->fresh()->is_signed);
        $this->assertNull($tenant->asaas_checkout_id, 'Checkout ainda não foi criado só de assinar -- precisa seguir pro continueAfterSignature()');

        // Segue pro link que o JSON de cima devolveu -- é aqui que o
        // Checkout de fato nasce.
        $continueResponse = $this->get(route('checkout.continue', ['token' => $signature->token]));

        $continueResponse->assertRedirect('https://sandbox.asaas.com/checkoutSession/show/che_signed');

        $tenant->refresh();
        $this->assertSame('che_signed', $tenant->asaas_checkout_id);

        // Regressão real (PROD, 2026-09-23): a Asaas rejeita
        // billingTypes=['CREDIT_CARD','PIX'] junto com chargeTypes
        // RECURRENT ("O método de pagamento CREDIT_CARD é o único método
        // de pagamento permitido para operações RECURRENT") -- o
        // Http::fake genérico dos outros testes não pega isso porque
        // devolve a resposta combinada independente do payload enviado.
        // IMPORTANTE: sem o `str_contains` restrito só ao /checkouts, a
        // closure teria que retornar true pra QUALQUER outra chamada
        // (ex: /customers) pra não quebrar o assertSent -- e
        // Http::assertSent() já passa se UMA ÚNICA requisição do
        // histórico bater com a closure, então uma versão frouxa dessa
        // asserção (a primeira tentativa, corrigida aqui) não pegava o
        // bug de verdade: a chamada a /customers "cobria" a
        // asserção mesmo com o /checkouts errado.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkouts')
            && $request['billingTypes'] === ['CREDIT_CARD']
            && $request['chargeTypes'] === ['RECURRENT']);

        // Regressão real #2 (PROD, mesma sessão): depois de corrigir o bug
        // acima, a Asaas passou a rejeitar por outro motivo -- "O campo
        // phoneNumber/address/addressNumber/postalCode/province deve ser
        // informado". customerData só mandava name/cpfCnpj.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/checkouts')
            && ! blank($request['customerData']['email'] ?? null)
            && $request['customerData']['phoneNumber'] === '19999999999'
            && $request['customerData']['address'] === 'Rua das Torres'
            && $request['customerData']['addressNumber'] === '100'
            && $request['customerData']['postalCode'] === '13480000'
            && $request['customerData']['province'] === 'SP');
    }

    public function test_continue_after_signature_redirects_to_pending_if_not_actually_signed(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake();

        $plan = $this->makePlan();
        $this->post('/assinar', $this->validPayload($plan, [
            'company_name' => 'Empresa Nao Assinou',
            'admin_email' => 'admin-nao-assinou-'.uniqid().'@oravel.com.br',
        ]));

        $tenant = Tenant::where('name', 'Empresa Nao Assinou')->firstOrFail();
        $signature = DocumentSignature::where('signable_id', $tenant->id)->firstOrFail();

        // Tenta pular direto pro link de continuação sem ter assinado --
        // não pode gerar Checkout nenhum (a trava real é aqui, não só na UI).
        $response = $this->get(route('checkout.continue', ['token' => $signature->token]));

        $response->assertRedirect(route('checkout.pending', absolute: false));

        $tenant->refresh();
        $this->assertNull($tenant->asaas_checkout_id);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/checkouts'));
    }

    public function test_continue_after_signature_redirects_to_pending_when_checkout_creation_fails(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*/customers' => Http::response(['id' => 'cus_falhou'], 200),
            'sandbox.asaas.com/*/checkouts' => Http::response(['errors' => [['description' => 'Falha simulada']]], 400),
        ]);

        $plan = $this->makePlan();
        $this->post('/assinar', $this->validPayload($plan, [
            'company_name' => 'Empresa Checkout Falhou',
            'admin_email' => 'admin-falhou-'.uniqid().'@oravel.com.br',
        ]));

        $tenant = Tenant::where('name', 'Empresa Checkout Falhou')->firstOrFail();
        $signature = DocumentSignature::where('signable_id', $tenant->id)->firstOrFail();

        $this->postJson("/assinatura/{$signature->token}/assinar", [
            'signature_base64' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            'signer_name' => 'Admin Checkout',
        ])->assertOk();

        $response = $this->get(route('checkout.continue', ['token' => $signature->token]));

        $response->assertRedirect(route('checkout.pending', absolute: false));
    }

    public function test_checkout_generates_unique_slug_for_duplicate_company_names(): void
    {
        config(['services.asaas.api_key' => null]);

        $plan = $this->makePlan();

        Tenant::create([
            'name' => 'Empresa Duplicada', 'slug' => 'empresa-duplicada',
            'plan_id' => $plan->id, 'status' => 'trial',
        ]);

        $this->post('/assinar', $this->validPayload($plan, [
            'company_name' => 'Empresa Duplicada',
            'admin_email' => 'admin-duplicado-'.uniqid().'@oravel.com.br',
        ]));

        $this->assertDatabaseHas('tenants', ['slug' => 'empresa-duplicada-1']);
    }

    public function test_checkout_requires_all_fields(): void
    {
        $response = $this->post('/assinar', []);

        $response->assertSessionHasErrors([
            'company_name', 'admin_name', 'admin_email', 'admin_password', 'cpf_cnpj', 'plan_id',
            'segment', 'equipment_types', 'cep', 'logradouro', 'numero', 'bairro', 'cidade', 'uf',
            'telefone', 'terms_accepted',
        ]);
    }

    public function test_checkout_rejects_invalid_cpf_cnpj(): void
    {
        $plan = $this->makePlan();

        $response = $this->post('/assinar', $this->validPayload($plan, [
            'cpf_cnpj' => '123.456.789-01',
        ]));

        $response->assertSessionHasErrors('cpf_cnpj');
    }

    public function test_checkout_rejects_unknown_equipment_type(): void
    {
        $plan = $this->makePlan();

        $response = $this->post('/assinar', $this->validPayload($plan, [
            'equipment_types' => ['jato-de-particulas'],
        ]));

        $response->assertSessionHasErrors('equipment_types.0');
    }

    public function test_checkout_rejects_duplicate_admin_email(): void
    {
        $plan = $this->makePlan();
        User::create([
            'name' => 'Já Existe', 'email' => 'ja-existe@oravel.com.br',
            'password' => bcrypt('senha12345'), 'role' => 'admin', 'hourly_rate' => 0,
        ]);

        $response = $this->post('/assinar', $this->validPayload($plan, [
            'company_name' => 'Empresa Email Duplicado',
            'admin_email' => 'ja-existe@oravel.com.br',
        ]));

        $response->assertSessionHasErrors('admin_email');
    }
}
