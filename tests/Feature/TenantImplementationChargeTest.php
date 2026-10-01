<?php

namespace Tests\Feature;

use App\Models\ImplementationCharge;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\AsaasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Taxa de implantação do contrato: cobrança única na Asaas, em 1 ou 2 parcelas. */
class TenantImplementationChargeTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(?float $fee, int $installments = 1, ?float $tenantFee = null): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Impl '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
            'implementation_fee' => $fee, 'implementation_installments' => $installments,
        ]);

        return Tenant::create([
            'name' => 'Tenant Impl '.uniqid(), 'slug' => 'tenant-impl-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'asaas_customer_id' => 'cus_impl',
            'implementation_fee' => $tenantFee,
        ]);
    }

    public function test_installment_amounts_always_sum_to_the_total(): void
    {
        $this->assertSame([500.0, 500.0], $this->makeTenant(1000, 2)->implementationInstallmentAmounts());
        $this->assertSame([50.01, 50.0], $this->makeTenant(100.01, 2)->implementationInstallmentAmounts());
        $this->assertSame([300.0], $this->makeTenant(300, 1)->implementationInstallmentAmounts());
        // Valor negociado no tenant vence o do contrato.
        $this->assertSame(800.0, $this->makeTenant(1000, 1, 800)->implementationAmount());
    }

    public function test_two_installments_create_two_separate_asaas_payments(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        $n = 0;
        Http::fake(['sandbox.asaas.com/*/payments' => function () use (&$n) {
            $n++;

            return Http::response(['id' => "pay_$n", 'invoiceUrl' => "https://asaas.test/i/$n"], 200);
        }]);

        $tenant = $this->makeTenant(1000, 2);
        $urls = app(AsaasService::class)->chargeTenantImplementation($tenant);

        $this->assertSame(['https://asaas.test/i/1', 'https://asaas.test/i/2'], $urls);
        $charges = ImplementationCharge::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('installment_number')->get();
        $this->assertCount(2, $charges);
        $this->assertSame(['500.00', '500.00'], $charges->pluck('amount')->all());
        $this->assertEquals(30, $charges[0]->due_date->diffInDays($charges[1]->due_date));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/payments')
            && $r['customer'] === 'cus_impl' && str_contains($r['description'], '(1/2)'));

        // Idempotente: chamar de novo não cria cobrança duplicada.
        app(AsaasService::class)->chargeTenantImplementation($tenant->refresh());
        $this->assertSame(2, ImplementationCharge::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        Http::assertSentCount(2);
    }

    public function test_no_charge_without_fee(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake();

        $this->assertSame([], app(AsaasService::class)->chargeTenantImplementation($this->makeTenant(null)));
        Http::assertNothingSent();
    }

    public function test_webhook_updates_only_the_installment_not_the_subscription_status(): void
    {
        config(['services.asaas.webhook_token' => 'tok']);
        $tenant = $this->makeTenant(1000, 2);
        $tenant->update(['asaas_payment_status' => Tenant::PAYMENT_STATUS_EM_DIA]);
        $charge = ImplementationCharge::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'installment_number' => 1, 'installments_total' => 2,
            'amount' => 500, 'due_date' => now()->addDays(7), 'asaas_payment_id' => 'pay_impl_1',
        ]);

        $send = fn (string $event) => $this->postJson('/api/webhooks/asaas', [
            'event' => $event, 'payment' => ['id' => 'pay_impl_1', 'customer' => 'cus_impl', 'invoiceUrl' => 'https://x'],
        ], ['asaas-access-token' => 'tok'])->assertOk();

        $send('PAYMENT_OVERDUE');
        $this->assertSame(ImplementationCharge::ATRASADO, $charge->refresh()->status);
        // A mensalidade segue em dia: atraso da implantação não bloqueia o acesso.
        $this->assertSame(Tenant::PAYMENT_STATUS_EM_DIA, $tenant->refresh()->asaas_payment_status);

        $send('PAYMENT_RECEIVED');
        $this->assertSame(ImplementationCharge::PAGO, $charge->refresh()->status);
        $this->assertNotNull($charge->paid_at);
    }

    public function test_subscription_agreement_states_implementation_fee_and_installments(): void
    {
        $tenant = $this->makeTenant(1000, 2);

        $html = view('partials.subscription-agreement-clauses', ['contract' => $tenant])->render();

        $this->assertStringContainsString('taxa única de implantação de', $html);
        $this->assertStringContainsString('R$ 1.000,00', $html);
        $this->assertStringContainsString('dividida em 2 parcelas', $html);
        $this->assertStringContainsString('R$ 500,00 e R$ 500,00', $html);
        $this->assertStringContainsString('Limitação de responsabilidade', $html);
        $this->assertStringContainsString('Inadimplência e bloqueio de acesso', $html);

        $semTaxa = view('partials.subscription-agreement-clauses', ['contract' => $this->makeTenant(null)])->render();
        $this->assertStringNotContainsString('taxa única de implantação', $semTaxa);
    }
}
