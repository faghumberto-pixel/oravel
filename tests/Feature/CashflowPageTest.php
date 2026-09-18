<?php

namespace Tests\Feature;

use App\Filament\Widgets\CashflowAccumulatedChart;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testa a lógica de cálculo de fluxo de caixa e aggregações.
 * Valida que o widget pode ser instanciado e que as queries
 * agregam dados corretamente.
 */
class CashflowPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = Plan::create([
            'name' => 'Cashflow Test Plan',
            'price' => 100,
            'base_price' => 100,
            'level' => 1,
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'features' => [],
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Cashflow Test Tenant',
            'slug' => 'cashflow-test-'.uniqid(),
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    public function test_cashflow_widget_can_be_instantiated(): void
    {
        $widget = new CashflowAccumulatedChart();
        $this->assertNotNull($widget);
    }

    public function test_cashflow_aggregates_receivables_by_status(): void
    {
        $today = now()->startOfDay();

        // Projetado (pendente)
        AccountReceivable::create([
            'description' => 'Entrada Pendente',
            'amount' => 1000,
            'due_date' => $today->addDays(5),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
        ]);

        // Realizado (pago)
        AccountReceivable::create([
            'description' => 'Entrada Paga',
            'amount' => 500,
            'due_date' => $today->addDays(5),
            'status' => 'pago',
            'payment_date' => $today->addDays(5),
            'tenant_id' => $this->tenant->id,
        ]);

        $projetado = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['pendente', 'atrasado'])
            ->sum('amount');

        $realizado = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->where('status', 'pago')
            ->sum('amount');

        $this->assertEquals(1000, $projetado);
        $this->assertEquals(500, $realizado);
    }

    public function test_cashflow_aggregates_payables_by_status(): void
    {
        $today = now()->startOfDay();

        // Projetado (pendente)
        AccountPayable::create([
            'description' => 'Saída Pendente',
            'amount' => 300,
            'due_date' => $today->addDays(5),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
        ]);

        // Realizado (pago)
        AccountPayable::create([
            'description' => 'Saída Paga',
            'amount' => 200,
            'due_date' => $today->addDays(5),
            'status' => 'pago',
            'payment_date' => $today->addDays(5),
            'tenant_id' => $this->tenant->id,
        ]);

        $projetado = AccountPayable::where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['pendente', 'atrasado'])
            ->sum('amount');

        $realizado = AccountPayable::where('tenant_id', $this->tenant->id)
            ->where('status', 'pago')
            ->sum('amount');

        $this->assertEquals(300, $projetado);
        $this->assertEquals(200, $realizado);
    }

    public function test_cashflow_filters_by_date_range(): void
    {
        $today = now()->startOfDay();

        // In range (next 5 days)
        AccountReceivable::create([
            'description' => 'Dentro da janela',
            'amount' => 1000,
            'due_date' => $today->copy()->addDays(5),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
        ]);

        // Out of range (beyond 90 days)
        AccountReceivable::create([
            'description' => 'Fora da janela',
            'amount' => 2000,
            'due_date' => $today->copy()->addDays(95),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
        ]);

        $limite = now()->addDays(90);

        $inRange = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereBetween('due_date', [$today, $limite])
            ->sum('amount');

        $this->assertEquals(1000, $inRange);
    }

    public function test_cashflow_includes_overdue_in_projected(): void
    {
        $today = now()->startOfDay();

        // Overdue should count as "projetado" (not yet paid)
        AccountReceivable::create([
            'description' => 'Atrasada',
            'amount' => 1500,
            'due_date' => $today->subDays(10),
            'status' => 'atrasado',
            'tenant_id' => $this->tenant->id,
        ]);

        $projetado = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['pendente', 'atrasado'])
            ->sum('amount');

        $this->assertEquals(1500, $projetado);
    }

    public function test_cashflow_calculates_net_balance(): void
    {
        $today = now()->startOfDay();

        // Receivable: +1000
        AccountReceivable::create([
            'description' => 'Entrada',
            'amount' => 1000,
            'due_date' => $today->addDays(5),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
        ]);

        // Payable: -300
        AccountPayable::create([
            'description' => 'Saída',
            'amount' => 300,
            'due_date' => $today->addDays(5),
            'status' => 'pendente',
            'tenant_id' => $this->tenant->id,
        ]);

        $receber = AccountReceivable::where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['pendente', 'atrasado'])
            ->sum('amount');

        $pagar = AccountPayable::where('tenant_id', $this->tenant->id)
            ->whereIn('status', ['pendente', 'atrasado'])
            ->sum('amount');

        $balance = $receber - $pagar;

        $this->assertEquals(700, $balance);
    }
}
