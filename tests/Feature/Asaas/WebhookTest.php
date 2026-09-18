<?php

namespace Tests\Feature\Asaas;

use App\Http\Controllers\AsaasWebhookController;
use App\Models\AccountReceivable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $webhookToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->webhookToken = config('services.asaas.webhook_token') ?? 'test-token';
    }

    public function test_webhook_rejects_missing_token(): void
    {
        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => ['id' => 'pay_123'],
        ];

        $response = $this->postJson('/api/webhooks/asaas', $payload);

        $this->assertEquals(401, $response->status());
    }

    public function test_webhook_updates_account_receivable_status(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_123',
            'status' => 'pendente',
        ]);

        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'pay_123',
                'paymentDate' => now()->toDateString(),
            ],
        ];

        $response = $this->postJson('/api/webhooks/asaas', $payload, [
            'asaas-access-token' => $this->webhookToken,
        ]);

        $this->assertTrue($response->ok());
        $this->assertEquals('pago', $receivable->fresh()->status);
    }

    public function test_webhook_marks_overdue(): void
    {
        $tenant = Tenant::factory()->create();
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_456',
            'status' => 'pendente',
        ]);

        $payload = [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => ['id' => 'pay_456'],
        ];

        $this->postJson('/api/webhooks/asaas', $payload, [
            'asaas-access-token' => $this->webhookToken,
        ]);

        $this->assertEquals('atrasado', $receivable->fresh()->status);
    }

    public function test_webhook_refunds(): void
    {
        $tenant = Tenant::factory()->create();
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_789',
            'status' => 'pago',
        ]);

        $payload = [
            'event' => 'PAYMENT_REFUNDED',
            'payment' => ['id' => 'pay_789'],
        ];

        $this->postJson('/api/webhooks/asaas', $payload, [
            'asaas-access-token' => $this->webhookToken,
        ]);

        $this->assertEquals('pendente', $receivable->fresh()->status);
    }

    public function test_webhook_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'pay_idempotent',
            'status' => 'pendente',
        ]);

        $payload = [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => ['id' => 'pay_idempotent'],
        ];

        // Enviar 2x o mesmo evento
        $this->postJson('/api/webhooks/asaas', $payload, [
            'asaas-access-token' => $this->webhookToken,
        ]);

        $statusAfterFirstRequest = $receivable->fresh()->status;

        $this->postJson('/api/webhooks/asaas', $payload, [
            'asaas-access-token' => $this->webhookToken,
        ]);

        $statusAfterSecondRequest = $receivable->fresh()->status;

        // Status deve ser o mesmo (idempotente)
        $this->assertEquals($statusAfterFirstRequest, $statusAfterSecondRequest);
        $this->assertEquals('pago', $statusAfterSecondRequest);
    }
}
