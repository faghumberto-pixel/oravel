<?php

namespace Tests\Feature;

use App\Models\ImplementationCharge;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AsaasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Implantação somada à mensalidade: R$ 600/mês + R$ 900 em 2x =>
 * mês 1: 1.050, mês 2: 1.050, mês 3 em diante: 600.
 */
class SummedImplementationBillingTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(int $installments = 2, string $mode = 'somada'): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Somado '.uniqid(), 'price' => 600, 'base_price' => 600, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
            'implementation_fee' => 900, 'implementation_installments' => $installments, 'implementation_billing_mode' => $mode,
        ]);

        return Tenant::create([
            'name' => 'Tenant Somado '.uniqid(), 'slug' => 'tenant-somado-'.uniqid(), 'plan_id' => $plan->id,
            'status' => 'trial', 'mrr_value' => 600, 'cpf_cnpj' => '12.925.998/0001-14', 'asaas_customer_id' => 'cus_s',
            'telefone' => '7391230020', 'logradouro' => 'Av Medici', 'numero' => '1234', 'cep' => '45345-000', 'uf' => 'BA',
        ]);
    }

    private function webhook(Tenant $tenant, string $event, string $paymentId): void
    {
        $this->postJson('/api/webhooks/asaas', [
            'event' => $event,
            'payment' => ['id' => $paymentId, 'customer' => 'cus_s', 'subscription' => 'sub_s', 'invoiceUrl' => 'https://x'],
        ], ['asaas-access-token' => 'tok'])->assertOk();
    }

    public function test_cycle_amounts_follow_the_agreed_table(): void
    {
        $tenant = $this->makeTenant(2);

        $this->assertSame([1050.0, 1050.0], $tenant->summedCycleAmounts());
        $this->assertTrue($tenant->isImplementationSummed());
        $this->assertFalse($this->makeTenant(2, 'separada')->isImplementationSummed());
    }

    public function test_checkout_charges_mensalidade_plus_first_installment_and_no_standalone_charge(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake(['sandbox.asaas.com/*/checkouts' => Http::response(['id' => 'chk_1', 'link' => 'https://asaas.test/c/1'], 200)]);
        $tenant = $this->makeTenant(2);
        User::create(['name' => 'Adm', 'email' => 'a'.uniqid().'@t.com', 'password' => 'x12345678', 'tenant_id' => $tenant->id, 'role' => 'admin', 'hourly_rate' => 0]);

        $this->assertSame('https://asaas.test/c/1', app(AsaasService::class)->createTenantCheckout($tenant->refresh()));
        $this->assertSame([], app(AsaasService::class)->chargeTenantImplementation($tenant->refresh()));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/checkouts')
            && $r['items'][0]['value'] === 1050.0 && str_contains($r['items'][0]['name'], 'Implantação 1/2'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/payments'));

        $rows = ImplementationCharge::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('installment_number')->get();
        $this->assertSame(['450.00', '450.00'], $rows->pluck('amount')->all());
        $this->assertTrue($rows->every(fn ($r) => $r->included_in_subscription && $r->status === 'pendente'));

        // Recriar o checkout (link de recuperação) não duplica as parcelas.
        app(AsaasService::class)->createTenantCheckout($tenant->refresh());
        $this->assertSame(2, ImplementationCharge::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_second_confirmed_payment_returns_the_subscription_to_the_base_value(): void
    {
        config(['services.asaas.webhook_token' => 'tok', 'services.asaas.api_key' => 'test-key']);
        Http::fake(['*/subscriptions/sub_s' => Http::response(['id' => 'sub_s', 'value' => 600], 200)]);
        $tenant = $this->makeTenant(2);
        app(AsaasService::class)->planSummedImplementationInstallments($tenant);

        // 1º pagamento: marca a 1ª parcela, assinatura continua em 1.050.
        $this->webhook($tenant, 'PAYMENT_CONFIRMED', 'pay_1');
        $this->webhook($tenant, 'PAYMENT_RECEIVED', 'pay_1'); // mesmo pagamento, evento repetido
        $rows = ImplementationCharge::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('installment_number')->get();
        $this->assertSame(['pago', 'pendente'], $rows->pluck('status')->all());
        Http::assertNotSent(fn ($r) => $r->method() === 'PUT');
        $this->assertSame('sub_s', $tenant->refresh()->asaas_subscription_id);

        // 2º pagamento: última parcela => assinatura volta a 600.
        $this->webhook($tenant, 'PAYMENT_CONFIRMED', 'pay_2');
        $this->webhook($tenant, 'PAYMENT_RECEIVED', 'pay_2');
        $this->assertSame(['pago', 'pago'], ImplementationCharge::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('installment_number')->pluck('status')->all());
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/subscriptions/sub_s')
            && $r['value'] === 600.0 && $r['updatePendingPayments'] === true);
        $this->assertNotNull($tenant->refresh()->subscription_reverted_to_base_at);

        // 3º pagamento (600): nada muda, nenhum PUT a mais.
        $this->webhook($tenant, 'PAYMENT_CONFIRMED', 'pay_3');
        Http::assertSentCount(1);
    }

    public function test_single_installment_reverts_after_the_first_payment_and_failure_is_retried(): void
    {
        config(['services.asaas.webhook_token' => 'tok', 'services.asaas.api_key' => 'test-key']);
        Http::fake(['*/subscriptions/sub_s' => Http::sequence()->push(['errors' => []], 500)->push(['id' => 'sub_s'], 200)]);
        $tenant = $this->makeTenant(1);
        app(AsaasService::class)->planSummedImplementationInstallments($tenant);

        $this->webhook($tenant, 'PAYMENT_CONFIRMED', 'pay_1'); // PUT falha (500)
        $this->assertNull($tenant->refresh()->subscription_reverted_to_base_at);

        $this->webhook($tenant, 'PAYMENT_RECEIVED', 'pay_1'); // reenvio: tenta de novo e passa
        $this->assertNotNull($tenant->refresh()->subscription_reverted_to_base_at);
        Http::assertSentCount(2);
    }

    public function test_contract_text_describes_the_summed_installments(): void
    {
        $html = view('partials.subscription-agreement-clauses', ['contract' => $this->makeTenant(2)])->render();

        $this->assertStringContainsString('somada às primeiras', $html);
        $this->assertStringContainsString('na 1ª mensalidade, R$ 450,00 de implantação, totalizando', $html);
        $this->assertStringContainsString('R$ 1.050,00', $html);
        $this->assertStringContainsString('voltando a cobrança ao valor normal da mensalidade', $html);
        $this->assertStringNotContainsString('dividida em 2 parcelas', $html);
    }

    public function test_contract_has_the_implementation_plan_annex_with_period_and_steps(): void
    {
        $tenant = $this->makeTenant(2);

        $html = view('partials.subscription-agreement-implementation', ['contract' => $tenant])->render();

        $this->assertStringContainsString('ANEXO I', $html);
        $this->assertStringContainsString('30 dias corridos', $html);
        foreach (['Etapa 1', 'Etapa 2', 'Etapa 3', 'Etapa 4', 'Etapa 5'] as $etapa) {
            $this->assertStringContainsString($etapa, $html);
        }
        $this->assertStringContainsString('Fora do escopo', $html);
        $this->assertStringContainsString('8 horas', $html);

        // Sem taxa de implantação no contrato, o anexo não aparece.
        $semTaxa = $this->makeTenant(2);
        $semTaxa->plan->update(['implementation_fee' => null]);
        $this->assertStringNotContainsString('ANEXO I', view('partials.subscription-agreement-implementation', ['contract' => $semTaxa->refresh()])->render());

        $clauses = view('partials.subscription-agreement-clauses', ['contract' => $tenant])->render();
        $this->assertStringContainsString('Anexo I (Plano de Implantação)', $clauses);
    }
}
