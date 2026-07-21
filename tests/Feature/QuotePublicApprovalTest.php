<?php

namespace Tests\Feature;

use App\Filament\Resources\QuoteResource\Pages\EditQuote;
use App\Mail\GenericPdfMail;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class QuotePublicApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClient(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Teste Aprovacao '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_quotes'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Teste Aprovacao', 'slug' => 'tenant-aprovacao-'.uniqid(),
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

    public function test_sending_quote_emails_client_with_approval_link(): void
    {
        Mail::fake();
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça X', 'quantity' => 1, 'unit_price' => 100,
        ]);

        Livewire::test(EditQuote::class, ['record' => $quote->getKey(), 'tenant' => $tenant->slug])
            ->callAction('enviar', data: ['client_email' => 'cliente@exemplo.com.br']);

        $quote->refresh();
        $this->assertSame(Quote::STATUS_ENVIADO, $quote->status);
        $this->assertNotNull($quote->approval_token);

        Mail::assertSent(GenericPdfMail::class, function (GenericPdfMail $mail) use ($quote) {
            return $mail->hasTo('cliente@exemplo.com.br')
                && str_contains($mail->bodyText, $quote->approval_token)
                && $mail->pdfContent !== null;
        });
    }

    public function test_public_page_shows_quote_and_marks_viewed(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_SERVICO,
            'description' => 'Serviço Y', 'quantity' => 1, 'unit_price' => 200,
        ]);
        $quote->send();

        $this->assertNull($quote->client_viewed_at);

        $response = $this->get(route('quotes.public-approval', $quote->approval_token));

        $response->assertOk();
        $response->assertSee('Cliente Teste');
        $response->assertSee('Serviço Y');

        $this->assertNotNull($quote->fresh()->client_viewed_at);
    }

    public function test_invalid_token_shows_friendly_message_not_error(): void
    {
        $response = $this->get(route('quotes.public-approval', 'token-invalido-qualquer'));

        $response->assertOk();
        $response->assertSee('Link inválido');
    }

    public function test_client_can_approve_via_public_link_without_login(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça Z', 'quantity' => 1, 'unit_price' => 500,
        ]);
        $quote->send();

        // sem actingAs() nenhum -- exatamente como o cliente final faria
        $response = $this->post(route('quotes.public-approve', $quote->approval_token));

        $response->assertRedirect(route('quotes.public-approval', $quote->approval_token));
        $this->assertSame(Quote::STATUS_APROVADO, $quote->fresh()->status);
    }

    public function test_client_can_reject_via_public_link_with_reason(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça W', 'quantity' => 1, 'unit_price' => 999,
        ]);
        $quote->send();

        $response = $this->post(route('quotes.public-reject', $quote->approval_token), [
            'reason' => 'Preço acima do combinado.',
        ]);

        $response->assertRedirect();
        $quote->refresh();
        $this->assertSame(Quote::STATUS_REPROVADO, $quote->status);
        $this->assertSame('Preço acima do combinado.', $quote->rejection_reason);
    }

    public function test_reject_without_reason_is_rejected_by_validation(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $quote = Quote::create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);
        $quote->items()->create([
            'tenant_id' => $tenant->id, 'type' => QuoteItem::TYPE_PECA,
            'description' => 'Peça V', 'quantity' => 1, 'unit_price' => 10,
        ]);
        $quote->send();

        $response = $this->post(route('quotes.public-reject', $quote->approval_token), []);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(Quote::STATUS_ENVIADO, $quote->fresh()->status);
    }
}
