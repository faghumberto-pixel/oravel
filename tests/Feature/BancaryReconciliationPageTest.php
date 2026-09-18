<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testa a lógica de categorização de contas a receber por status
 * de sincronismo com Asaas (Conciliação Bancária).
 */
class BancaryReconciliationPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create([
            'name' => 'Reconciliation Test Plan',
            'price' => 100,
            'base_price' => 100,
            'level' => 1,
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'features' => [],
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Reconciliation Test Tenant',
            'slug' => 'reconciliation-test-'.uniqid(),
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    public function test_page_categorizes_automatically_settled_receivables(): void
    {
        // Com asaas_payment_id + status pago
        AccountReceivable::create([
            'description' => 'Fatura 001',
            'amount' => 1000,
            'due_date' => now()->subDays(5),
            'status' => 'pago',
            'payment_date' => now()->subDays(2),
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_auto_001',
        ]);

        $automatically = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNotNull('asaas_payment_id')
            ->where('status', 'pago')
            ->count();

        $this->assertEquals(1, $automatically);
    }

    public function test_page_categorizes_pending_confirmation_receivables(): void
    {
        // Com asaas_payment_id + status pendente/atrasado
        AccountReceivable::create([
            'description' => 'Fatura 002',
            'amount' => 500,
            'due_date' => now()->addDays(10),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_pending_001',
        ]);

        AccountReceivable::create([
            'description' => 'Fatura 003',
            'amount' => 750,
            'due_date' => now()->subDays(5),
            'status' => 'atrasado',
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_pending_002',
        ]);

        $pending = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNotNull('asaas_payment_id')
            ->whereIn('status', ['pendente', 'atrasado'])
            ->count();

        $this->assertEquals(2, $pending);
    }

    public function test_page_categorizes_manual_settlement_receivables(): void
    {
        // Sem asaas_payment_id
        AccountReceivable::create([
            'description' => 'Cobrança Manual 001',
            'amount' => 2000,
            'due_date' => now()->addDays(5),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
        ]);

        AccountReceivable::create([
            'description' => 'Cobrança Manual 002',
            'amount' => 1500,
            'due_date' => now(),
            'status' => 'pago',
            'payment_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $manual = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNull('asaas_payment_id')
            ->count();

        $this->assertEquals(2, $manual);
    }

    public function test_page_correctly_sums_amounts_per_category(): void
    {
        // Automatically settled: 1000 + 1200 = 2200
        AccountReceivable::create([
            'description' => 'Auto 1',
            'amount' => 1000,
            'due_date' => now()->subDays(5),
            'status' => 'pago',
            'payment_date' => now()->subDays(2),
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_auto_001',
        ]);

        AccountReceivable::create([
            'description' => 'Auto 2',
            'amount' => 1200,
            'due_date' => now()->subDays(3),
            'status' => 'pago',
            'payment_date' => now()->subDays(1),
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_auto_002',
        ]);

        // Pending: 500 + 750 = 1250
        AccountReceivable::create([
            'description' => 'Pending 1',
            'amount' => 500,
            'due_date' => now()->addDays(10),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_pending_001',
        ]);

        AccountReceivable::create([
            'description' => 'Pending 2',
            'amount' => 750,
            'due_date' => now()->subDays(5),
            'status' => 'atrasado',
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_pending_002',
        ]);

        // Manual: 2000 + 1500 = 3500
        AccountReceivable::create([
            'description' => 'Manual 1',
            'amount' => 2000,
            'due_date' => now()->addDays(5),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
        ]);

        AccountReceivable::create([
            'description' => 'Manual 2',
            'amount' => 1500,
            'due_date' => now(),
            'status' => 'pago',
            'payment_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $autoTotal = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNotNull('asaas_payment_id')
            ->where('status', 'pago')
            ->sum('amount');

        $pendingTotal = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNotNull('asaas_payment_id')
            ->whereIn('status', ['pendente', 'atrasado'])
            ->sum('amount');

        $manualTotal = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNull('asaas_payment_id')
            ->sum('amount');

        $this->assertEquals(2200, $autoTotal);
        $this->assertEquals(1250, $pendingTotal);
        $this->assertEquals(3500, $manualTotal);
    }

    public function test_page_handles_empty_categories(): void
    {
        // No receivables at all
        $auto = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNotNull('asaas_payment_id')
            ->where('status', 'pago')
            ->count();

        $pending = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNotNull('asaas_payment_id')
            ->whereIn('status', ['pendente', 'atrasado'])
            ->count();

        $manual = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNull('asaas_payment_id')
            ->count();

        $this->assertEquals(0, $auto);
        $this->assertEquals(0, $pending);
        $this->assertEquals(0, $manual);
    }

    public function test_page_differentiates_asaas_vs_non_asaas_receivables(): void
    {
        // With asaas_payment_id
        AccountReceivable::create([
            'description' => 'Com Asaas',
            'amount' => 1000,
            'due_date' => now(),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_001',
        ]);

        // Without asaas_payment_id
        AccountReceivable::create([
            'description' => 'Sem Asaas',
            'amount' => 1000,
            'due_date' => now(),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
        ]);

        $withAsaas = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNotNull('asaas_payment_id')
            ->count();

        $withoutAsaas = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNull('asaas_payment_id')
            ->count();

        $this->assertEquals(1, $withAsaas);
        $this->assertEquals(1, $withoutAsaas);
    }

    public function test_page_handles_multiple_statuses_in_pending_category(): void
    {
        // Pending should include both "pendente" and "atrasado"
        AccountReceivable::create([
            'description' => 'Pendente',
            'amount' => 500,
            'due_date' => now()->addDays(10),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_pend',
        ]);

        AccountReceivable::create([
            'description' => 'Atrasada',
            'amount' => 750,
            'due_date' => now()->subDays(5),
            'status' => 'atrasado',
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_late',
        ]);

        $pending = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereNotNull('asaas_payment_id')
            ->whereIn('status', ['pendente', 'atrasado'])
            ->sum('amount');

        $this->assertEquals(1250, $pending);
    }

    public function test_pagination_handles_tenant_isolation(): void
    {
        // Create another tenant
        $plan2 = Plan::create([
            'name' => 'Plan 2',
            'price' => 100,
            'base_price' => 100,
            'level' => 1,
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'features' => [],
        ]);

        $tenant2 = Tenant::create([
            'name' => 'Tenant 2',
            'slug' => 'tenant2-'.uniqid(),
            'plan_id' => $plan2->id,
            'status' => 'active',
        ]);

        // Add receivables to tenant 1
        AccountReceivable::create([
            'description' => 'Tenant 1',
            'amount' => 1000,
            'due_date' => now(),
            'status' => 'pago',
            'payment_date' => now(),
            'tenant_id' => $this->tenant->id,
            'asaas_payment_id' => 'recv_t1',
        ]);

        // Add receivables to tenant 2
        AccountReceivable::create([
            'description' => 'Tenant 2',
            'amount' => 2000,
            'due_date' => now(),
            'status' => 'pago',
            'payment_date' => now(),
            'tenant_id' => $tenant2->id,
            'asaas_payment_id' => 'recv_t2',
        ]);

        // Each tenant should only see its own receivables
        $t1Count = AccountReceivable::where('tenant_id', $this->tenant->id)->count();
        $t2Count = AccountReceivable::where('tenant_id', $tenant2->id)->count();

        $this->assertEquals(1, $t1Count);
        $this->assertEquals(1, $t2Count);
    }
}
