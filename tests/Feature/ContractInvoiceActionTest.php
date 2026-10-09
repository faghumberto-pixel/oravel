<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\ContractMeasurement;
use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Filament\Actions\FaturarContratoAction;
use App\Filament\Resources\ContractResource\Pages\EditContract;
use App\Filament\Resources\ContractResource\Pages\ListContracts;
use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\HorimeterReading;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContractMeasurementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Botão "Faturar" no Contrato (App\Filament\Actions\FaturarContratoAction):
 * gera a cobrança no Contas a Receber passando pelo fluxo normal de
 * ContractMeasurement (rascunho -> enviada -> aprovada -> faturada).
 */
class ContractInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantUser(bool $admin = true): array
    {
        $plan = Plan::create([
            'name' => 'Plano Faturar '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_contracts', 'tabela_account_receivables', 'tabela_rental_overage_charges', 'tabela_contract_measurements'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Faturar '.uniqid(), 'slug' => 'tenant-faturar-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $user = User::create([
            'name' => 'Usuario', 'email' => 'u-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        if ($admin) {
            $user->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
        }

        return [$tenant, $user];
    }

    private function makeContract(Tenant $tenant, array $overrides = []): Contract
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente '.uniqid()]);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo '.uniqid(), 'status' => Asset::STATUS_LOCADO]);

        return Contract::create(array_merge([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now()->subMonths(6),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 3000, 'status' => 'Ativo',
        ], $overrides));
    }

    private function formData(array $extra = []): array
    {
        return array_merge([
            'periodo_inicio' => now()->subMonth()->startOfMonth()->toDateString(),
            'periodo_fim' => now()->subMonth()->endOfMonth()->toDateString(),
            'vencimento' => now()->addDays(10)->toDateString(),
        ], $extra);
    }

    public function test_faturar_from_table_creates_receivable_and_invoices_measurement(): void
    {
        [$tenant, $user] = $this->makeTenantUser();
        $contract = $this->makeContract($tenant);
        $this->actingAs($user);

        Livewire::test(ListContracts::class)
            ->callTableAction('faturar', $contract, $this->formData())
            ->assertHasNoTableActionErrors();

        $measurement = ContractMeasurement::where('contract_id', $contract->id)->sole();
        $this->assertSame(ContractMeasurement::STATUS_INVOICED, $measurement->status);
        $this->assertEquals($user->id, $measurement->approved_by);

        $receivable = AccountReceivable::findOrFail($measurement->account_receivable_id);
        $this->assertEqualsWithDelta(3000.0, (float) $receivable->amount, 0.01);
        $this->assertSame($contract->id, $receivable->contract_id);
        $this->assertSame($contract->client_id, $receivable->client_id);
        $this->assertSame(now()->addDays(10)->toDateString(), $receivable->due_date->toDateString());
    }

    public function test_faturar_from_edit_page_header_works(): void
    {
        [$tenant, $user] = $this->makeTenantUser();
        $contract = $this->makeContract($tenant);
        $this->actingAs($user);

        Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
            ->callAction('faturar', $this->formData())
            ->assertHasNoActionErrors();

        $this->assertSame(1, AccountReceivable::where('contract_id', $contract->id)->count());
    }

    public function test_button_is_hidden_for_inactive_contract_and_for_user_without_permission(): void
    {
        [$tenant, $admin] = $this->makeTenantUser();
        $inativo = $this->makeContract($tenant, ['status' => 'Encerrado']);
        $this->actingAs($admin);

        Livewire::test(ListContracts::class)->assertTableActionHidden('faturar', $inativo);

        [$tenant2, $semPermissao] = $this->makeTenantUser(admin: false);
        $ativo = $this->makeContract($tenant2);
        $this->actingAs($semPermissao);

        $this->assertFalse(FaturarContratoAction::canFaturar($ativo));
    }

    public function test_overlapping_period_is_refused_without_creating_anything(): void
    {
        [$tenant, $user] = $this->makeTenantUser();
        $contract = $this->makeContract($tenant);
        $this->actingAs($user);

        $inicio = now()->subMonth()->startOfMonth();
        ContractMeasurement::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
            'reference_period_start' => $inicio, 'reference_period_end' => $inicio->copy()->addDays(14),
            'total_days_in_period' => 15, 'prorated_days' => 15,
            'total_base_amount' => 1500, 'total_excess_hours_amount' => 0, 'total_extras_amount' => 0,
            'total_amount' => 1500, 'status' => ContractMeasurement::STATUS_DRAFT,
        ]);

        Livewire::test(ListContracts::class)->callTableAction('faturar', $contract, $this->formData());

        $this->assertSame(1, ContractMeasurement::where('contract_id', $contract->id)->count());
        $this->assertSame(0, AccountReceivable::where('contract_id', $contract->id)->count());
    }

    public function test_period_outside_contract_validity_is_refused(): void
    {
        [$tenant, $user] = $this->makeTenantUser();
        $contract = $this->makeContract($tenant, ['start_date' => now()->addMonth(), 'end_date' => now()->addMonths(5)]);
        $this->actingAs($user);

        Livewire::test(ListContracts::class)->callTableAction('faturar', $contract, $this->formData());

        $this->assertSame(0, ContractMeasurement::where('contract_id', $contract->id)->count());
    }

    public function test_por_hora_contract_requires_manual_amount_and_uses_it(): void
    {
        [$tenant, $user] = $this->makeTenantUser();
        $contract = $this->makeContract($tenant, ['billing_type' => Contract::BILLING_POR_HORA, 'price' => 80]);
        $this->actingAs($user);

        Livewire::test(ListContracts::class)
            ->callTableAction('faturar', $contract, $this->formData())
            ->assertHasTableActionErrors(['valor_manual' => 'required']);

        Livewire::test(ListContracts::class)
            ->callTableAction('faturar', $contract, $this->formData(['valor_manual' => 1234.56]))
            ->assertHasNoTableActionErrors();

        $receivable = AccountReceivable::where('contract_id', $contract->id)->sole();
        $this->assertEqualsWithDelta(1234.56, (float) $receivable->amount, 0.01);
    }

    public function test_calculate_for_period_does_not_persist_anything(): void
    {
        [$tenant] = $this->makeTenantUser();
        $contract = $this->makeContract($tenant, ['billing_type' => Contract::BILLING_FRANQUIA_EXCEDENTE, 'price' => 5000]);
        $inicio = now()->subMonth()->startOfMonth();
        $fim = now()->subMonth()->endOfMonth();

        $calc = app(ContractMeasurementService::class)->calculateForPeriod($contract, $inicio, $fim);

        $this->assertEqualsWithDelta(5000.0, $calc['base_amount'], 0.01);
        $this->assertSame(0, ContractMeasurement::count());
        $this->assertSame(0, RentalOverageCharge::count());
    }

    public function test_overage_is_billed_once_with_measurement_and_cannot_be_billed_again(): void
    {
        [$tenant, $user] = $this->makeTenantUser();
        $contract = $this->makeContract($tenant, ['billing_type' => Contract::BILLING_FRANQUIA_EXCEDENTE, 'price' => 5000]);
        $this->actingAs($user);

        $inicio = now()->subMonth()->startOfMonth();
        $fim = now()->subMonth()->endOfMonth();

        RentalHourFranchise::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
            'included_hours_per_period' => 200, 'period_type' => RentalHourFranchise::PERIOD_MENSAL,
            'overage_rate_per_hour' => 42.00, 'effective_from' => $inicio->copy()->subMonths(3),
        ]);
        HorimeterReading::create(['tenant_id' => $tenant->id, 'asset_id' => $contract->asset_id, 'reading' => 1000, 'recorded_at' => $inicio->copy()->addDay(), 'source' => 'manual']);
        HorimeterReading::create(['tenant_id' => $tenant->id, 'asset_id' => $contract->asset_id, 'reading' => 1238, 'recorded_at' => $fim->copy()->subDay(), 'source' => 'manual']);

        Livewire::test(ListContracts::class)->callTableAction('faturar', $contract, $this->formData());

        $receivable = AccountReceivable::where('contract_id', $contract->id)->sole();
        $this->assertEqualsWithDelta(6596.0, (float) $receivable->amount, 0.01);

        $overage = RentalOverageCharge::where('contract_id', $contract->id)->sole();
        $this->assertSame(RentalOverageCharge::STATUS_INVOICED, $overage->status);
        $this->assertSame($receivable->id, $overage->account_receivable_id);

        // Segunda tentativa de cobrar o mesmo excedente pela tela de excedentes é barrada.
        $overage->update(['status' => RentalOverageCharge::STATUS_PENDING]);
        $this->expectException(\RuntimeException::class);
        $overage->approve($user);
    }

    public function test_overage_already_billed_separately_blocks_the_measurement(): void
    {
        [$tenant, $user] = $this->makeTenantUser();
        $contract = $this->makeContract($tenant, ['billing_type' => Contract::BILLING_FRANQUIA_EXCEDENTE, 'price' => 5000]);
        $inicio = now()->subMonth()->startOfMonth();

        $overage = RentalOverageCharge::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'asset_id' => $contract->asset_id,
            'period_start' => $inicio, 'period_end' => $inicio->copy()->endOfMonth(),
            'hours_included' => 200, 'hours_used' => 238, 'hours_overage' => 38, 'amount' => 1596,
            'status' => RentalOverageCharge::STATUS_INVOICED,
        ]);
        $measurement = ContractMeasurement::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id,
            'reference_period_start' => $inicio, 'reference_period_end' => $inicio->copy()->endOfMonth(),
            'total_days_in_period' => 30, 'prorated_days' => 30,
            'total_base_amount' => 5000, 'total_excess_hours_amount' => 1596, 'total_extras_amount' => 0,
            'total_amount' => 6596, 'rental_overage_charge_id' => $overage->id,
            'status' => ContractMeasurement::STATUS_DRAFT,
        ]);

        $this->expectException(\RuntimeException::class);
        $measurement->submit();
    }

    public function test_failure_mid_flow_rolls_back_everything(): void
    {
        [$tenant, $user] = $this->makeTenantUser();
        // preço zero: markInvoiced lança "Não há valor a cobrar" depois de a medição já ter sido criada/aprovada.
        $contract = $this->makeContract($tenant, ['price' => 0]);
        $this->actingAs($user);

        Livewire::test(ListContracts::class)->callTableAction('faturar', $contract, $this->formData());

        $this->assertSame(0, ContractMeasurement::where('contract_id', $contract->id)->count());
        $this->assertSame(0, AccountReceivable::where('contract_id', $contract->id)->count());
    }
}
