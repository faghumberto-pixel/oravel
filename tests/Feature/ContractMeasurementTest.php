<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\ContractMeasurement;
use App\Domain\Fleet\Models\ContractMeasurementExtra;
use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\HorimeterReading;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContractMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Medição mensal consolidada (valor base pró-rata + excedente de franquia
 * + extras) -- ver App\Domain\Fleet\Models\ContractMeasurement e
 * App\Services\ContractMeasurementService. Reaproveita o motor de
 * excedente já existente (ContractOverageCalculator/RentalOverageCharge)
 * em vez de duplicar o cálculo de horímetro.
 */
class ContractMeasurementTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Medição '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_contracts', 'tabela_account_receivables', 'tabela_rental_overage_charges', 'tabela_contract_measurements'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Medição '.uniqid(), 'slug' => 'tenant-medicao-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        return [$tenant, $admin];
    }

    public function test_full_period_generates_full_base_amount_no_proration(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Mês Cheio']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Mês Cheio', 'status' => Asset::STATUS_LOCADO]);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => $periodStart->copy()->subMonths(3),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 3000,
        ]);

        $measurement = app(ContractMeasurementService::class)->generateForPeriod($contract, $periodStart, $periodEnd);

        $this->assertFalse($measurement->isProrated());
        $this->assertEquals($measurement->total_days_in_period, $measurement->prorated_days);
        $this->assertEqualsWithDelta(3000.0, (float) $measurement->total_base_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $measurement->total_excess_hours_amount, 0.01);
        $this->assertEqualsWithDelta(3000.0, (float) $measurement->total_amount, 0.01);
        $this->assertSame(ContractMeasurement::STATUS_DRAFT, $measurement->status);
    }

    public function test_contract_starting_mid_period_prorates_base_amount(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Entrada']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Entrada', 'status' => Asset::STATUS_LOCADO]);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();
        $totalDays = (int) round($periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay())) + 1;

        // Contrato começou 10 dias depois do início do período de referência.
        $contractStart = $periodStart->copy()->addDays(10);
        $expectedProratedDays = (int) round($contractStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay())) + 1;

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => $contractStart,
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 3000,
        ]);

        $measurement = app(ContractMeasurementService::class)->generateForPeriod($contract, $periodStart, $periodEnd);

        $this->assertTrue($measurement->isProrated());
        $this->assertSame($expectedProratedDays, $measurement->prorated_days);
        $this->assertSame($totalDays, $measurement->total_days_in_period);

        $expectedBase = round(3000 * ($expectedProratedDays / $totalDays), 2);
        $this->assertEqualsWithDelta($expectedBase, (float) $measurement->total_base_amount, 0.01);
    }

    public function test_contract_ending_mid_period_prorates_base_amount(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Saída']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Saída', 'status' => Asset::STATUS_LOCADO]);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();
        $totalDays = (int) round($periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay())) + 1;

        // Contrato terminou 5 dias antes do fim do período de referência.
        $contractEnd = $periodEnd->copy()->subDays(5);
        $expectedProratedDays = (int) round($periodStart->copy()->startOfDay()->diffInDays($contractEnd->copy()->startOfDay())) + 1;

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => $periodStart->copy()->subMonths(2),
            'end_date' => $contractEnd,
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 3000,
        ]);

        $measurement = app(ContractMeasurementService::class)->generateForPeriod($contract, $periodStart, $periodEnd);

        $this->assertTrue($measurement->isProrated());
        $this->assertSame($expectedProratedDays, $measurement->prorated_days);
        $this->assertSame($totalDays, $measurement->total_days_in_period);

        $expectedBase = round(3000 * ($expectedProratedDays / $totalDays), 2);
        $this->assertEqualsWithDelta($expectedBase, (float) $measurement->total_base_amount, 0.01);
    }

    public function test_daily_billing_multiplies_price_by_prorated_days_not_by_ratio(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Diária']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Diária', 'status' => Asset::STATUS_LOCADO]);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        // Só os últimos 7 dias do período.
        $contractStart = $periodEnd->copy()->subDays(6);

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => $contractStart,
            'billing_type' => Contract::BILLING_DIARIA, 'price' => 150,
        ]);

        $measurement = app(ContractMeasurementService::class)->generateForPeriod($contract, $periodStart, $periodEnd);

        $this->assertSame(7, $measurement->prorated_days);
        $this->assertEqualsWithDelta(150 * 7, (float) $measurement->total_base_amount, 0.01);
    }

    public function test_franquia_excedente_reuses_existing_overage_calculation(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Franquia']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Franquia', 'status' => Asset::STATUS_LOCADO]);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => $periodStart->copy()->subMonths(3),
            'billing_type' => Contract::BILLING_FRANQUIA_EXCEDENTE, 'price' => 5000,
        ]);

        RentalHourFranchise::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
            'included_hours_per_period' => 200, 'period_type' => RentalHourFranchise::PERIOD_MENSAL,
            'overage_rate_per_hour' => 42.00, 'effective_from' => $periodStart->copy()->subMonths(3),
        ]);

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 1000, 'recorded_at' => $periodStart->copy()->addDay(), 'source' => 'manual',
        ]);
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 1238, 'recorded_at' => $periodEnd->copy()->subDay(), 'source' => 'manual',
        ]);

        $measurement = app(ContractMeasurementService::class)->generateForPeriod($contract, $periodStart, $periodEnd);

        // 238h trabalhadas - 200h franquia = 38h excedente * 42 = 1596.
        $this->assertEqualsWithDelta(1596.0, (float) $measurement->total_excess_hours_amount, 0.01);
        $this->assertEqualsWithDelta(5000.0, (float) $measurement->total_base_amount, 0.01);
        $this->assertEqualsWithDelta(6596.0, (float) $measurement->total_amount, 0.01);
        $this->assertNotNull($measurement->rental_overage_charge_id);
    }

    public function test_does_not_duplicate_measurement_for_same_period(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Dedupe Medição']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Dedupe Medição', 'status' => Asset::STATUS_LOCADO]);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => $periodStart->copy()->subMonths(2),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000,
        ]);

        $service = app(ContractMeasurementService::class);
        $first = $service->generateForPeriod($contract, $periodStart, $periodEnd);
        $second = $service->generateForPeriod($contract, $periodStart, $periodEnd);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ContractMeasurement::where('contract_id', $contract->id)->count());
    }

    private function makeDraftMeasurement(Tenant $tenant, Client $client, float $baseAmount = 1000): ContractMeasurement
    {
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Workflow '.uniqid(), 'status' => Asset::STATUS_LOCADO]);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => $periodStart->copy()->subMonths(2),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => $baseAmount,
        ]);

        return app(ContractMeasurementService::class)->generateForPeriod($contract, $periodStart, $periodEnd);
    }

    public function test_full_workflow_submit_approve_invoice(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Workflow']);
        $measurement = $this->makeDraftMeasurement($tenant, $client, 2000);

        $measurement->submit();
        $this->assertSame(ContractMeasurement::STATUS_AWAITING_APPROVAL, $measurement->status);

        $measurement->approve($admin);
        $this->assertSame(ContractMeasurement::STATUS_APPROVED, $measurement->status);
        $this->assertNotNull($measurement->approved_at);
        $this->assertSame($admin->id, $measurement->approved_by);

        $this->assertSame(0, AccountReceivable::count());
        $receivable = $measurement->markInvoiced();

        $this->assertSame(1, AccountReceivable::count());
        $this->assertEqualsWithDelta(2000.0, (float) $receivable->amount, 0.01);
        $measurement->refresh();
        $this->assertSame(ContractMeasurement::STATUS_INVOICED, $measurement->status);
        $this->assertSame($receivable->id, $measurement->account_receivable_id);
    }

    public function test_reject_requires_reason_and_sets_status(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Rejeição']);
        $measurement = $this->makeDraftMeasurement($tenant, $client);

        $measurement->submit();
        $measurement->reject($admin, 'Valor base incorreto, contrato foi reajustado.');

        $this->assertSame(ContractMeasurement::STATUS_REJECTED, $measurement->status);
        $this->assertSame('Valor base incorreto, contrato foi reajustado.', $measurement->rejection_reason);
    }

    public function test_reject_fails_with_empty_reason(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Rejeição Vazia']);
        $measurement = $this->makeDraftMeasurement($tenant, $client);
        $measurement->submit();

        $this->expectException(\RuntimeException::class);
        $measurement->reject($admin, '   ');
    }

    public function test_cannot_approve_a_draft_measurement(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Draft Approve']);
        $measurement = $this->makeDraftMeasurement($tenant, $client);

        $this->expectException(\RuntimeException::class);
        $measurement->approve($admin);
    }

    public function test_cannot_invoice_before_approval(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Invoice Cedo']);
        $measurement = $this->makeDraftMeasurement($tenant, $client);
        $measurement->submit();

        $this->expectException(\RuntimeException::class);
        $measurement->markInvoiced();
    }

    public function test_adding_extra_recalculates_totals(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Extras']);
        $measurement = $this->makeDraftMeasurement($tenant, $client, 1000);

        $measurement->extras()->create([
            'type' => ContractMeasurementExtra::TYPE_MOBILIZACAO,
            'description' => 'Frete de ida',
            'amount' => 300,
        ]);
        $measurement->extras()->create([
            'type' => ContractMeasurementExtra::TYPE_DESMOBILIZACAO,
            'description' => 'Frete de volta',
            'amount' => 250,
        ]);
        $measurement->recalculateTotals();

        $measurement->refresh();
        $this->assertEqualsWithDelta(550.0, (float) $measurement->total_extras_amount, 0.01);
        $this->assertEqualsWithDelta(1550.0, (float) $measurement->total_amount, 0.01);
    }
}
