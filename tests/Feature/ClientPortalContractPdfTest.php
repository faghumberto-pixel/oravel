<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portal do Cliente Fase 2 (2026-08-26): download de contrato em PDF.
 * O mais importante é o teste de isolamento -- ContractPdfController não
 * usa route-model-binding cego, filtra manualmente tenant_id+client_id.
 */
class ClientPortalContractPdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClientAndContract(string $label): array
    {
        $plan = Plan::create([
            'name' => 'Plano PDF '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_contracts'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant PDF '.$label.' '.uniqid(), 'slug' => 'tenant-pdf-'.strtolower($label).'-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente PDF '.$label,
            'email' => 'pdf-'.strtolower($label).'-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo PDF '.$label, 'status' => Asset::STATUS_DISPONIVEL]);

        $contract = Contract::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'asset_id' => $asset->id,
            'contract_number' => 'CT-PDF-'.$label.'-'.uniqid(), 'start_date' => now(),
            'billing_type' => Contract::BILLING_MENSAL_FIXO, 'price' => 1000,
        ]);

        return [$tenant, $client, $contract];
    }

    public function test_client_can_download_own_contract_pdf(): void
    {
        [, $client, $contract] = $this->makeTenantWithClientAndContract('A');

        $response = $this->actingAs($client, 'client')
            ->get(route('cliente.contracts.pdf', ['contract' => $contract->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_client_cannot_download_other_client_contract_pdf(): void
    {
        [, $clientA] = $this->makeTenantWithClientAndContract('A');
        [, , $contractB] = $this->makeTenantWithClientAndContract('B');

        $response = $this->actingAs($clientA, 'client')
            ->get(route('cliente.contracts.pdf', ['contract' => $contractB->id]));

        $response->assertNotFound();
    }

    public function test_guest_cannot_download_contract_pdf(): void
    {
        [, , $contract] = $this->makeTenantWithClientAndContract('A');

        $response = $this->get(route('cliente.contracts.pdf', ['contract' => $contract->id]));

        $response->assertRedirect();
    }
}
