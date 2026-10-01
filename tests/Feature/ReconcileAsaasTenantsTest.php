<?php

namespace Tests\Feature;

use App\Models\ImplementationCharge;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Rede de segurança do webhook: pagamentos que o Asaas recebeu e o sistema não soube. */
class ReconcileAsaasTenantsTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(): Tenant
    {
        config(['services.asaas.api_key' => 'k', 'services.asaas.webhook_token' => 'tok']);
        $plan = Plan::create(['name' => 'Plano Rec '.uniqid(), 'price' => 600, 'base_price' => 600, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => []]);

        return Tenant::create(['name' => 'Cliente Rec', 'slug' => 'cliente-rec-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'trial', 'asaas_customer_id' => 'cus_rec']);
    }

    private function fakePayments(array $payments): void
    {
        Http::fake(['*/payments*' => Http::response(['data' => $payments], 200)]);
    }

    public function test_missed_implementation_payment_is_registered_once(): void
    {
        $tenant = $this->tenant();
        $charge = ImplementationCharge::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id, 'installment_number' => 1, 'installments_total' => 1, 'amount' => 900,
            'due_date' => now(), 'asaas_payment_id' => 'pay_impl_rec',
        ]);
        $this->fakePayments([['id' => 'pay_impl_rec', 'customer' => 'cus_rec', 'status' => 'RECEIVED', 'value' => 900]]);

        $this->artisan('asaas:reconcile-tenants')->expectsOutputToContain('1 evento(s)')->assertSuccessful();
        $this->assertSame(ImplementationCharge::PAGO, $charge->refresh()->status);

        $this->artisan('asaas:reconcile-tenants')->expectsOutputToContain('0 evento(s)')->assertSuccessful();
        $this->assertSame(1, TenantEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('event_type', TenantEvent::IMPLANTACAO_PAGA)->count());
    }

    public function test_missed_subscription_payment_marks_client_paid_and_releases_access_once(): void
    {
        $tenant = $this->tenant();
        $admin = User::create(['name' => 'Adm', 'email' => 'adm-'.uniqid().'@c.com', 'password' => bcrypt('x12345678'), 'tenant_id' => $tenant->id, 'role' => 'admin', 'hourly_rate' => 0]);
        $admin->forceFill(['is_approved' => false])->save();
        $this->fakePayments([['id' => 'pay_sub_rec', 'customer' => 'cus_rec', 'status' => 'CONFIRMED', 'value' => 600]]);

        $this->artisan('asaas:reconcile-tenants')->assertSuccessful();
        $this->artisan('asaas:reconcile-tenants')->expectsOutputToContain('0 evento(s)')->assertSuccessful();

        $this->assertSame('em_dia', $tenant->refresh()->asaas_payment_status);
        $this->assertTrue((bool) $admin->refresh()->is_approved);
        $this->assertSame(1, TenantEvent::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('event_type', TenantEvent::MENSALIDADE_PAGA)->count());
    }

    public function test_dry_run_changes_nothing_and_missing_credentials_do_nothing(): void
    {
        $tenant = $this->tenant();
        $this->fakePayments([['id' => 'pay_sub_x', 'customer' => 'cus_rec', 'status' => 'RECEIVED', 'value' => 600]]);

        $this->artisan('asaas:reconcile-tenants', ['--dry-run' => true])->expectsOutputToContain('simulação')->assertSuccessful();
        $this->assertNull($tenant->refresh()->asaas_payment_status);

        config(['services.asaas.api_key' => null]);
        $this->artisan('asaas:reconcile-tenants')->assertFailed();
    }
}
