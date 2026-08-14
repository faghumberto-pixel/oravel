<?php

namespace Tests\Feature;

use App\Models\Client;
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

    public function test_submitting_checkout_creates_tenant_and_admin_without_logging_in(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*/customers' => Http::response(['id' => 'cus_checkout'], 200),
            'sandbox.asaas.com/*/subscriptions' => Http::response(['id' => 'sub_checkout'], 200),
            'sandbox.asaas.com/*/payments*' => Http::response(['data' => [['invoiceUrl' => 'https://www.asaas.com/i/pay_checkout']]], 200),
        ]);

        $plan = $this->makePlan();

        $response = $this->post('/assinar', $this->validPayload($plan));

        $tenant = Tenant::where('name', 'Empresa Checkout Teste')->first();
        $this->assertNotNull($tenant);
        $this->assertSame($plan->id, $tenant->plan_id);
        $this->assertSame('cus_checkout', $tenant->asaas_customer_id);
        $this->assertSame('sub_checkout', $tenant->asaas_subscription_id);
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

        $response->assertRedirect('https://www.asaas.com/i/pay_checkout');
    }

    public function test_checkout_redirects_to_pending_page_when_invoice_url_unavailable(): void
    {
        config(['services.asaas.api_key' => null]);
        Http::fake();

        $plan = $this->makePlan();

        $response = $this->post('/assinar', $this->validPayload($plan, [
            'company_name' => 'Empresa Sem Fatura',
            'admin_email' => 'admin-sem-fatura-'.uniqid().'@oravel.com.br',
        ]));

        $tenant = Tenant::where('name', 'Empresa Sem Fatura')->first();
        $this->assertNotNull($tenant, 'Tenant é criado mesmo sem conseguir sincronizar com a Asaas');

        $admin = User::where('tenant_id', $tenant->id)->first();
        $this->assertFalse((bool) $admin->is_approved);
        $this->assertGuest();

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
            'terms_accepted',
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
