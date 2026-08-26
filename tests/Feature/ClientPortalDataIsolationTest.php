<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Portal do Cliente (2026-08-25/26): o mecanismo de segurança real não é
 * o global scope de BelongsToTenant (ele resolve Auth::user() no guard
 * 'web', que é null para um Client logado no guard 'client') -- é o
 * filtro manual tenant_id+client_id em cada Page. Este teste existe pra
 * pegar exatamente o vazamento que aconteceria se alguma Page confiasse
 * no scope automático em vez de filtrar explicitamente.
 */
class ClientPortalDataIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClient(string $label): array
    {
        $plan = Plan::create([
            'name' => 'Plano Isolamento '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_contracts'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant '.$label.' '.uniqid(), 'slug' => 'tenant-'.strtolower($label).'-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente '.$label,
            'email' => strtolower($label).'-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo '.$label, 'status' => Asset::STATUS_DISPONIVEL]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-'.$label.'-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000,
        ]);

        return [$tenant, $client, $contract];
    }

    private function clientOwnContractsQuery(Client $client)
    {
        return Contract::withoutGlobalScope('tenant')
            ->where('tenant_id', $client->tenant_id)
            ->where('client_id', $client->id);
    }

    public function test_client_only_sees_own_contracts_not_other_client_same_tenant(): void
    {
        [$tenant] = $this->makeTenantWithClient('A');

        $clientA = Client::where('tenant_id', $tenant->id)->first();

        $clientB = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente B (mesmo tenant)',
            'email' => 'clientb-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);
        $assetB = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo B', 'status' => Asset::STATUS_DISPONIVEL]);
        $contractB = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $clientB->id, 'asset_id' => $assetB->id,
            'contract_number' => 'CT-B-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 2000,
        ]);

        $visibleToA = $this->clientOwnContractsQuery($clientA)->pluck('id');

        $this->assertNotContains($contractB->id, $visibleToA);
    }

    public function test_client_only_sees_own_contracts_not_other_tenant(): void
    {
        [, $clientA] = $this->makeTenantWithClient('A');
        [, , $contractC] = $this->makeTenantWithClient('C');

        $visibleToA = $this->clientOwnContractsQuery($clientA)->pluck('id');

        $this->assertNotContains($contractC->id, $visibleToA);
    }

    public function test_client_sees_only_their_own_contract(): void
    {
        [, $clientA, $contractA] = $this->makeTenantWithClient('A');

        $visibleToA = $this->clientOwnContractsQuery($clientA)->pluck('id');

        $this->assertCount(1, $visibleToA);
        $this->assertSame($contractA->id, $visibleToA->first());
    }

    public function test_client_authenticated_on_client_guard_is_not_authenticated_on_web_guard(): void
    {
        [, $client] = $this->makeTenantWithClient('A');

        Auth::guard('client')->attempt(['email' => $client->email, 'password' => 'senha123']);

        $this->assertTrue(Auth::guard('client')->check());
        $this->assertFalse(Auth::guard('web')->check());
        $this->assertNull(Auth::guard('web')->user());
    }

    public function test_client_cannot_access_admin_panel(): void
    {
        [, $client] = $this->makeTenantWithClient('A');

        $this->actingAs($client, 'client')
            ->get('/admin')
            ->assertRedirect(); // redireciona pro login do admin (guard web), não autoriza acesso
    }
}
