<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-25: modalidade "Diária" pra fechar prospect
 * (Alumaq) cujo modelo de negócio é aluguel de curto prazo por dia.
 * Contagem confirmada com o usuário: inclusive-inclusive (01/09 a 03/09 =
 * 3 diárias). Cálculo é só leitura (price x dias) -- sem cobrança
 * automática, decisão consciente do usuário para esta entrega.
 */
class ContractDailyBillingTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Diaria '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_contracts'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Diaria '.uniqid(), 'slug' => 'tenant-diaria-'.uniqid(),
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

    public function test_billing_type_options_include_diaria(): void
    {
        $this->assertArrayHasKey(Contract::BILLING_DIARIA, Contract::billingTypeOptions());
        $this->assertSame('Diária', Contract::billingTypeOptions()[Contract::BILLING_DIARIA]);
    }

    public function test_uses_daily_billing_true_only_for_diaria(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Diaria']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Solda Diaria', 'status' => Asset::STATUS_DISPONIVEL]);

        $diaria = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now(), 'end_date' => now()->addDays(2),
            'billing_type' => Contract::BILLING_DIARIA, 'price' => 150,
        ]);
        $mensal = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 5000,
        ]);

        $this->assertTrue($diaria->usesDailyBilling());
        $this->assertFalse($mensal->usesDailyBilling());
    }

    public function test_calculated_daily_total_is_inclusive_inclusive(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Diaria']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Solda Diaria', 'status' => Asset::STATUS_DISPONIVEL]);

        $tresDias = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(),
            'start_date' => '2026-09-01', 'end_date' => '2026-09-03',
            'billing_type' => Contract::BILLING_DIARIA, 'price' => 150,
        ]);

        $umDia = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(),
            'start_date' => '2026-09-01', 'end_date' => '2026-09-01',
            'billing_type' => Contract::BILLING_DIARIA, 'price' => 150,
        ]);

        $this->assertSame(450.0, $tresDias->calculatedDailyTotal());
        $this->assertSame(150.0, $umDia->calculatedDailyTotal());
    }

    public function test_calculated_daily_total_is_null_without_end_date_or_wrong_modality(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Diaria']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Solda Diaria', 'status' => Asset::STATUS_DISPONIVEL]);

        $semEndDate = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_DIARIA, 'price' => 150,
        ]);

        $mensal = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now(), 'end_date' => now()->addDays(5),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 5000,
        ]);

        $this->assertNull($semEndDate->calculatedDailyTotal());
        $this->assertNull($mensal->calculatedDailyTotal());
    }

    public function test_end_date_is_not_required_for_mensal_fixo(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Diaria']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Solda Diaria', 'status' => Asset::STATUS_DISPONIVEL]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 5000,
        ]);

        $this->assertNull($contract->end_date);
    }
}
