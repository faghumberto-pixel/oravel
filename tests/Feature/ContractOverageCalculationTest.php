<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\HorimeterReading;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContractOverageCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-24: cálculo automático de excedente de
 * franquia de horas, sem gerar cobrança sozinho -- fica pending até o
 * financeiro aprovar manualmente (RentalOverageCharge::approve()). Popula
 * a tabela rental_overage_charges que já existia no schema (com Resource
 * CRUD manual), mas sem motor automático até este trabalho.
 *
 * Cobre os desfechos reais: excedente calculado, sem excedente, conflito
 * por contratos sobrepostos no mesmo ativo (caso real confirmado no
 * código, sem constraint que impeça isso) e conflito por dado insuficiente.
 */
class ContractOverageCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Excedente '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_contracts', 'tabela_account_receivables', 'tabela_rental_overage_charges'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Excedente '.uniqid(), 'slug' => 'tenant-excedente-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeContractWithFranchise(Tenant $tenant, Asset $asset, Client $client): array
    {
        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now()->subMonths(2),
            'billing_type' => 'franquia_excedente', 'price' => 5000,
        ]);

        $franchise = RentalHourFranchise::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
            'included_hours_per_period' => 200, 'period_type' => RentalHourFranchise::PERIOD_MENSAL,
            'overage_rate_per_hour' => 42.00, 'effective_from' => now()->subMonths(2),
        ]);

        return [$contract, $franchise];
    }

    public function test_calculates_overage_when_hours_worked_exceed_franchise(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Excedente']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Excedente', 'status' => Asset::STATUS_DISPONIVEL]);
        [$contract, $franchise] = $this->makeContractWithFranchise($tenant, $asset, $client);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 1000, 'recorded_at' => $periodStart->copy()->addDay(), 'source' => 'manual',
        ]);
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 1238, 'recorded_at' => $periodEnd->copy()->subDay(), 'source' => 'manual',
        ]);

        $charge = app(ContractOverageCalculator::class)
            ->calculateForPeriod($contract, $franchise, $periodStart, $periodEnd);

        $this->assertSame(RentalOverageCharge::STATUS_PENDING, $charge->status);
        $this->assertEqualsWithDelta(238.0, (float) $charge->hours_used, 0.01);
        $this->assertEqualsWithDelta(38.0, (float) $charge->hours_overage, 0.01);
        $this->assertEqualsWithDelta(1596.0, (float) $charge->amount, 0.01);
    }

    public function test_marks_hours_overage_zero_when_hours_worked_below_franchise(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Sem Excedente']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Sem Excedente', 'status' => Asset::STATUS_DISPONIVEL]);
        [$contract, $franchise] = $this->makeContractWithFranchise($tenant, $asset, $client);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 1000, 'recorded_at' => $periodStart->copy()->addDay(), 'source' => 'manual',
        ]);
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 1150, 'recorded_at' => $periodEnd->copy()->subDay(), 'source' => 'manual',
        ]);

        $charge = app(ContractOverageCalculator::class)
            ->calculateForPeriod($contract, $franchise, $periodStart, $periodEnd);

        $this->assertSame(RentalOverageCharge::STATUS_PENDING, $charge->status);
        $this->assertEqualsWithDelta(0.0, (float) $charge->hours_overage, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $charge->amount, 0.01);
    }

    public function test_marks_as_conflict_when_asset_has_overlapping_contracts(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Conflito']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Conflito', 'status' => Asset::STATUS_DISPONIVEL]);
        [$contract, $franchise] = $this->makeContractWithFranchise($tenant, $asset, $client);

        // Segundo contrato do MESMO ativo, com período sobreposto -- cenário
        // real que o código permite (sem constraint de banco contra isso).
        Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now()->subMonths(3),
            'end_date' => now()->addMonths(3), 'price' => 3000,
        ]);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        $charge = app(ContractOverageCalculator::class)
            ->calculateForPeriod($contract, $franchise, $periodStart, $periodEnd);

        $this->assertSame(RentalOverageCharge::STATUS_CONFLICT, $charge->status);
        $this->assertNotNull($charge->conflict_reason);
    }

    public function test_marks_as_conflict_when_less_than_two_readings(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Sem Leitura']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Sem Leitura', 'status' => Asset::STATUS_DISPONIVEL]);
        [$contract, $franchise] = $this->makeContractWithFranchise($tenant, $asset, $client);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        $charge = app(ContractOverageCalculator::class)
            ->calculateForPeriod($contract, $franchise, $periodStart, $periodEnd);

        $this->assertSame(RentalOverageCharge::STATUS_CONFLICT, $charge->status);
        $this->assertStringContainsString('Menos de 2 leituras', $charge->conflict_reason);
    }

    public function test_does_not_duplicate_calculation_for_same_period(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Dedupe']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Dedupe', 'status' => Asset::STATUS_DISPONIVEL]);
        [$contract, $franchise] = $this->makeContractWithFranchise($tenant, $asset, $client);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        $calculator = app(ContractOverageCalculator::class);
        $first = $calculator->calculateForPeriod($contract, $franchise, $periodStart, $periodEnd);
        $second = $calculator->calculateForPeriod($contract, $franchise, $periodStart, $periodEnd);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, RentalOverageCharge::where('contract_id', $contract->id)->count());
    }

    public function test_approve_creates_account_receivable_and_locks_status(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Aprovação']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Aprovação', 'status' => Asset::STATUS_DISPONIVEL]);
        [$contract, $franchise] = $this->makeContractWithFranchise($tenant, $asset, $client);

        $periodStart = now()->subMonth()->startOfMonth();
        $periodEnd = now()->subMonth()->endOfMonth();

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 1000, 'recorded_at' => $periodStart->copy()->addDay(), 'source' => 'manual',
        ]);
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'reading' => 1238, 'recorded_at' => $periodEnd->copy()->subDay(), 'source' => 'manual',
        ]);

        $charge = app(ContractOverageCalculator::class)
            ->calculateForPeriod($contract, $franchise, $periodStart, $periodEnd);

        $this->assertSame(0, AccountReceivable::count());

        $receivable = $charge->approve($admin);

        $this->assertSame(1, AccountReceivable::count());
        $this->assertEqualsWithDelta(1596.0, (float) $receivable->amount, 0.01);
        $this->assertSame($client->id, $receivable->client_id);
        $this->assertSame($contract->id, $receivable->contract_id);

        $charge->refresh();
        $this->assertSame(RentalOverageCharge::STATUS_INVOICED, $charge->status);
        $this->assertSame($receivable->id, $charge->account_receivable_id);

        $this->expectException(\RuntimeException::class);
        $charge->approve($admin);
    }

    public function test_approve_fails_when_no_overage(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Sem Cobrança']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Sem Cobrança', 'status' => Asset::STATUS_DISPONIVEL]);
        [$contract, $franchise] = $this->makeContractWithFranchise($tenant, $asset, $client);

        $charge = RentalOverageCharge::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'asset_id' => $asset->id,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'hours_included' => 200, 'hours_used' => 150, 'hours_overage' => 0,
            'amount' => 0, 'status' => RentalOverageCharge::STATUS_PENDING,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Não há valor a cobrar neste excedente.');
        $charge->approve($admin);
    }

    public function test_approve_fails_when_status_is_not_pending(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Conflito Aprovação']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Conflito Aprovação', 'status' => Asset::STATUS_DISPONIVEL]);
        [$contract, $franchise] = $this->makeContractWithFranchise($tenant, $asset, $client);

        $charge = RentalOverageCharge::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'asset_id' => $asset->id,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'hours_included' => 200,
            'status' => RentalOverageCharge::STATUS_CONFLICT,
            'conflict_reason' => 'Teste de conflito',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Só é possível aprovar um excedente pendente.');
        $charge->approve($admin);
    }
}
