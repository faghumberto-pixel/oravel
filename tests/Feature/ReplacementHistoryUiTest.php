<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource;
use App\Filament\Resources\ContractResource;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentMovement;
use App\Models\EquipmentReplacement;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validacao end-to-end (via HTTP real, nao so Eloquent) de que as telas de
 * Contrato e de Ativo realmente renderizam o historico de substituicao --
 * um teste so nas relações não pegaria um erro de render no Placeholder
 * (ex: metodo com assinatura errada, campo inexistente no HTML).
 */
class ReplacementHistoryUiTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Historico '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_contracts', 'tabela_assets', 'tabela_maintenance_orders', 'tabela_equipment_replacements'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Historico '.uniqid(), 'slug' => 'tenant-historico-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'email_verified_at' => now(),
        ]);
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeCompletedReplacement(Tenant $tenant, User $technician): array
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);

        $originalAsset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Guindaste Original',
            'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_LOCADO,
            'patrimonio' => 'PAT-ORIGINAL', 'client_id' => $client->id,
        ]);
        $replacementAsset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Guindaste Substituto',
            'tag' => 'AST-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'patrimonio' => 'PAT-SUBSTITUTO',
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $originalAsset->id,
            'technician_id' => $technician->id, 'description' => 'Visita tecnica',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $originalAsset->id,
            'contract_number' => 'CT-'.uniqid(), 'start_date' => now(), 'is_active' => true,
        ]);

        $replacement = EquipmentReplacement::create([
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $originalAsset->id,
            'requested_by_user_id' => $technician->id,
            'urgency' => EquipmentReplacement::URGENCY_NORMAL,
            'reason' => 'Diagnostico: falha recorrente no motor.',
        ]);
        $replacement->identifyReplacement($replacementAsset, $technician);
        $replacement->startLogisticsMovements();
        $replacement->refresh();
        $replacement->desmobilizationMovement->update(['status' => EquipmentMovement::STATUS_CONCLUIDO, 'completed_at' => now()]);
        $replacement->mobilizationMovement->update([
            'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'completed_at' => now(),
            'client_signature' => 'data:image/png;base64,FAKE',
        ]);

        return [$contract->fresh(), $originalAsset->fresh(), $replacementAsset->fresh()];
    }

    public function test_contract_edit_page_shows_replacement_diagnosis_history(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        [$contract] = $this->makeCompletedReplacement($tenant, $admin);

        $response = $this->get(ContractResource::getUrl('edit', ['record' => $contract]));

        $response->assertOk();
        $response->assertSee('Histórico de Substituição de Equipamento');
        $response->assertSee('PAT-ORIGINAL');
        $response->assertSee('PAT-SUBSTITUTO');
        $response->assertSee('Diagnostico: falha recorrente no motor.');
        $response->assertSee('Concluído');
    }

    public function test_original_asset_edit_page_shows_replacement_history(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        [, $originalAsset] = $this->makeCompletedReplacement($tenant, $admin);

        $response = $this->get(AssetResource::getUrl('edit', ['record' => $originalAsset]));

        $response->assertOk();
        $response->assertSee('Substituído por: Guindaste Substituto');
        $response->assertSee('Diagnostico: falha recorrente no motor.');
    }

    public function test_replacement_asset_edit_page_shows_replacement_history(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        [, , $replacementAsset] = $this->makeCompletedReplacement($tenant, $admin);

        $response = $this->get(AssetResource::getUrl('edit', ['record' => $replacementAsset]));

        $response->assertOk();
        $response->assertSee('Substituiu: Guindaste Original');
    }
}
