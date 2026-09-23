<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AsaasWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $asaasCustomerId): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Asaas '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [],
        ]);

        return Tenant::create([
            'name' => 'Tenant Asaas '.uniqid(), 'slug' => 'tenant-asaas-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
            'asaas_customer_id' => $asaasCustomerId,
        ]);
    }

    private function payload(string $event, string $customerId, string $paymentId = 'pay_123'): array
    {
        return [
            'event' => $event,
            'payment' => [
                'id' => $paymentId,
                'customer' => $customerId,
                'status' => 'RECEIVED',
                'value' => 297,
            ],
        ];
    }

    public function test_rejects_request_without_valid_token(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $response = $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_RECEIVED', 'cus_123'), [
            'asaas-access-token' => 'token-errado',
        ]);

        $response->assertStatus(401);
    }

    public function test_rejects_request_when_webhook_token_not_configured(): void
    {
        config(['services.asaas.webhook_token' => null]);

        $response = $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_RECEIVED', 'cus_123'), [
            'asaas-access-token' => 'qualquer-coisa',
        ]);

        $response->assertStatus(401);
    }

    public function test_payment_received_marks_tenant_as_em_dia(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $tenant->update(['asaas_payment_status' => Tenant::PAYMENT_STATUS_ATRASADO]);

        $response = $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_RECEIVED', 'cus_abc', 'pay_999'), [
            'asaas-access-token' => 'token-correto',
        ]);

        $response->assertOk();

        $tenant->refresh();
        $this->assertSame(Tenant::PAYMENT_STATUS_EM_DIA, $tenant->asaas_payment_status);
        $this->assertSame('pay_999', $tenant->asaas_last_payment_id);
        $this->assertNotNull($tenant->asaas_payment_updated_at);
    }

    public function test_payment_confirmed_also_marks_tenant_as_em_dia(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_CONFIRMED', 'cus_abc'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $this->assertSame(Tenant::PAYMENT_STATUS_EM_DIA, $tenant->fresh()->asaas_payment_status);
    }

    public function test_payment_overdue_marks_tenant_as_atrasado(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_OVERDUE', 'cus_abc'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $this->assertSame(Tenant::PAYMENT_STATUS_ATRASADO, $tenant->fresh()->asaas_payment_status);
    }

    public function test_payment_deleted_marks_tenant_as_cancelado(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_DELETED', 'cus_abc'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $this->assertSame(Tenant::PAYMENT_STATUS_CANCELADO, $tenant->fresh()->asaas_payment_status);
    }

    public function test_payment_refunded_marks_tenant_as_cancelado(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_REFUNDED', 'cus_abc'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $this->assertSame(Tenant::PAYMENT_STATUS_CANCELADO, $tenant->fresh()->asaas_payment_status);
    }

    public function test_unrecognized_event_is_ignored_without_error(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');

        $response = $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_CREATED', 'cus_abc'), [
            'asaas-access-token' => 'token-correto',
        ]);

        $response->assertOk();
        $this->assertSame(Tenant::PAYMENT_STATUS_EM_DIA, $tenant->fresh()->asaas_payment_status);
    }

    public function test_event_for_unknown_customer_does_not_error(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $response = $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_RECEIVED', 'cus_nao_existe'), [
            'asaas-access-token' => 'token-correto',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'received']);
    }

    public function test_malformed_payload_does_not_error(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $response = $this->postJson('/api/webhooks/asaas', ['foo' => 'bar'], [
            'asaas-access-token' => 'token-correto',
        ]);

        $response->assertOk();
    }

    public function test_does_not_affect_other_tenants(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenantA = $this->makeTenant('cus_a');
        $tenantB = $this->makeTenant('cus_b');

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_OVERDUE', 'cus_a'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $this->assertSame(Tenant::PAYMENT_STATUS_ATRASADO, $tenantA->fresh()->asaas_payment_status);
        $this->assertSame(Tenant::PAYMENT_STATUS_EM_DIA, $tenantB->fresh()->asaas_payment_status);
    }

    public function test_payment_confirmation_approves_pending_admin_from_checkout(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $admin = User::create([
            'name' => 'Admin Pendente', 'email' => 'admin-pendente-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha12345'), 'role' => 'admin', 'hourly_rate' => 0,
            'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['is_approved' => false])->save();

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_RECEIVED', 'cus_abc'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $this->assertTrue((bool) $admin->fresh()->is_approved);
    }

    public function test_payment_overdue_does_not_approve_pending_admin(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $admin = User::create([
            'name' => 'Admin Pendente', 'email' => 'admin-pendente-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha12345'), 'role' => 'admin', 'hourly_rate' => 0,
            'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['is_approved' => false])->save();

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_OVERDUE', 'cus_abc'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $this->assertFalse((bool) $admin->fresh()->is_approved);
    }

    // ========== Testes para AccountReceivable (contas a receber) ==========

    public function test_account_receivable_payment_received_marks_as_pago(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $receivable = AccountReceivable::create([
            'description' => 'Cobrança Cliente',
            'amount' => 1000.00,
            'due_date' => now()->subDays(5),
            'status' => 'pendente',
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'recv_123',
        ]);

        $response = $this->postJson('/api/webhooks/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'recv_123',
                'customer' => 'cus_abc',
                'paymentDate' => '2026-09-18',
            ],
        ], [
            'asaas-access-token' => 'token-correto',
        ]);

        $response->assertOk();

        $receivable->refresh();
        $this->assertSame('pago', $receivable->status);
        $this->assertNotNull($receivable->payment_date);
    }

    public function test_account_receivable_payment_confirmed_marks_as_pago(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $receivable = AccountReceivable::create([
            'description' => 'Cobrança Cliente',
            'amount' => 1000.00,
            'due_date' => now()->subDays(5),
            'status' => 'pendente',
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'recv_456',
        ]);

        $this->postJson('/api/webhooks/asaas', [
            'event' => 'PAYMENT_CONFIRMED',
            'payment' => [
                'id' => 'recv_456',
                'customer' => 'cus_abc',
                'paymentDate' => '2026-09-18',
            ],
        ], [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $this->assertSame('pago', $receivable->fresh()->status);
    }

    public function test_account_receivable_payment_overdue_marks_as_atrasado(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $receivable = AccountReceivable::create([
            'description' => 'Cobrança Cliente',
            'amount' => 1000.00,
            'due_date' => now()->subDays(5),
            'status' => 'pendente',
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'recv_789',
        ]);

        $this->postJson('/api/webhooks/asaas', [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => [
                'id' => 'recv_789',
                'customer' => 'cus_abc',
            ],
        ], [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $this->assertSame('atrasado', $receivable->fresh()->status);
    }

    public function test_account_receivable_payment_refunded_returns_to_pendente(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $receivable = AccountReceivable::create([
            'description' => 'Cobrança Cliente',
            'amount' => 1000.00,
            'due_date' => now()->subDays(5),
            'status' => 'pago',
            'payment_date' => now(),
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'recv_refund',
        ]);

        $this->postJson('/api/webhooks/asaas', [
            'event' => 'PAYMENT_REFUNDED',
            'payment' => [
                'id' => 'recv_refund',
                'customer' => 'cus_abc',
            ],
        ], [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $receivable->refresh();
        $this->assertSame('pendente', $receivable->status);
        $this->assertNull($receivable->payment_date);
    }

    public function test_account_receivable_payment_deleted_returns_to_pendente(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $receivable = AccountReceivable::create([
            'description' => 'Cobrança Cliente',
            'amount' => 1000.00,
            'due_date' => now()->subDays(5),
            'status' => 'pago',
            'payment_date' => now(),
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'recv_delete',
        ]);

        $this->postJson('/api/webhooks/asaas', [
            'event' => 'PAYMENT_DELETED',
            'payment' => [
                'id' => 'recv_delete',
                'customer' => 'cus_abc',
            ],
        ], [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $receivable->refresh();
        $this->assertSame('pendente', $receivable->status);
        $this->assertNull($receivable->payment_date);
    }

    public function test_account_receivable_idempotent_same_status(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $receivable = AccountReceivable::create([
            'description' => 'Cobrança Cliente',
            'amount' => 1000.00,
            'due_date' => now()->subDays(5),
            'status' => 'pago',
            'payment_date' => now()->subDays(1),
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'recv_idem',
        ]);

        $originalPaymentDate = $receivable->payment_date->toDateString();

        $this->postJson('/api/webhooks/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'recv_idem',
                'customer' => 'cus_abc',
                'paymentDate' => now()->toDateString(),
            ],
        ], [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $receivable->refresh();
        $this->assertSame('pago', $receivable->status);
        // payment_date não foi alterada porque já estava em status 'pago'
        $this->assertSame($originalPaymentDate, $receivable->payment_date->toDateString());
    }

    public function test_account_receivable_isolates_from_tenant_subscription_flow(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $receivable = AccountReceivable::create([
            'description' => 'Cobrança Cliente',
            'amount' => 1000.00,
            'due_date' => now()->subDays(5),
            'status' => 'pendente',
            'tenant_id' => $tenant->id,
            'asaas_payment_id' => 'recv_isolation',
        ]);

        // Paymentamento com payment_id de AccountReceivable não deve alterar
        // Tenant.asaas_payment_status, mesmo que trouxer um customer válido
        $tenant->update(['asaas_payment_status' => Tenant::PAYMENT_STATUS_ATRASADO]);

        $this->postJson('/api/webhooks/asaas', [
            'event' => 'PAYMENT_RECEIVED',
            'payment' => [
                'id' => 'recv_isolation',
                'customer' => 'cus_abc',
                'paymentDate' => now()->toDateString(),
            ],
        ], [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $receivable->refresh();
        $this->assertSame('pago', $receivable->status);

        $tenant->refresh();
        // Tenant ainda está em ATRASADO porque o webhook foi de AccountReceivable
        $this->assertSame(Tenant::PAYMENT_STATUS_ATRASADO, $tenant->asaas_payment_status);
    }

    /**
     * Payload de checkout (POST /v3/checkouts) tem um shape diferente do
     * de payment: objeto "checkout", com "externalReference" (= tenant->id
     * na criação, ver AsaasService::createTenantCheckout()) e "customer"
     * (pode ou não ser o mesmo asaas_customer_id já sincronizado).
     */
    private function checkoutPayload(string $event, string $externalReference, ?string $customerId = 'cus_checkout_evt', string $checkoutId = 'che_123'): array
    {
        return [
            'event' => $event,
            'checkout' => array_filter([
                'id' => $checkoutId,
                'externalReference' => $externalReference,
                'customer' => $customerId,
                'status' => 'PAID',
            ], fn ($v) => $v !== null),
        ];
    }

    public function test_checkout_paid_marks_tenant_as_em_dia_and_approves_admin(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $admin = User::create([
            'name' => 'Admin Pendente', 'email' => 'admin-pendente-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha12345'), 'tenant_id' => $tenant->id, 'is_approved' => false,
        ]);

        $response = $this->postJson('/api/webhooks/asaas', $this->checkoutPayload('CHECKOUT_PAID', $tenant->id), [
            'asaas-access-token' => 'token-correto',
        ]);

        $response->assertOk();

        $tenant->refresh();
        $this->assertSame(Tenant::PAYMENT_STATUS_EM_DIA, $tenant->asaas_payment_status);
        $this->assertNotNull($tenant->asaas_payment_updated_at);

        $admin->refresh();
        $this->assertTrue((bool) $admin->is_approved);
    }

    public function test_checkout_paid_casa_pelo_external_reference_nao_pelo_customer_id_ja_sincronizado(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        // Customer sincronizado por syncTenantCustomer() é diferente do
        // customer que o evento de checkout traz -- exatamente o cenário
        // que motivou casar por externalReference (tenant->id), não só
        // por asaas_customer_id.
        $tenant = $this->makeTenant('cus_sincronizado_antes');

        $response = $this->postJson('/api/webhooks/asaas', $this->checkoutPayload('CHECKOUT_PAID', $tenant->id, 'cus_diferente_no_checkout'), [
            'asaas-access-token' => 'token-correto',
        ]);

        $response->assertOk();

        $tenant->refresh();
        $this->assertSame(Tenant::PAYMENT_STATUS_EM_DIA, $tenant->asaas_payment_status);
        // asaas_customer_id já sincronizado antes NÃO é sobrescrito
        // (backfill só acontece quando ainda estava vazio).
        $this->assertSame('cus_sincronizado_antes', $tenant->asaas_customer_id);
    }

    public function test_checkout_paid_faz_backfill_do_customer_id_quando_ainda_vazio(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $plan = Plan::create([
            'name' => 'Plano Asaas '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Sem Customer '.uniqid(), 'slug' => 'tenant-sem-customer-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $this->assertNull($tenant->asaas_customer_id);

        $this->postJson('/api/webhooks/asaas', $this->checkoutPayload('CHECKOUT_PAID', $tenant->id, 'cus_veio_do_checkout'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $tenant->refresh();
        $this->assertSame('cus_veio_do_checkout', $tenant->asaas_customer_id);
    }

    public function test_checkout_canceled_e_expired_marcam_tenant_como_cancelado(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        foreach (['CHECKOUT_CANCELED', 'CHECKOUT_EXPIRED'] as $event) {
            $tenant = $this->makeTenant('cus_'.uniqid());
            $tenant->update(['asaas_payment_status' => Tenant::PAYMENT_STATUS_EM_DIA]);

            $this->postJson('/api/webhooks/asaas', $this->checkoutPayload($event, $tenant->id), [
                'asaas-access-token' => 'token-correto',
            ])->assertOk();

            $tenant->refresh();
            $this->assertSame(Tenant::PAYMENT_STATUS_CANCELADO, $tenant->asaas_payment_status, "evento {$event} deveria marcar cancelado");
        }
    }

    public function test_checkout_event_sem_tenant_correspondente_nao_quebra(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $response = $this->postJson('/api/webhooks/asaas', $this->checkoutPayload('CHECKOUT_PAID', (string) Str::uuid()), [
            'asaas-access-token' => 'token-correto',
        ]);

        $response->assertOk();
    }

    /**
     * asaas_overdue_since alimenta o prazo de tolerância do bloqueio real
     * (Tenant::isAccessBlockedForNonPayment(), pedido do usuário
     * 2026-09-23) -- precisa marcar a TRANSIÇÃO pra atrasado, não o último
     * webhook recebido, senão a Asaas reenviando o MESMO evento resetaria o
     * prazo pra sempre e o tenant nunca seria bloqueado.
     */
    public function test_payment_overdue_marca_overdue_since_na_primeira_vez(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $this->assertNull($tenant->asaas_overdue_since);

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_OVERDUE', 'cus_abc'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $tenant->refresh();
        $this->assertNotNull($tenant->asaas_overdue_since);
        $this->assertTrue($tenant->asaas_overdue_since->isToday());
    }

    public function test_payment_overdue_repetido_nao_reseta_overdue_since(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $tenant->update([
            'asaas_payment_status' => Tenant::PAYMENT_STATUS_ATRASADO,
            'asaas_overdue_since' => now()->subDays(6),
        ]);

        // A Asaas pode reenviar o mesmo evento -- não pode resetar o relógio.
        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_OVERDUE', 'cus_abc'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $tenant->refresh();
        $this->assertTrue($tenant->asaas_overdue_since->isBefore(now()->subDays(5)));
    }

    public function test_payment_received_limpa_overdue_since(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');
        $tenant->update([
            'asaas_payment_status' => Tenant::PAYMENT_STATUS_ATRASADO,
            'asaas_overdue_since' => now()->subDays(3),
        ]);

        $this->postJson('/api/webhooks/asaas', $this->payload('PAYMENT_RECEIVED', 'cus_abc'), [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $tenant->refresh();
        $this->assertNull($tenant->asaas_overdue_since);
    }

    public function test_payment_overdue_captura_invoice_url_do_payload(): void
    {
        config(['services.asaas.webhook_token' => 'token-correto']);

        $tenant = $this->makeTenant('cus_abc');

        $this->postJson('/api/webhooks/asaas', [
            'event' => 'PAYMENT_OVERDUE',
            'payment' => [
                'id' => 'pay_overdue', 'customer' => 'cus_abc',
                'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_overdue',
            ],
        ], [
            'asaas-access-token' => 'token-correto',
        ])->assertOk();

        $tenant->refresh();
        $this->assertSame('https://sandbox.asaas.com/i/pay_overdue', $tenant->asaas_current_invoice_url);
    }
}
