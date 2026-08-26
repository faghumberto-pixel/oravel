<?php

namespace Tests\Feature;

use App\Console\Commands\VerificarVencimentosCommand;
use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\EquipmentDamage;
use App\Models\EquipmentPickupRequest;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ClientAccountReceivableOverdueNotification;
use App\Notifications\ClientEquipmentDamageNotification;
use App\Notifications\ClientRequestStatusUpdatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Portal do Cliente Fase 2 (2026-08-26): notificações por e-mail pro
 * Client em 5 eventos. Gate comum: só dispara se
 * portal_access_enabled_at está preenchido. Cada teste tem um irmão de
 * isolamento (evento de um Client não notifica outro).
 */
class ClientPortalNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClient(bool $portalEnabled = true): array
    {
        $plan = Plan::create([
            'name' => 'Plano Notif '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_contracts', 'tabela_account_receivables', 'tabela_maintenance_orders', 'tabela_solicitacao_locacao', 'tabela_equipment_pickup_requests'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Notif '.uniqid(), 'slug' => 'tenant-notif-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Notif',
            'email' => 'notif-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => $portalEnabled ? now() : null,
        ]);

        return [$tenant, $client];
    }

    private function makeUser(Tenant $tenant): User
    {
        return User::create([
            'name' => 'User Notif', 'email' => 'user-notif-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
    }

    public function test_client_notified_when_account_receivable_overdue(): void
    {
        Notification::fake();
        [$tenant, $client] = $this->makeTenantWithClient();
        $this->makeUser($tenant); // VerificarVencimentosCommand só processa contas se houver User no tenant

        AccountReceivable::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'description' => 'Aluguel Agosto', 'amount' => 500,
            'due_date' => now()->subDays(5), 'status' => 'pendente',
        ]);

        $this->artisan(VerificarVencimentosCommand::class);

        Notification::assertSentTo($client, ClientAccountReceivableOverdueNotification::class);
    }

    public function test_client_without_portal_access_not_notified_for_overdue_account(): void
    {
        Notification::fake();
        [$tenant, $client] = $this->makeTenantWithClient(portalEnabled: false);
        $this->makeUser($tenant);

        AccountReceivable::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'description' => 'Aluguel Agosto', 'amount' => 500,
            'due_date' => now()->subDays(5), 'status' => 'pendente',
        ]);

        $this->artisan(VerificarVencimentosCommand::class);

        Notification::assertNotSentTo($client, ClientAccountReceivableOverdueNotification::class);
    }

    public function test_client_notified_when_equipment_damage_confirmed(): void
    {
        Notification::fake();
        [$tenant, $client] = $this->makeTenantWithClient();

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Notif', 'status' => Asset::STATUS_LOCADO]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto',
            'description' => 'Falha no motor',
        ]);

        $damage = EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id, 'asset_id' => $asset->id,
            'reported_by_user_id' => $this->makeUser($tenant)->id,
            'severity' => EquipmentDamage::SEVERITY_MODERADA,
            'description' => 'Motor com barulho anormal.',
            'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
        ]);

        $damage->update(['status' => EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL]);

        Notification::assertSentTo($client, ClientEquipmentDamageNotification::class);
    }

    public function test_client_not_notified_for_other_client_equipment_damage(): void
    {
        Notification::fake();
        [$tenant, $client] = $this->makeTenantWithClient();

        $otherClient = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Outro Cliente',
            'email' => 'outro-damage-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Outro', 'status' => Asset::STATUS_LOCADO]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'client_id' => $otherClient->id, 'asset_id' => $asset->id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto',
            'description' => 'Falha',
        ]);

        $damage = EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id, 'asset_id' => $asset->id,
            'reported_by_user_id' => $this->makeUser($tenant)->id,
            'severity' => EquipmentDamage::SEVERITY_LEVE,
            'description' => 'Amassado na lateral.',
            'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
        ]);

        $damage->update(['status' => EquipmentDamage::STATUS_AGUARDANDO_COMERCIAL]);

        Notification::assertNotSentTo($client, ClientEquipmentDamageNotification::class);
        Notification::assertSentTo($otherClient, ClientEquipmentDamageNotification::class);
    }

    public function test_client_notified_when_solicitacao_locacao_contrato_fechado(): void
    {
        Notification::fake();
        [$tenant, $client] = $this->makeTenantWithClient();

        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $this->makeUser($tenant)->id, 'customer_id' => $client->id,
            'category_id' => AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores'])->id,
            'data_saida_prevista' => now()->addDays(3), 'status_comercial' => 'proposta_em_andamento',
        ]);

        $solicitacao->update(['status_comercial' => 'contrato_fechado']);

        Notification::assertSentTo($client, ClientRequestStatusUpdatedNotification::class);
    }

    public function test_client_not_notified_when_solicitacao_locacao_still_internal_status(): void
    {
        Notification::fake();
        [$tenant, $client] = $this->makeTenantWithClient();

        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $this->makeUser($tenant)->id, 'customer_id' => $client->id,
            'category_id' => AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores'])->id,
            'data_saida_prevista' => now()->addDays(3), 'status_comercial' => 'proposta_em_andamento',
        ]);

        $solicitacao->update(['status_comercial' => 'reserva_manutencao']);

        Notification::assertNotSentTo($client, ClientRequestStatusUpdatedNotification::class);
    }

    public function test_client_notified_when_maintenance_order_concluded(): void
    {
        Notification::fake();
        [$tenant, $client] = $this->makeTenantWithClient();

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador OS', 'status' => Asset::STATUS_LOCADO]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto',
            'description' => 'Falha',
        ]);

        $order->update(['status' => 'Concluída']);

        Notification::assertSentTo($client, ClientRequestStatusUpdatedNotification::class);
    }

    public function test_client_notified_when_pickup_request_status_changes(): void
    {
        Notification::fake();
        [$tenant, $client] = $this->makeTenantWithClient();

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Pickup', 'status' => Asset::STATUS_LOCADO]);
        $request = EquipmentPickupRequest::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
        ]);

        $request->update(['status' => EquipmentPickupRequest::STATUS_AGENDADO]);

        Notification::assertSentTo($client, ClientRequestStatusUpdatedNotification::class);
    }

    public function test_client_not_notified_for_other_client_pickup_request(): void
    {
        Notification::fake();
        [$tenant, $client] = $this->makeTenantWithClient();

        $otherClient = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Outro Cliente Pickup',
            'email' => 'outro-pickup-notif-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Pickup 2', 'status' => Asset::STATUS_LOCADO]);
        $request = EquipmentPickupRequest::create([
            'tenant_id' => $tenant->id, 'client_id' => $otherClient->id, 'asset_id' => $asset->id,
        ]);

        $request->update(['status' => EquipmentPickupRequest::STATUS_CONCLUIDO]);

        Notification::assertNotSentTo($client, ClientRequestStatusUpdatedNotification::class);
        Notification::assertSentTo($otherClient, ClientRequestStatusUpdatedNotification::class);
    }
}
