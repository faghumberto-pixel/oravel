<?php

namespace Tests\Feature;

use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-24: Asset::getFinancialSummary() e o novo
 * Client::getFinancialSummary() devem somar excedente de franquia aprovado
 * (RentalOverageCharge invoiced) e avaria cobrada do cliente (Quote
 * aprovado/concluído vinculado a EquipmentDamage) como receita, e descontar
 * depreciação acumulada do resultado do Asset -- antes disso o "Resumo
 * Financeiro" só somava receita bruta de contrato menos custo de
 * manutenção, sem esses dois componentes reais de dinheiro do negócio.
 */
class AssetAndClientFinancialSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Financeiro '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_contracts', 'tabela_rental_overage_charges', 'tabela_equipment_damages'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Financeiro '.uniqid(), 'slug' => 'tenant-financeiro-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeAsset(Tenant $tenant, array $overrides = []): Asset
    {
        return Asset::create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Gerador Teste '.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL,
            'acquisition_value' => 100000,
            'residual_value' => 10000,
            'useful_life_years' => 10,
            'acquisition_date' => now()->subYears(5),
        ], $overrides));
    }

    private function makeClient(Tenant $tenant): Client
    {
        return Client::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Teste '.uniqid(),
        ]);
    }

    public function test_asset_financial_summary_soma_excedente_de_franquia_invoiced(): void
    {
        $tenant = $this->makeTenant();
        $asset = $this->makeAsset($tenant, ['acquisition_value' => 0, 'useful_life_years' => 0]);
        $client = $this->makeClient($tenant);
        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now()->subMonths(2),
            'billing_type' => 'franquia_excedente', 'price' => 5000,
        ]);

        RentalOverageCharge::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'asset_id' => $asset->id,
            'period_start' => now()->subMonth()->startOfMonth(), 'period_end' => now()->subMonth()->endOfMonth(),
            'hours_used' => 238, 'hours_included' => 200, 'hours_overage' => 38, 'amount' => 1596,
            'status' => RentalOverageCharge::STATUS_INVOICED,
        ]);

        // Excedente ainda pendente de aprovação não deve contar como receita.
        RentalOverageCharge::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'asset_id' => $asset->id,
            'period_start' => now()->startOf('month'), 'period_end' => now()->endOfMonth(),
            'hours_used' => 220, 'hours_included' => 200, 'hours_overage' => 20, 'amount' => 840,
            'status' => RentalOverageCharge::STATUS_PENDING,
        ]);

        $summary = $asset->fresh()->getFinancialSummary();

        $this->assertSame(1596.0, $summary['total_overage_revenue']);
        $this->assertSame(5000.0 + 1596.0, $summary['total_revenue']);
        $this->assertSame(5000.0 + 1596.0, $summary['result']);
    }

    private function makeMaintenanceOrder(Tenant $tenant, Asset $asset, array $overrides = []): MaintenanceOrder
    {
        return MaintenanceOrder::create(array_merge([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'os_number' => 'OS-'.uniqid(), 'status' => 'concluida', 'maintenance_type' => 'corretiva',
        ], $overrides));
    }

    private function makeUser(Tenant $tenant): User
    {
        return User::create([
            'name' => 'Técnico Teste', 'email' => 'tecnico-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
    }

    public function test_asset_financial_summary_soma_avaria_cobravel_aprovada_e_ignora_desgaste_natural(): void
    {
        $tenant = $this->makeTenant();
        $asset = $this->makeAsset($tenant, ['acquisition_value' => 0, 'useful_life_years' => 0]);
        $client = $this->makeClient($tenant);
        $os = $this->makeMaintenanceOrder($tenant, $asset);
        $user = $this->makeUser($tenant);

        $damageCobravel = EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $os->id, 'reported_by_user_id' => $user->id,
            'severity' => 'grave', 'damage_type' => 'estrutural', 'cause' => EquipmentDamage::CAUSE_MAU_USO,
            'description' => 'Dano por mau uso do cliente.',
        ]);
        Quote::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'quotable_type' => EquipmentDamage::class, 'quotable_id' => $damageCobravel->id,
            'type' => Quote::TYPE_INTERNO, 'status' => Quote::STATUS_APROVADO, 'total_value' => 2500,
        ]);

        $damageNatural = EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $os->id, 'reported_by_user_id' => $user->id,
            'severity' => 'leve', 'damage_type' => 'estetico', 'cause' => EquipmentDamage::CAUSE_DESGASTE_NATURAL,
            'description' => 'Desgaste natural de uso.',
        ]);
        Quote::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'quotable_type' => EquipmentDamage::class, 'quotable_id' => $damageNatural->id,
            'type' => Quote::TYPE_INTERNO, 'status' => Quote::STATUS_APROVADO, 'total_value' => 9999,
        ]);

        // Orçamento ainda em rascunho não deve contar como receita.
        $damageRascunho = EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $os->id, 'reported_by_user_id' => $user->id,
            'severity' => 'grave', 'damage_type' => 'estrutural', 'cause' => EquipmentDamage::CAUSE_MAU_USO,
            'description' => 'Dano ainda em avaliação.',
        ]);
        Quote::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'quotable_type' => EquipmentDamage::class, 'quotable_id' => $damageRascunho->id,
            'type' => Quote::TYPE_INTERNO, 'status' => Quote::STATUS_RASCUNHO, 'total_value' => 4000,
        ]);

        $summary = $asset->fresh()->getFinancialSummary();

        $this->assertSame(2500.0, $summary['total_damage_revenue']);
        $this->assertSame(2500.0, $summary['total_revenue']);
    }

    public function test_asset_financial_summary_desconta_depreciacao_acumulada_do_resultado(): void
    {
        $tenant = $this->makeTenant();
        // 100k adquirido ha' exatos 60 meses, residual 10k, vida util 10 anos:
        // 90k depreciavel / 120 meses = 750/mes; 60 meses decorridos = 45000.
        $asset = $this->makeAsset($tenant, ['acquisition_date' => now()->subMonths(60)]);

        $summary = $asset->fresh()->getFinancialSummary();

        // diffInMonths(now()) pode variar +/-1 mês por fração de segundo
        // entre a criação do Asset e o cálculo -- delta cobre isso sem
        // enfraquecer a asserção do essencial: depreciação entra no resultado.
        $this->assertEqualsWithDelta(45000.0, $summary['accumulated_depreciation'], 750.0);
        $this->assertSame(0.0, $summary['total_revenue']);
        $this->assertSame(-$summary['accumulated_depreciation'], $summary['result']);
    }

    public function test_client_financial_summary_soma_receita_dos_ativos_usados_e_custo_das_proprias_os(): void
    {
        $tenant = $this->makeTenant();
        $client = $this->makeClient($tenant);
        $asset = $this->makeAsset($tenant, ['acquisition_value' => 0, 'useful_life_years' => 0]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now()->subMonth(),
            'billing_type' => 'franquia_excedente', 'price' => 5000,
        ]);

        RentalOverageCharge::create([
            'tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'asset_id' => $asset->id,
            'period_start' => now()->subMonth()->startOfMonth(), 'period_end' => now()->subMonth()->endOfMonth(),
            'hours_used' => 238, 'hours_included' => 200, 'hours_overage' => 38, 'amount' => 1596,
            'status' => RentalOverageCharge::STATUS_INVOICED,
        ]);

        $os = $this->makeMaintenanceOrder($tenant, $asset, [
            'client_id' => $client->id,
            'labor_cost' => 300, 'material_cost' => 200, 'logistics_cost' => 50, 'total_order_cost' => 550,
        ]);

        $user = $this->makeUser($tenant);
        $damage = EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $os->id, 'reported_by_user_id' => $user->id,
            'severity' => 'grave', 'damage_type' => 'estrutural', 'cause' => EquipmentDamage::CAUSE_DANO_CLIENTE,
            'description' => 'Dano causado pelo cliente.',
        ]);
        Quote::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'quotable_type' => EquipmentDamage::class, 'quotable_id' => $damage->id,
            'type' => Quote::TYPE_INTERNO, 'status' => Quote::STATUS_CONCLUIDO, 'total_value' => 800,
        ]);

        $summary = $client->fresh()->getFinancialSummary();

        $this->assertSame(5000.0, $summary['total_rental_revenue']);
        $this->assertSame(1596.0, $summary['total_overage_revenue']);
        $this->assertSame(800.0, $summary['total_damage_revenue']);
        $this->assertSame(5000.0 + 1596.0 + 800.0, $summary['total_revenue']);
        $this->assertSame(550.0, $summary['total_maintenance_cost']);
        $this->assertSame(5000.0 + 1596.0 + 800.0 - 550.0, $summary['result']);
    }

    public function test_client_financial_summary_sem_contratos_nem_os_retorna_zeros(): void
    {
        $tenant = $this->makeTenant();
        $client = $this->makeClient($tenant);

        $summary = $client->getFinancialSummary();

        $this->assertSame(0.0, $summary['total_revenue']);
        $this->assertSame(0.0, $summary['total_maintenance_cost']);
        $this->assertSame(0.0, $summary['result']);
    }
}
