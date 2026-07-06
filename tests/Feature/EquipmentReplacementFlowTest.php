<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement;
use App\Models\EquipmentReplacement;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentReplacementFlowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mesmos papeis base que App\Services\TenantProvisioner::provision() cria
     * pra todo tenant real -- os Observers notificam por esses papeis
     * independente do teste precisar ou nao verificar a notificacao.
     */
    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Troca '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_equipment_replacements', 'tabela_assets', 'tabela_maintenance_orders', 'tabela_contracts'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Troca '.uniqid(), 'slug' => 'tenant-troca-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        foreach ([EquipmentDamage::ROLE_COMERCIAL, EquipmentReplacement::ROLE_LOGISTICA] as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        }

        return $tenant;
    }

    private function makeUser(Tenant $tenant, string $name, ?string $roleName = null): User
    {
        $user = User::create([
            'name' => $name,
            'email' => str($name)->slug().'-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'),
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        if ($roleName) {
            // tenant_id precisa estar dentro do array de busca do
            // firstOrCreate, nao so nos defaults -- senao, com mais de um
            // tenant no mesmo teste, reaproveita a role de OUTRO tenant que
            // por acaso tenha o mesmo nome (exatamente o bug que
            // EquipmentDamageObserver/EquipmentReplacementObserver tinham).
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
            $user->assignRole($role);
        }

        return $user;
    }

    private function makeAsset(Tenant $tenant, string $name, string $status): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'tag' => 'AST-'.uniqid(),
            'status' => $status,
        ]);
    }

    private function makeOrder(Tenant $tenant, Asset $asset, User $technician): MaintenanceOrder
    {
        return MaintenanceOrder::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'technician_id' => $technician->id,
            'description' => 'Visita tecnica',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);
    }

    public function test_creating_a_replacement_request_notifies_comercial_with_urgency_severity(): void
    {
        $tenant = $this->makeTenant();
        $technician = $this->makeUser($tenant, 'Tecnico Campo');
        $comercial = $this->makeUser($tenant, 'Comercial User', 'Comercial');
        $originalAsset = $this->makeAsset($tenant, 'Gerador Original', Asset::STATUS_LOCADO);
        $order = $this->makeOrder($tenant, $originalAsset, $technician);

        $this->actingAs($technician);

        $replacement = EquipmentReplacement::create([
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $originalAsset->id,
            'requested_by_user_id' => $technician->id,
            'urgency' => EquipmentReplacement::URGENCY_CRITICO,
            'reason' => 'Falha recorrente no motor.',
        ]);

        $this->assertEquals($tenant->id, $replacement->tenant_id);
        $this->assertEquals(EquipmentReplacement::STATUS_SOLICITADO, $replacement->status);

        $notification = $comercial->fresh()->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('danger', $notification->data['status'] ?? null);
    }

    public function test_identifying_replacement_does_not_yet_notify_logistica(): void
    {
        $tenant = $this->makeTenant();
        $technician = $this->makeUser($tenant, 'Tecnico Campo');
        $comercial = $this->makeUser($tenant, 'Comercial User', 'Comercial');
        $logistica = $this->makeUser($tenant, 'Logistica User', EquipmentReplacement::ROLE_LOGISTICA);
        $originalAsset = $this->makeAsset($tenant, 'Gerador Original', Asset::STATUS_LOCADO);
        $replacementAsset = $this->makeAsset($tenant, 'Gerador Substituto', Asset::STATUS_DISPONIVEL);
        $order = $this->makeOrder($tenant, $originalAsset, $technician);

        $this->actingAs($technician);

        $replacement = EquipmentReplacement::create([
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $originalAsset->id,
            'requested_by_user_id' => $technician->id,
            'urgency' => EquipmentReplacement::URGENCY_NORMAL,
            'reason' => 'Fim de vida util.',
        ]);

        $replacement->identifyReplacement($replacementAsset, $comercial);
        $replacement->refresh();

        $this->assertEquals(EquipmentReplacement::STATUS_SUBSTITUTO_IDENTIFICADO, $replacement->status);
        $this->assertEquals($replacementAsset->id, $replacement->replacement_asset_id);
        $this->assertEquals($comercial->id, $replacement->identified_by_user_id);
        $this->assertNotNull($replacement->identified_at);
        $this->assertSame(0, $logistica->fresh()->notifications()->count());
    }

    public function test_starting_logistics_movements_links_them_and_notifies_logistica(): void
    {
        $tenant = $this->makeTenant();
        $technician = $this->makeUser($tenant, 'Tecnico Campo');
        $comercial = $this->makeUser($tenant, 'Comercial User', 'Comercial');
        $logistica = $this->makeUser($tenant, 'Logistica User', EquipmentReplacement::ROLE_LOGISTICA);
        $originalAsset = $this->makeAsset($tenant, 'Gerador Original', Asset::STATUS_LOCADO);
        $replacementAsset = $this->makeAsset($tenant, 'Gerador Substituto', Asset::STATUS_DISPONIVEL);
        $order = $this->makeOrder($tenant, $originalAsset, $technician);

        $this->actingAs($technician);

        $replacement = EquipmentReplacement::create([
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $originalAsset->id,
            'requested_by_user_id' => $technician->id,
            'urgency' => EquipmentReplacement::URGENCY_NORMAL,
            'reason' => 'Fim de vida util.',
        ]);

        $replacement->identifyReplacement($replacementAsset, $comercial);
        $replacement->startLogisticsMovements();
        $replacement->refresh();

        $this->assertEquals(EquipmentReplacement::STATUS_DESMOBILIZACAO_ANDAMENTO, $replacement->status);
        $this->assertNotNull($replacement->desmobilization_movement_id);
        $this->assertNotNull($replacement->mobilization_movement_id);
        $this->assertEquals(EquipmentMovement::TYPE_DESMOBILIZACAO, $replacement->desmobilizationMovement->type);
        $this->assertEquals($originalAsset->id, $replacement->desmobilizationMovement->asset_id);
        $this->assertEquals(EquipmentMovement::TYPE_MOBILIZACAO, $replacement->mobilizationMovement->type);
        $this->assertEquals($replacementAsset->id, $replacement->mobilizationMovement->asset_id);

        $notification = $logistica->fresh()->notifications()->first();
        $this->assertNotNull($notification);

        // Idempotente: chamar de novo nao duplica os movements.
        $replacement->startLogisticsMovements();
        $this->assertEquals(2, EquipmentMovement::count());
    }

    public function test_desmobilization_completion_advances_status_to_mobilizacao_andamento(): void
    {
        $tenant = $this->makeTenant();
        $technician = $this->makeUser($tenant, 'Tecnico Campo');
        $comercial = $this->makeUser($tenant, 'Comercial User', 'Comercial');
        $originalAsset = $this->makeAsset($tenant, 'Gerador Original', Asset::STATUS_LOCADO);
        $replacementAsset = $this->makeAsset($tenant, 'Gerador Substituto', Asset::STATUS_DISPONIVEL);
        $order = $this->makeOrder($tenant, $originalAsset, $technician);

        $this->actingAs($technician);

        $replacement = EquipmentReplacement::create([
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $originalAsset->id,
            'requested_by_user_id' => $technician->id,
            'urgency' => EquipmentReplacement::URGENCY_NORMAL,
            'reason' => 'Fim de vida util.',
        ]);
        $replacement->identifyReplacement($replacementAsset, $comercial);
        $replacement->startLogisticsMovements();
        $replacement->refresh();

        $replacement->desmobilizationMovement->update([
            'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'completed_at' => now(),
        ]);

        $this->assertEquals(EquipmentReplacement::STATUS_MOBILIZACAO_ANDAMENTO, $replacement->fresh()->status);
    }

    public function test_mobilization_completion_without_signature_does_not_finalize(): void
    {
        $tenant = $this->makeTenant();
        $technician = $this->makeUser($tenant, 'Tecnico Campo');
        $comercial = $this->makeUser($tenant, 'Comercial User', 'Comercial');
        $originalAsset = $this->makeAsset($tenant, 'Gerador Original', Asset::STATUS_LOCADO);
        $replacementAsset = $this->makeAsset($tenant, 'Gerador Substituto', Asset::STATUS_DISPONIVEL);
        $order = $this->makeOrder($tenant, $originalAsset, $technician);

        $this->actingAs($technician);

        $replacement = EquipmentReplacement::create([
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $originalAsset->id,
            'requested_by_user_id' => $technician->id,
            'urgency' => EquipmentReplacement::URGENCY_NORMAL,
            'reason' => 'Fim de vida util.',
        ]);
        $replacement->identifyReplacement($replacementAsset, $comercial);
        $replacement->startLogisticsMovements();
        $replacement->refresh();

        $replacement->desmobilizationMovement->update(['status' => EquipmentMovement::STATUS_CONCLUIDO, 'completed_at' => now()]);
        $replacement->mobilizationMovement->update(['status' => EquipmentMovement::STATUS_CONCLUIDO, 'completed_at' => now()]);

        $replacement->refresh();
        $this->assertEquals(EquipmentReplacement::STATUS_MOBILIZACAO_ANDAMENTO, $replacement->status);
        $this->assertNull($replacement->completed_at);
    }

    public function test_mobilization_completion_with_signature_finalizes_and_swaps_contract_and_asset_status(): void
    {
        $tenant = $this->makeTenant();
        $technician = $this->makeUser($tenant, 'Tecnico Campo');
        $comercial = $this->makeUser($tenant, 'Comercial User', 'Comercial');
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $originalAsset = $this->makeAsset($tenant, 'Guindaste Original', Asset::STATUS_LOCADO);
        $replacementAsset = $this->makeAsset($tenant, 'Guindaste Substituto', Asset::STATUS_DISPONIVEL);
        $originalAsset->update(['client_id' => $client->id]);
        $order = $this->makeOrder($tenant, $originalAsset, $technician);

        $this->actingAs($technician);

        $contract = Contract::create([
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
            'asset_id' => $originalAsset->id,
            'contract_number' => 'CT-'.uniqid(),
            'start_date' => now(),
            'is_active' => true,
        ]);

        $replacement = EquipmentReplacement::create([
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $originalAsset->id,
            'requested_by_user_id' => $technician->id,
            'urgency' => EquipmentReplacement::URGENCY_NORMAL,
            'reason' => 'Fim de vida util.',
        ]);
        $replacement->identifyReplacement($replacementAsset, $comercial);
        $replacement->startLogisticsMovements();
        $replacement->refresh();

        $replacement->desmobilizationMovement->update(['status' => EquipmentMovement::STATUS_CONCLUIDO, 'completed_at' => now()]);

        $replacement->mobilizationMovement->update([
            'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'completed_at' => now(),
            'client_signature' => 'data:image/png;base64,FAKE',
        ]);

        $replacement->refresh();
        $this->assertEquals(EquipmentReplacement::STATUS_CONCLUIDO, $replacement->status);
        $this->assertNotNull($replacement->completed_at);
        $this->assertNotNull($replacement->delivered_at);
        $this->assertSame('data:image/png;base64,FAKE', $replacement->client_signature);

        $contract->refresh();
        $this->assertEquals($replacementAsset->id, $contract->asset_id);

        $originalAsset->refresh();
        $replacementAsset->refresh();
        $this->assertEquals(Asset::STATUS_DISPONIVEL, $originalAsset->status);
        $this->assertNull($originalAsset->client_id);
        $this->assertEquals(Asset::STATUS_LOCADO, $replacementAsset->status);
        $this->assertEquals($client->id, $replacementAsset->client_id);

        $requesterNotifications = $technician->fresh()->notifications()
            ->where('data->title', 'Troca de equipamento concluída')
            ->count();
        $this->assertSame(1, $requesterNotifications);
    }

    public function test_completing_swap_without_active_contract_still_swaps_asset_status_directly(): void
    {
        $tenant = $this->makeTenant();
        $technician = $this->makeUser($tenant, 'Tecnico Campo');
        $comercial = $this->makeUser($tenant, 'Comercial User', 'Comercial');
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Teste']);
        $originalAsset = $this->makeAsset($tenant, 'Guindaste Proprio', Asset::STATUS_LOCADO);
        $replacementAsset = $this->makeAsset($tenant, 'Guindaste Substituto', Asset::STATUS_DISPONIVEL);
        $originalAsset->update(['client_id' => $client->id]);
        $order = $this->makeOrder($tenant, $originalAsset, $technician);

        $this->actingAs($technician);

        $replacement = EquipmentReplacement::create([
            'maintenance_order_id' => $order->id,
            'original_asset_id' => $originalAsset->id,
            'requested_by_user_id' => $technician->id,
            'urgency' => EquipmentReplacement::URGENCY_NORMAL,
            'reason' => 'Fim de vida util.',
        ]);
        $replacement->identifyReplacement($replacementAsset, $comercial);
        $replacement->startLogisticsMovements();
        $replacement->refresh();

        $replacement->desmobilizationMovement->update(['status' => EquipmentMovement::STATUS_CONCLUIDO, 'completed_at' => now()]);
        $replacement->mobilizationMovement->update([
            'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'completed_at' => now(),
            'client_signature' => 'data:image/png;base64,FAKE',
        ]);

        $this->assertEquals(EquipmentReplacement::STATUS_CONCLUIDO, $replacement->fresh()->status);

        $originalAsset->refresh();
        $replacementAsset->refresh();
        $this->assertEquals(Asset::STATUS_DISPONIVEL, $originalAsset->status);
        $this->assertNull($originalAsset->client_id);
        $this->assertEquals(Asset::STATUS_LOCADO, $replacementAsset->status);
        $this->assertEquals($client->id, $replacementAsset->client_id);
    }

    /**
     * Regressao: notifyRole() usava User::role($roleName) direto -- o
     * scope do Spatie resolve o papel por nome GLOBALMENTE
     * (Role::findByName), ignorando tenant_id. Com dois tenants tendo cada
     * um seu proprio registro "Comercial" (nomes iguais, ids diferentes),
     * sempre resolvia pro primeiro criado no banco inteiro, entao o
     * segundo tenant nunca notificava ninguem (lista vazia silenciosa, nao
     * uma excecao). Corrigido resolvendo a role certa (por tenant_id)
     * antes de consultar os usuarios.
     */
    public function test_notification_reaches_comercial_even_when_another_tenant_has_the_same_role_name_created_first(): void
    {
        $tenantA = $this->makeTenant();
        $this->makeUser($tenantA, 'Comercial Tenant A', 'Comercial');

        $tenantB = $this->makeTenant();
        $technicianB = $this->makeUser($tenantB, 'Tecnico B');
        $comercialB = $this->makeUser($tenantB, 'Comercial Tenant B', 'Comercial');
        $originalAssetB = $this->makeAsset($tenantB, 'Ativo B', Asset::STATUS_LOCADO);
        $orderB = $this->makeOrder($tenantB, $originalAssetB, $technicianB);

        $this->actingAs($technicianB);

        EquipmentReplacement::create([
            'maintenance_order_id' => $orderB->id,
            'original_asset_id' => $originalAssetB->id,
            'requested_by_user_id' => $technicianB->id,
            'urgency' => EquipmentReplacement::URGENCY_NORMAL,
            'reason' => 'Motivo B',
        ]);

        $this->assertSame(1, $comercialB->fresh()->notifications()->count());
    }

    public function test_replacement_does_not_leak_to_other_tenants(): void
    {
        $tenantA = $this->makeTenant();
        $technicianA = $this->makeUser($tenantA, 'Tecnico A');
        $originalAssetA = $this->makeAsset($tenantA, 'Ativo A', Asset::STATUS_LOCADO);
        $orderA = $this->makeOrder($tenantA, $originalAssetA, $technicianA);

        $this->actingAs($technicianA);
        EquipmentReplacement::create([
            'maintenance_order_id' => $orderA->id,
            'original_asset_id' => $originalAssetA->id,
            'requested_by_user_id' => $technicianA->id,
            'urgency' => EquipmentReplacement::URGENCY_NORMAL,
            'reason' => 'Motivo A',
        ]);

        $tenantB = $this->makeTenant();
        $technicianB = $this->makeUser($tenantB, 'Tecnico B');
        $this->actingAs($technicianB);

        $this->assertSame(0, EquipmentReplacement::count());
    }
}
