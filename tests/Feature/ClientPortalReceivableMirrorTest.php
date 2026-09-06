<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portal do Cliente: download do "espelho de medição" (PDF) de uma
 * cobrança fechada. Mesmo princípio de isolamento de
 * ClientPortalContractPdfTest -- ClientReceivableMirrorController filtra
 * manualmente tenant_id+client_id, sem confiar em route-model-binding cego.
 */
class ClientPortalReceivableMirrorTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClientAndReceivable(string $label): array
    {
        $plan = Plan::create([
            'name' => 'Plano Espelho '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_account_receivables'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Espelho '.$label.' '.uniqid(), 'slug' => 'tenant-espelho-'.strtolower($label).'-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Espelho '.$label,
            'email' => 'espelho-'.strtolower($label).'-'.uniqid().'@teste.com', 'password' => 'senha123',
            'portal_access_enabled_at' => now(),
        ]);

        $receivable = AccountReceivable::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'description' => 'Medição '.$label, 'amount' => 1500,
            'due_date' => now()->addDays(10), 'status' => 'pendente',
            'mes' => now()->month, 'ano' => now()->year,
        ]);

        return [$tenant, $client, $receivable];
    }

    public function test_client_can_download_own_receivable_mirror(): void
    {
        [, $client, $receivable] = $this->makeTenantWithClientAndReceivable('A');

        $response = $this->actingAs($client, 'client')
            ->get(route('cliente.receivable.mirror', ['accountReceivable' => $receivable->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_client_cannot_download_other_client_receivable_mirror(): void
    {
        [, $clientA] = $this->makeTenantWithClientAndReceivable('A');
        [, , $receivableB] = $this->makeTenantWithClientAndReceivable('B');

        $response = $this->actingAs($clientA, 'client')
            ->get(route('cliente.receivable.mirror', ['accountReceivable' => $receivableB->id]));

        $response->assertNotFound();
    }

    public function test_guest_cannot_download_receivable_mirror(): void
    {
        [, , $receivable] = $this->makeTenantWithClientAndReceivable('A');

        $response = $this->get(route('cliente.receivable.mirror', ['accountReceivable' => $receivable->id]));

        $response->assertRedirect();
    }
}
