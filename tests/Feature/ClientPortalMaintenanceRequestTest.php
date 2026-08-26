<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\AbrirChamado;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Portal do Cliente (2026-08-25/26): abertura de chamado é caminho novo --
 * antes deste portal, nenhum Observer/endpoint externo criava
 * MaintenanceOrder (confirmado por investigação: só Filament Pages
 * internas e um Console Command). Cobre criação com client_id/tenant_id
 * corretos e leitura isolada de "Minhas OS".
 */
class ClientPortalMaintenanceRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClientAndAsset(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Chamado '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_contracts', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Chamado '.uniqid(), 'slug' => 'tenant-chamado-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Chamado',
            'email' => 'chamado-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Chamado', 'status' => Asset::STATUS_LOCADO]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-CHAMADO-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000,
        ]);

        return [$tenant, $client, $asset, $contract];
    }

    public function test_client_can_open_a_maintenance_request_for_their_own_asset(): void
    {
        [$tenant, $client, $asset] = $this->makeTenantWithClientAndAsset();

        $this->actingAs($client, 'client');

        Livewire::test(AbrirChamado::class)
            ->fillForm([
                'asset_id' => $asset->id,
                'description' => 'Equipamento não liga.',
            ])
            ->call('create');

        $order = MaintenanceOrder::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)
            ->first();

        $this->assertNotNull($order);
        $this->assertSame($asset->id, $order->asset_id);
        $this->assertSame(MaintenanceOrder::TYPE_CORRECTIVE, $order->maintenance_type);
        $this->assertSame('Aberto', $order->status);
        $this->assertSame('Equipamento não liga.', $order->description);
    }

    public function test_client_only_sees_own_maintenance_orders(): void
    {
        [$tenant, $client, $asset] = $this->makeTenantWithClientAndAsset();

        $ownOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto',
            'description' => 'Chamado do próprio cliente',
        ]);

        $otherClient = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Outro Cliente',
            'email' => 'outro-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);
        $otherOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'client_id' => $otherClient->id, 'asset_id' => $asset->id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'status' => 'Aberto',
            'description' => 'Chamado de outro cliente',
        ]);

        $visible = MaintenanceOrder::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id)
            ->pluck('id');

        $this->assertContains($ownOrder->id, $visible);
        $this->assertNotContains($otherOrder->id, $visible);
    }
}
