<?php

namespace Tests\Feature;

use App\Models\ImplementationCharge;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use App\Services\SignatureService;
use App\Services\TenantTimeline;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Linha do tempo permanente do cliente: cadastro, contrato, assinatura, pagamentos. */
class TenantTimelineTest extends TestCase
{
    use DatabaseTransactions;

    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function plan(string $name = 'Plano Hist'): Plan
    {
        return Plan::create([
            'name' => $name.' '.uniqid(), 'price' => 600, 'base_price' => 600, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
    }

    private function tenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Cliente Hist '.uniqid(), 'slug' => 'cliente-hist-'.uniqid(), 'plan_id' => $this->plan()->id,
            'status' => 'trial', 'asaas_customer_id' => 'cus_hist', 'mrr_value' => 600, 'cpf_cnpj' => '12.925.998/0001-14',
        ]);
    }

    private function types(Tenant $tenant): array
    {
        return TenantEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->orderBy('occurred_at')->orderBy('created_at')->pluck('event_type')->all();
    }

    private function webhook(array $payload): void
    {
        config(['services.asaas.webhook_token' => 'tok']);
        $this->postJson('/api/webhooks/asaas', $payload, ['asaas-access-token' => 'tok'])->assertOk();
    }

    public function test_registration_and_contract_change_are_recorded(): void
    {
        $tenant = $this->tenant();
        $this->assertContains(TenantEvent::CLIENTE_CADASTRADO, $this->types($tenant));

        $novo = $this->plan('Plano Novo');
        $tenant->update(['plan_id' => $novo->id]);

        $evento = TenantEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('event_type', TenantEvent::CONTRATO_ALTERADO)->sole();
        $this->assertStringContainsString($novo->name, $evento->description);
    }

    public function test_link_and_signature_are_recorded_and_the_bell_rings_once(): void
    {
        Storage::fake();
        $super = User::create(['name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x12345678')]);
        config(['oravel.super_admins' => [$super->email]]);
        $tenant = $this->tenant();

        $link = app(SignatureService::class)->generateSignatureLink($tenant, ['name' => 'Marivan', 'email' => 'm@x.com']);
        $this->assertContains(TenantEvent::CONTRATO_LINK, $this->types($tenant));

        app(SignatureService::class)->signDocument(basename($link), ['signature_base64' => self::PNG, 'ip_address' => '1.2.3.4']);

        $this->assertContains(TenantEvent::CONTRATO_ASSINADO, $this->types($tenant));
        $this->assertSame(1, $super->refresh()->notifications()->count());

        // Histórico permanece mesmo se o aviso do sino for apagado.
        $super->notifications()->delete();
        $this->assertContains(TenantEvent::CONTRATO_ASSINADO, $this->types($tenant));
    }

    public function test_payment_events_are_recorded_once_and_access_release_is_logged(): void
    {
        $tenant = $this->tenant();
        $admin = User::create(['name' => 'Adm', 'email' => 'adm-'.uniqid().'@c.com', 'password' => bcrypt('x12345678'), 'tenant_id' => $tenant->id, 'role' => 'admin', 'hourly_rate' => 0]);
        $admin->forceFill(['is_approved' => false])->save();
        $payment = ['id' => 'pay_h1', 'customer' => 'cus_hist', 'value' => 600, 'invoiceUrl' => 'https://x'];

        $this->webhook(['event' => 'PAYMENT_CONFIRMED', 'payment' => $payment]);
        $this->webhook(['event' => 'PAYMENT_RECEIVED', 'payment' => $payment]); // mesmo pagamento

        $types = $this->types($tenant);
        $this->assertSame(1, array_count_values($types)[TenantEvent::MENSALIDADE_PAGA]);
        $this->assertContains(TenantEvent::ACESSO_LIBERADO, $types);
        $this->assertTrue((bool) $admin->refresh()->is_approved);

        $this->webhook(['event' => 'PAYMENT_OVERDUE', 'payment' => ['id' => 'pay_h2', 'customer' => 'cus_hist', 'value' => 600]]);
        $this->assertContains(TenantEvent::MENSALIDADE_ATRASADA, $this->types($tenant));
    }

    public function test_checkout_and_implementation_events_are_recorded(): void
    {
        $tenant = $this->tenant();
        $charge = ImplementationCharge::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'installment_number' => 1, 'installments_total' => 1, 'amount' => 900,
            'due_date' => now(), 'asaas_payment_id' => 'pay_impl_h',
        ]);

        $this->webhook(['event' => 'CHECKOUT_PAID', 'checkout' => ['id' => 'chk_h', 'externalReference' => $tenant->id]]);
        $this->webhook(['event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'pay_impl_h', 'customer' => 'cus_hist', 'value' => 900]]);

        $types = $this->types($tenant);
        $this->assertContains(TenantEvent::CHECKOUT_PAGO, $types);
        $this->assertContains(TenantEvent::IMPLANTACAO_PAGA, $types);
        $this->assertSame(ImplementationCharge::PAGO, $charge->refresh()->status);
    }

    public function test_same_fact_with_the_same_dedupe_key_is_recorded_only_once(): void
    {
        $tenant = $this->tenant();

        TenantTimeline::record($tenant, TenantEvent::ANOTACAO, 'A', dedupeKey: 'k1');
        TenantTimeline::record($tenant, TenantEvent::ANOTACAO, 'A de novo', dedupeKey: 'k1');
        TenantTimeline::record($tenant, TenantEvent::ANOTACAO, 'B');
        TenantTimeline::record($tenant, TenantEvent::ANOTACAO, 'C');

        $this->assertSame(3, TenantEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('event_type', TenantEvent::ANOTACAO)->count());
    }
}
