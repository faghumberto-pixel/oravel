<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\AsaasService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O Asaas cria os avisos por e-mail ao cliente DESLIGADOS quando o cliente nasce sem
 * e-mail, e colocar o e-mail depois não os liga (achado em PROD, Topmixx, 2026-10-01).
 */
class AsaasCustomerNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(array $extra = []): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Avisos '.uniqid(), 'price' => 600, 'base_price' => 600, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        return Tenant::create(array_merge([
            'name' => 'Cliente Avisos '.uniqid(), 'slug' => 'cliente-avisos-'.uniqid(), 'plan_id' => $plan->id,
            'status' => 'trial', 'cpf_cnpj' => '12.925.998/0001-14',
        ], $extra));
    }

    private function fakeAsaas(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        $notifications = [
            ['id' => 'not_created', 'event' => 'PAYMENT_CREATED', 'emailEnabledForCustomer' => false],
            ['id' => 'not_updated', 'event' => 'PAYMENT_UPDATED', 'emailEnabledForCustomer' => false],
            ['id' => 'not_received', 'event' => 'PAYMENT_RECEIVED', 'emailEnabledForCustomer' => true], // já ligado: não mexe
            ['id' => 'not_overdue', 'event' => 'PAYMENT_OVERDUE', 'emailEnabledForCustomer' => false],
            ['id' => 'not_linha', 'event' => 'SEND_LINHA_DIGITAVEL', 'emailEnabledForCustomer' => false],
        ];

        Http::fake(function (Request $request) use ($notifications) {
            $url = $request->url();

            return match (true) {
                $request->method() === 'POST' && str_ends_with($url, '/customers') => Http::response(['id' => 'cus_novo'], 200),
                $request->method() === 'GET' && str_contains($url, '/notifications') => Http::response(['data' => $notifications], 200),
                default => Http::response(['ok' => true], 200),
            };
        });
    }

    private function putUrls(): array
    {
        return Http::recorded()->filter(fn ($pair) => $pair[0]->method() === 'PUT')->map(fn ($pair) => $pair[0]->url())->values()->all();
    }

    public function test_new_customer_with_email_gets_email_notifications_turned_on(): void
    {
        $this->fakeAsaas();
        $tenant = $this->tenant(['email_contato' => 'cliente@topmixx.com.br']);

        app(AsaasService::class)->syncTenantCustomer($tenant);

        $puts = implode(' ', $this->putUrls());
        foreach (['not_created', 'not_overdue', 'not_linha'] as $id) {
            $this->assertStringContainsString("/notifications/{$id}", $puts);
        }
        $this->assertStringNotContainsString('not_updated', $puts);   // PAYMENT_UPDATED fica desligado
        $this->assertStringNotContainsString('not_received', $puts);  // já estava ligado
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/notifications/not_created')
            && $r['emailEnabledForCustomer'] === true && $r['smsEnabledForCustomer'] === false);
        $this->assertSame('cus_novo', $tenant->refresh()->asaas_customer_id);
    }

    public function test_customer_without_email_gets_no_notification_calls(): void
    {
        $this->fakeAsaas();

        app(AsaasService::class)->syncTenantCustomer($this->tenant());

        $this->assertSame([], $this->putUrls());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/notifications'));
    }

    public function test_existing_customer_gets_email_and_notifications_fixed_on_resync(): void
    {
        $this->fakeAsaas();
        $tenant = $this->tenant(['asaas_customer_id' => 'cus_existente', 'email_contato' => 'marivan@topmixx.com.br']);

        app(AsaasService::class)->syncTenantCustomer($tenant);

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/customers')); // não duplica o cliente
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/customers/cus_existente') && $r['email'] === 'marivan@topmixx.com.br');
        $this->assertStringContainsString('/notifications/not_created', implode(' ', $this->putUrls()));
    }

    public function test_changing_the_contact_email_updates_the_asaas_customer(): void
    {
        $this->fakeAsaas();
        $tenant = $this->tenant(['asaas_customer_id' => 'cus_existente']);

        $tenant->update(['email_contato' => 'novo@topmixx.com.br']);

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/customers/cus_existente') && $r['email'] === 'novo@topmixx.com.br');
        $this->assertStringContainsString('/notifications/not_overdue', implode(' ', $this->putUrls()));
    }
}
