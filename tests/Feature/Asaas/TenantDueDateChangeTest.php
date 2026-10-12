<?php

namespace Tests\Feature\Asaas;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Vencimento remarcado no Asaas pra uma cobrança em atraso recomeça a carência de bloqueio. */
class TenantDueDateChangeTest extends TestCase
{
    use RefreshDatabase;

    private function atrasado(): Tenant
    {
        config(['services.asaas.webhook_token' => 'tok']);
        $plan = Plan::create([
            'name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        return Tenant::create([
            'name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active',
            'asaas_customer_id' => 'cus_1', 'asaas_payment_status' => Tenant::PAYMENT_STATUS_ATRASADO,
            'asaas_overdue_since' => now()->subDays(6), 'asaas_last_payment_id' => 'pay_1',
        ]);
    }

    private function send(array $payment): void
    {
        $this->postJson('/api/webhooks/asaas', ['event' => 'PAYMENT_UPDATED', 'payment' => array_merge(
            ['id' => 'pay_1', 'customer' => 'cus_1', 'status' => 'PENDING'], $payment
        )], ['asaas-access-token' => 'tok'])->assertOk();
    }

    public function test_postponed_due_date_restarts_the_grace_period(): void
    {
        $tenant = $this->atrasado();
        $this->assertTrue($tenant->isAccessBlockedForNonPayment());

        $this->send(['dueDate' => now()->addDays(20)->toDateString()]);

        $tenant->refresh();
        $this->assertFalse($tenant->isAccessBlockedForNonPayment());
        $this->assertSame(now()->addDays(20)->toDateString(), $tenant->asaas_overdue_since->toDateString());
        $this->assertSame(Tenant::PAYMENT_STATUS_ATRASADO, $tenant->asaas_payment_status);
    }

    public function test_past_due_date_or_other_payment_changes_nothing(): void
    {
        $tenant = $this->atrasado();

        $this->send(['dueDate' => now()->subDay()->toDateString()]);
        $this->send(['id' => 'pay_outra', 'dueDate' => now()->addDays(20)->toDateString()]);

        $this->assertTrue($tenant->refresh()->isAccessBlockedForNonPayment());
    }
}
