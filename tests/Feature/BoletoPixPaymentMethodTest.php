<?php

namespace Tests\Feature;

use App\Models\DocumentSignature;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Services\AsaasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Forma de pagamento do contrato: cartão (checkout) ou boleto/Pix mensal (assinatura). */
class BoletoPixPaymentMethodTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(string $method = 'boleto_pix', ?string $override = null): Tenant
    {
        config(['services.asaas.api_key' => 'k']);
        $plan = Plan::create(['name' => 'Plano PM '.uniqid(), 'price' => 600, 'base_price' => 600, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [], 'payment_method' => $method]);

        return Tenant::create([
            'name' => 'Cliente PM', 'slug' => 'cliente-pm-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial', 'mrr_value' => 600,
            'cpf_cnpj' => '12.925.998/0001-14', 'asaas_customer_id' => 'cus_pm', 'payment_method' => $override,
        ]);
    }

    private function fakeAsaas(): void
    {
        Http::fake(function (Request $r) {
            return match (true) {
                $r->method() === 'POST' && str_ends_with($r->url(), '/subscriptions') => Http::response(['id' => 'sub_pm'], 200),
                $r->method() === 'POST' && str_ends_with($r->url(), '/checkouts') => Http::response(['id' => 'chk_pm', 'link' => 'https://asaas.test/checkout'], 200),
                str_contains($r->url(), '/subscriptions/sub_pm/payments') => Http::response(['data' => [['invoiceUrl' => 'https://asaas.test/i/primeira']]], 200),
                default => Http::response(['id' => 'x'], 200),
            };
        });
    }

    public function test_contract_default_is_card_and_tenant_can_override(): void
    {
        $this->assertSame('cartao', $this->tenant('cartao')->paymentMethod());
        $this->assertSame('boleto_pix', $this->tenant('boleto_pix')->paymentMethod());
        $this->assertSame('boleto_pix', $this->tenant('cartao', 'boleto_pix')->paymentMethod());
        $this->assertSame('cartao', $this->tenant('boleto_pix', 'cartao')->paymentMethod());
    }

    public function test_boleto_pix_creates_a_monthly_subscription_and_returns_the_first_invoice_once(): void
    {
        $this->fakeAsaas();
        $tenant = $this->tenant();

        $url = app(AsaasService::class)->createTenantBoletoPixSubscription($tenant);

        $this->assertSame('https://asaas.test/i/primeira', $url);
        $this->assertSame('sub_pm', $tenant->refresh()->asaas_subscription_id);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/subscriptions')
            && $r['billingType'] === 'UNDEFINED' && $r['value'] === 600.0 && $r['cycle'] === 'MONTHLY'
            && $r['nextDueDate'] === now()->addDays(3)->toDateString());
        $this->assertSame(1, TenantEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('event_type', TenantEvent::ASSINATURA_CRIADA)->count());

        // Link de recuperação: reaproveita a assinatura, não cria outra.
        app(AsaasService::class)->createTenantBoletoPixSubscription($tenant->refresh());
        $creations = Http::recorded()->filter(fn ($pair) => $pair[0]->method() === 'POST' && str_ends_with($pair[0]->url(), '/subscriptions'))->count();
        $this->assertSame(1, $creations, 'a recuperação não pode criar uma 2ª assinatura');
    }

    public function test_signing_goes_to_the_invoice_page_for_boleto_pix_and_to_checkout_for_card(): void
    {
        $this->fakeAsaas();
        $boleto = $this->tenant('boleto_pix');
        $sigBoleto = DocumentSignature::create(['tenant_id' => $boleto->id, 'signable_type' => Tenant::class, 'signable_id' => $boleto->id, 'signer_name' => 'A', 'signer_email' => 'a@x.com']);
        $sigBoleto->markAsSigned();

        $this->get(route('checkout.continue', ['token' => $sigBoleto->token]))->assertRedirect('https://asaas.test/i/primeira');

        $card = $this->tenant('cartao');
        $card->update(['telefone' => '7391230020', 'logradouro' => 'Av', 'numero' => '1', 'cep' => '45345-000', 'uf' => 'BA']);
        $sigCard = DocumentSignature::create(['tenant_id' => $card->id, 'signable_type' => Tenant::class, 'signable_id' => $card->id, 'signer_name' => 'B', 'signer_email' => 'b@x.com']);
        $sigCard->markAsSigned();

        $this->get(route('checkout.continue', ['token' => $sigCard->token]))->assertRedirect('https://asaas.test/checkout');
    }

    public function test_contract_text_follows_the_payment_method(): void
    {
        $boleto = view('partials.subscription-agreement-clauses', ['contract' => $this->tenant('boleto_pix')])->render();
        $card = view('partials.subscription-agreement-clauses', ['contract' => $this->tenant('cartao')])->render();

        $this->assertStringContainsString('por boleto ou Pix à escolha do Contratante', $boleto);
        $this->assertStringNotContainsString('no cartão de crédito', $boleto);
        $this->assertStringContainsString('recorrente e automática, no cartão de crédito', $card);
    }
}
