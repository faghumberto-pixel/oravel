<?php

namespace Tests\Feature;

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

    public function test_checkout_form_preselects_plan_from_query_string(): void
    {
        $plan = $this->makePlan();

        $response = $this->get('/assinar?plano='.$plan->id);

        $response->assertOk();
        $response->assertSee($plan->name);
    }

    public function test_checkout_form_ignores_invalid_plan_id_without_error(): void
    {
        $response = $this->get('/assinar?plano=not-a-real-uuid');

        $response->assertOk();
    }

    public function test_submitting_checkout_creates_tenant_admin_and_logs_in(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*/customers' => Http::response(['id' => 'cus_checkout'], 200),
            'sandbox.asaas.com/*/subscriptions' => Http::response(['id' => 'sub_checkout'], 200),
            'sandbox.asaas.com/*/payments*' => Http::response(['data' => [['invoiceUrl' => 'https://www.asaas.com/i/pay_checkout']]], 200),
        ]);

        $plan = $this->makePlan();

        $response = $this->post('/assinar', [
            'plan_id' => $plan->id,
            'company_name' => 'Empresa Checkout Teste',
            'cpf_cnpj' => '123.456.789-01',
            'admin_name' => 'Admin Checkout',
            'admin_email' => 'admin-checkout-'.uniqid().'@oravel.com.br',
            'admin_password' => 'senha12345',
        ]);

        $tenant = Tenant::where('name', 'Empresa Checkout Teste')->first();
        $this->assertNotNull($tenant);
        $this->assertSame($plan->id, $tenant->plan_id);
        $this->assertSame('cus_checkout', $tenant->asaas_customer_id);
        $this->assertSame('sub_checkout', $tenant->asaas_subscription_id);

        $admin = User::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdmin());
        $this->assertAuthenticatedAs($admin);

        $response->assertRedirect('https://www.asaas.com/i/pay_checkout');
    }

    public function test_checkout_redirects_to_dashboard_when_invoice_url_unavailable(): void
    {
        config(['services.asaas.api_key' => null]);
        Http::fake();

        $plan = $this->makePlan();

        $response = $this->post('/assinar', [
            'plan_id' => $plan->id,
            'company_name' => 'Empresa Sem Fatura',
            'cpf_cnpj' => '123.456.789-01',
            'admin_name' => 'Admin Sem Fatura',
            'admin_email' => 'admin-sem-fatura-'.uniqid().'@oravel.com.br',
            'admin_password' => 'senha12345',
        ]);

        $tenant = Tenant::where('name', 'Empresa Sem Fatura')->first();
        $this->assertNotNull($tenant, 'Tenant é criado mesmo sem conseguir sincronizar com a Asaas');

        $admin = User::where('tenant_id', $tenant->id)->first();
        $this->assertAuthenticatedAs($admin);

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_checkout_generates_unique_slug_for_duplicate_company_names(): void
    {
        config(['services.asaas.api_key' => null]);

        $plan = $this->makePlan();

        Tenant::create([
            'name' => 'Empresa Duplicada', 'slug' => 'empresa-duplicada',
            'plan_id' => $plan->id, 'status' => 'trial',
        ]);

        $this->post('/assinar', [
            'plan_id' => $plan->id,
            'company_name' => 'Empresa Duplicada',
            'cpf_cnpj' => '123.456.789-01',
            'admin_name' => 'Admin Duplicado',
            'admin_email' => 'admin-duplicado-'.uniqid().'@oravel.com.br',
            'admin_password' => 'senha12345',
        ]);

        $this->assertDatabaseHas('tenants', ['slug' => 'empresa-duplicada-1']);
    }

    public function test_checkout_requires_all_fields(): void
    {
        $response = $this->post('/assinar', []);

        $response->assertSessionHasErrors(['company_name', 'admin_name', 'admin_email', 'admin_password', 'cpf_cnpj', 'plan_id']);
    }

    public function test_checkout_rejects_duplicate_admin_email(): void
    {
        $plan = $this->makePlan();
        User::create([
            'name' => 'Já Existe', 'email' => 'ja-existe@oravel.com.br',
            'password' => bcrypt('senha12345'), 'role' => 'admin', 'hourly_rate' => 0,
        ]);

        $response = $this->post('/assinar', [
            'plan_id' => $plan->id,
            'company_name' => 'Empresa Email Duplicado',
            'cpf_cnpj' => '123.456.789-01',
            'admin_name' => 'Admin Email Duplicado',
            'admin_email' => 'ja-existe@oravel.com.br',
            'admin_password' => 'senha12345',
        ]);

        $response->assertSessionHasErrors('admin_email');
    }
}
