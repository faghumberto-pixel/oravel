<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource\Pages\CreateClient;
use App\Filament\Resources\ClientResource\Pages\EditClient;
use App\Filament\Resources\QuoteResource\Pages\EditQuote;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Item 8 da auditoria POP: Client só tinha email_financial/email_purchasing
 * (setoriais) -- faltava um e-mail de contato geral pra usar como
 * destinatário padrão em qualquer comunicação. QuoteResource\Pages\EditQuote
 * já pedia esse e-mail manualmente no "enviar" (item 5) com um comentário
 * explícito dizendo que isso ainda não existia; agora prefila com ele.
 */
class ClientContactEmailTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Contato '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_clients', 'tabela_quotes'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Contato '.uniqid(), 'slug' => 'tenant-contato-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_email_field_is_fillable_and_persists_when_creating_a_client_via_the_form(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CreateClient::class)
            ->fillForm([
                'name' => 'Cliente Contato Geral',
                'email' => 'contato@clienteteste.com.br',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $client = Client::where('name', 'Cliente Contato Geral')->firstOrFail();
        $this->assertSame('contato@clienteteste.com.br', $client->email);
    }

    public function test_email_field_persists_when_editing_an_existing_client(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Existente']);
        $this->assertNull($client->email);

        Livewire::test(EditClient::class, ['record' => $client->id])
            ->fillForm(['email' => 'novo-contato@clienteteste.com.br'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('novo-contato@clienteteste.com.br', $client->fresh()->email);
    }

    public function test_quote_send_action_prefills_the_clients_general_email_when_present(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Teste',
            'email' => 'geral@clienteteste.com.br', 'email_financial' => 'financeiro@clienteteste.com.br',
        ]);
        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create(['description' => 'Peça X', 'quantity' => 1, 'unit_price' => 100]);

        Mail::fake();

        Livewire::test(EditQuote::class, ['record' => $quote->id])
            ->mountAction('enviar')
            ->assertActionDataSet(['client_email' => 'geral@clienteteste.com.br']);
    }

    public function test_quote_send_action_falls_back_to_email_financial_when_general_email_is_blank(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente Teste',
            'email_financial' => 'financeiro@clienteteste.com.br',
        ]);
        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create(['description' => 'Peça X', 'quantity' => 1, 'unit_price' => 100]);

        Mail::fake();

        Livewire::test(EditQuote::class, ['record' => $quote->id])
            ->mountAction('enviar')
            ->assertActionDataSet(['client_email' => 'financeiro@clienteteste.com.br']);
    }
}
