<?php

namespace Tests\Feature;

use App\Filament\Resources\AccountReceivableResource;
use App\Filament\Resources\AccountReceivableResource\Pages\ManageAccountReceivables;
use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Item 9 da auditoria POP: a fila do Financeiro pra orçamentos aprovados
 * passa a ser a tela já existente "Contas a Receber", em vez de uma tela
 * nova -- Quote::forwardToFinanceiro() (POP 4) agora cria a
 * App\Models\AccountReceivable de verdade. Cobre a ponte pelo lado da
 * listagem (AccountReceivableResource), não só pelo lado do Quote (já
 * coberto em QuoteWorkflowTest/QuoteResourceTest).
 */
class QuoteFinanceiroQueueTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClient(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Fila Financeiro '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_quotes', 'tabela_account_receivables'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Fila Financeiro '.uniqid(), 'slug' => 'tenant-fila-financeiro-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $client = Client::create(['name' => 'Cliente Teste', 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $client, $admin];
    }

    private function makeApprovedQuote(Tenant $tenant, Client $client): Quote
    {
        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça X', 'quantity' => 1, 'unit_price' => 250,
        ]);
        $quote->send();
        $quote->approve();

        return $quote;
    }

    public function test_forwarded_quote_appears_in_the_account_receivable_list(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = $this->makeApprovedQuote($tenant, $client);
        $quote->forwardToFinanceiro();

        $response = $this->get(AccountReceivableResource::getUrl('index'));

        $response->assertOk();
        $response->assertSee('Orçamento');
        $response->assertSee('Cliente Teste');
        $response->assertSee('250');
    }

    public function test_pdf_action_is_only_visible_for_receivables_originated_from_a_quote(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = $this->makeApprovedQuote($tenant, $client);
        $quote->forwardToFinanceiro();

        AccountReceivable::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'description' => 'Conta avulsa, sem origem em orçamento', 'amount' => 500, 'due_date' => now()->addDays(10),
        ]);

        $fromQuote = $quote->receivable;
        $avulsa = AccountReceivable::where('description', 'Conta avulsa, sem origem em orçamento')->firstOrFail();

        Livewire::test(ManageAccountReceivables::class)
            ->assertTableActionVisible('baixarOrcamento', $fromQuote)
            ->assertTableActionHidden('baixarOrcamento', $avulsa);
    }

    public function test_quote_relation_and_account_receivable_relation_are_linked_both_ways(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = $this->makeApprovedQuote($tenant, $client);
        $quote->forwardToFinanceiro();

        $receivable = $quote->fresh()->receivable;
        $this->assertNotNull($receivable);
        $this->assertSame($quote->id, $receivable->quote->id);
    }

    public function test_manually_created_receivables_are_unaffected_and_have_no_quote(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $receivable = AccountReceivable::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id,
            'description' => 'Cobrança manual', 'amount' => 1000, 'due_date' => now()->addDays(5),
        ]);

        $this->assertNull($receivable->quote_id);
        $this->assertNull($receivable->quote);
    }
}
