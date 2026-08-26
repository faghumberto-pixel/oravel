<?php

namespace Tests\Feature;

use App\Services\AsaasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Portal do Cliente (2026-08-25/26): createPayment() é novo -- AsaasService
 * só tinha createSubscription() (assinatura recorrente) antes disso.
 * Cobrança avulsa (POST /payments) devolve invoiceUrl/bankSlipUrl de forma
 * síncrona, usado pela Action "Gerar 2ª via" em MeuFinanceiro.
 */
class AsaasServiceCreatePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_payment_posts_to_payments_endpoint_with_correct_payload(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            '*/payments' => Http::response([
                'id' => 'pay_123', 'invoiceUrl' => 'https://asaas.com/i/pay_123',
                'bankSlipUrl' => 'https://asaas.com/b/pay_123',
            ], 200),
        ]);

        $result = app(AsaasService::class)->createPayment([
            'customer' => 'cus_abc',
            'billingType' => 'BOLETO',
            'value' => 150.00,
            'dueDate' => '2026-09-10',
            'description' => 'Locação Setembro',
        ]);

        $this->assertSame('pay_123', $result['id']);
        $this->assertSame('https://asaas.com/b/pay_123', $result['bankSlipUrl']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/payments')
            && $request['customer'] === 'cus_abc'
            && $request['billingType'] === 'BOLETO'
            && $request['value'] === 150.00);
    }

    public function test_create_payment_throws_on_failed_response(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            '*/payments' => Http::response(['errors' => [['description' => 'Cliente inválido']]], 400),
        ]);

        $this->expectException(\Exception::class);

        app(AsaasService::class)->createPayment([
            'customer' => 'cus_invalido',
            'billingType' => 'BOLETO',
            'value' => 100.00,
            'dueDate' => '2026-09-10',
        ]);
    }
}
