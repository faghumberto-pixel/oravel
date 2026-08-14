<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
