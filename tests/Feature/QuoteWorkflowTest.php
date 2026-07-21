<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClient(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Teste Quote '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_quotes'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Teste Quote', 'slug' => 'tenant-quote-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Cliente Teste', 'tenant_id' => $tenant->id,
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $client, $admin];
    }

    public function test_cannot_send_quote_without_items(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->expectException(\RuntimeException::class);
        $quote->send();
    }

    public function test_full_quote_lifecycle_send_approve_forward_complete(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Filtro de óleo', 'quantity' => 2, 'unit_price' => 50,
        ]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_SERVICO,
            'description' => 'Mão de obra', 'quantity' => 1, 'unit_price' => 300,
        ]);

        // observer recalcula total_value sozinho a cada item salvo
        $this->assertSame('400.00', $quote->fresh()->total_value);

        $quote->send();
        $this->assertSame(Quote::STATUS_ENVIADO, $quote->status);
        $this->assertNotNull($quote->sent_at);
        $this->assertNotNull($quote->approval_token);

        $quote->markViewedByClient();
        $this->assertNotNull($quote->client_viewed_at);
        $firstViewedAt = $quote->client_viewed_at;
        $quote->markViewedByClient(); // segunda chamada nao pode sobrescrever
        $this->assertEquals($firstViewedAt, $quote->fresh()->client_viewed_at);

        $quote->approve();
        $this->assertSame(Quote::STATUS_APROVADO, $quote->status);
        $this->assertNotNull($quote->client_responded_at);

        $quote->forwardToFinanceiro();
        $this->assertNotNull($quote->financeiro_forwarded_at);

        $receivable = AccountReceivable::where('quote_id', $quote->id)->firstOrFail();
        $this->assertSame($tenant->id, $receivable->tenant_id);
        $this->assertSame($client->id, $receivable->client_id);
        $this->assertSame('400.00', $receivable->amount);
        $this->assertSame('pendente', $receivable->status);
        $this->assertNotNull($receivable->due_date);

        $quote->complete();
        $this->assertSame(Quote::STATUS_CONCLUIDO, $quote->status);
        $this->assertNotNull($quote->completed_at);
        $this->assertFalse($quote->isOpen());
    }

    public function test_quote_rejection_records_reason(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça X', 'quantity' => 1, 'unit_price' => 100,
        ]);
        $quote->send();

        $quote->reject('Valor acima do orçamento disponível.');

        $this->assertSame(Quote::STATUS_REPROVADO, $quote->status);
        $this->assertSame('Valor acima do orçamento disponível.', $quote->rejection_reason);
        $this->assertFalse($quote->isOpen());
    }

    public function test_cannot_approve_quote_still_in_draft(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->expectException(\RuntimeException::class);
        $quote->approve();
    }

    public function test_cannot_forward_to_financeiro_before_approval(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça X', 'quantity' => 1, 'unit_price' => 100,
        ]);
        $quote->send();

        $this->expectException(\RuntimeException::class);
        $quote->forwardToFinanceiro();
    }

    public function test_forwarding_to_financeiro_twice_throws_and_does_not_duplicate_the_receivable(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça X', 'quantity' => 1, 'unit_price' => 100,
        ]);
        $quote->send();
        $quote->approve();
        $quote->forwardToFinanceiro();

        $this->expectException(\RuntimeException::class);
        $quote->forwardToFinanceiro();
    }

    public function test_forward_to_financeiro_honors_a_custom_due_date(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça X', 'quantity' => 1, 'unit_price' => 100,
        ]);
        $quote->send();
        $quote->approve();

        $dueDate = now()->addDays(15)->startOfDay();
        $quote->forwardToFinanceiro($dueDate);

        $receivable = AccountReceivable::where('quote_id', $quote->id)->firstOrFail();
        $this->assertTrue($dueDate->isSameDay($receivable->due_date));
    }

    public function test_deleting_item_recalculates_total(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $item1 = $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça X', 'quantity' => 1, 'unit_price' => 100,
        ]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_SERVICO,
            'description' => 'Serviço Y', 'quantity' => 1, 'unit_price' => 50,
        ]);

        $this->assertSame('150.00', $quote->fresh()->total_value);

        $item1->delete();

        $this->assertSame('50.00', $quote->fresh()->total_value);
    }

    public function test_quote_items_are_tenant_scoped(): void
    {
        [$tenantA, $clientA, $adminA] = $this->makeTenantWithClient();
        [$tenantB, $clientB, $adminB] = $this->makeTenantWithClient();

        $this->actingAs($adminA);
        $quoteA = Quote::create(['tenant_id' => $tenantA->id, 'client_id' => $clientA->id]);
        $quoteA->items()->create([
            'tenant_id' => $tenantA->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça tenant A', 'quantity' => 1, 'unit_price' => 10,
        ]);

        $this->actingAs($adminB);
        $this->assertSame(0, Quote::count(), 'Tenant B não pode ver orçamentos do tenant A.');
    }
}
