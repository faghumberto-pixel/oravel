<?php

namespace Tests\Feature;

use App\Filament\Client\Pages\SolicitarEquipamento;
use App\Filament\Client\Pages\SolicitarRetirada;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Contract;
use App\Models\EquipmentPickupRequest;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Portal do Cliente (2026-08-25/26): cliente abre pedido, operador aciona
 * manualmente -- sem automação de despacho. SolicitacaoLocacao reaproveita
 * o model existente (respeita a trava contract_id OU data_saida_prevista
 * já em SolicitacaoLocacao::booted()); EquipmentPickupRequest é tabela
 * nova (EquipmentMovement não tem client_id, ver comentário na migration).
 */
class ClientPortalEquipmentRequestTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClientAndAsset(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Logistica '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_contracts', 'tabela_solicitacao_locacao', 'tabela_equipment_pickup_requests'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Logistica '.uniqid(), 'slug' => 'tenant-logistica-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Logistica',
            'email' => 'logistica-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-logistica-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Logistica', 'status' => Asset::STATUS_LOCADO]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-LOG-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000,
        ]);

        return [$tenant, $client, $asset, $contract];
    }

    public function test_client_can_request_new_equipment(): void
    {
        [$tenant, $client] = $this->makeTenantWithClientAndAsset();

        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);

        $this->actingAs($client, 'client');

        Livewire::test(SolicitarEquipamento::class)
            ->fillForm([
                'category_id' => $category->id,
                'data_saida_prevista' => now()->addDays(5)->toDateString(),
                'observations' => 'Preciso para nova obra.',
            ])
            ->call('create');

        $solicitacao = SolicitacaoLocacao::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('customer_id', $client->id)
            ->first();

        $this->assertNotNull($solicitacao);
        $this->assertSame($category->id, $solicitacao->category_id);
        $this->assertSame('proposta_em_andamento', $solicitacao->status_comercial);
        $this->assertNull($solicitacao->contract_id);
    }

    public function test_client_can_request_equipment_pickup(): void
    {
        [$tenant, $client, $asset, $contract] = $this->makeTenantWithClientAndAsset();

        $this->actingAs($client, 'client');

        Livewire::test(SolicitarRetirada::class)
            ->fillForm([
                'asset_id' => $asset->id,
                'notes' => 'Uso encerrado, pode retirar.',
            ])
            ->call('create');

        $request = EquipmentPickupRequest::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)
            ->first();

        $this->assertNotNull($request);
        $this->assertSame($asset->id, $request->asset_id);
        $this->assertSame($contract->id, $request->contract_id);
        $this->assertSame(EquipmentPickupRequest::STATUS_SOLICITADO, $request->status);
    }

    public function test_client_only_sees_own_pickup_requests(): void
    {
        [$tenant, $client, $asset] = $this->makeTenantWithClientAndAsset();

        $ownRequest = EquipmentPickupRequest::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
        ]);

        $otherClient = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Outro Cliente',
            'email' => 'outro-pickup-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);
        $otherRequest = EquipmentPickupRequest::create([
            'tenant_id' => $tenant->id, 'client_id' => $otherClient->id, 'asset_id' => $asset->id,
        ]);

        $visible = EquipmentPickupRequest::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('client_id', $client->id)
            ->pluck('id');

        $this->assertContains($ownRequest->id, $visible);
        $this->assertNotContains($otherRequest->id, $visible);
    }
}
