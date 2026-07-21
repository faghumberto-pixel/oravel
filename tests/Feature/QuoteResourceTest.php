<?php

namespace Tests\Feature;

use App\Filament\Resources\QuoteResource\Pages\CreateQuote;
use App\Filament\Resources\QuoteResource\Pages\EditQuote;
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

class QuoteResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClient(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Teste Quote Resource '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_quotes'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Teste Quote Resource', 'slug' => 'tenant-quote-res-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $client = Client::create(['name' => 'Cliente Teste', 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $client, $admin];
    }

    public function test_can_create_quote_with_items_via_the_form(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        Livewire::test(CreateQuote::class, ['tenant' => $tenant->slug])
            ->fillForm([
                'client_id' => $client->id,
                'type' => Quote::TYPE_INTERNO,
                'items' => [
                    ['type' => QuoteItem::TYPE_PECA, 'description' => 'Filtro de óleo', 'quantity' => 2, 'unit_price' => 50],
                    ['type' => QuoteItem::TYPE_SERVICO, 'description' => 'Mão de obra', 'quantity' => 1, 'unit_price' => 300],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $quote = Quote::where('client_id', $client->id)->sole();
        $this->assertCount(2, $quote->items);
        $this->assertSame('400.00', $quote->total_value);
        $this->assertSame(Quote::STATUS_RASCUNHO, $quote->status);
    }

    public function test_technical_report_required_for_third_party_quotes(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        Livewire::test(CreateQuote::class, ['tenant' => $tenant->slug])
            ->fillForm([
                'client_id' => $client->id,
                'type' => Quote::TYPE_TERCEIRO,
                'items' => [
                    ['type' => QuoteItem::TYPE_SERVICO, 'description' => 'Reparo externo', 'quantity' => 1, 'unit_price' => 500],
                ],
            ])
            ->call('create')
            ->assertHasFormErrors(['technical_report', 'third_party_supplier_id']);
    }

    public function test_edit_page_stage_actions_gate_correctly(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça X', 'quantity' => 1, 'unit_price' => 100,
        ]);

        $component = Livewire::test(EditQuote::class, ['record' => $quote->getKey(), 'tenant' => $tenant->slug]);
        $component->assertActionVisible('enviar');
        $component->assertActionHidden('aprovar');

        $component->callAction('enviar');
        $this->assertSame(Quote::STATUS_ENVIADO, $quote->fresh()->status);

        $component = Livewire::test(EditQuote::class, ['record' => $quote->getKey(), 'tenant' => $tenant->slug]);
        $component->assertActionHidden('enviar');
        $component->assertActionVisible('aprovar');
        $component->assertActionVisible('reprovar');

        $component->callAction('aprovar');
        $this->assertSame(Quote::STATUS_APROVADO, $quote->fresh()->status);

        $component = Livewire::test(EditQuote::class, ['record' => $quote->getKey(), 'tenant' => $tenant->slug]);
        $component->assertActionVisible('encaminhar_financeiro');
        $component->assertActionVisible('concluir');

        $component->callAction('encaminhar_financeiro');
        $this->assertNotNull($quote->fresh()->financeiro_forwarded_at);

        $component->callAction('concluir');
        $this->assertSame(Quote::STATUS_CONCLUIDO, $quote->fresh()->status);
    }

    public function test_reject_action_requires_reason(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça X', 'quantity' => 1, 'unit_price' => 100,
        ]);
        $quote->send();

        Livewire::test(EditQuote::class, ['record' => $quote->getKey(), 'tenant' => $tenant->slug])
            ->callAction('reprovar', data: ['reason' => 'Valor muito alto.']);

        $quote->refresh();
        $this->assertSame(Quote::STATUS_REPROVADO, $quote->status);
        $this->assertSame('Valor muito alto.', $quote->rejection_reason);
    }

    public function test_pdf_download_route_returns_a_real_pdf(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_SERVICO,
            'description' => 'Serviço X', 'quantity' => 1, 'unit_price' => 250,
        ]);

        $response = $this->get(route('quotes.pdf', $quote));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
