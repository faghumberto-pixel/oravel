<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Central\Resources\TenantResource\Pages\EditTenant;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AsaasService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AsaasTenantSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(?string $cpfCnpj = '12345678901'): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Asaas Sync '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [],
        ]);

        return Tenant::create([
            'name' => 'Tenant Asaas Sync '.uniqid(), 'slug' => 'tenant-asaas-sync-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
            'cpf_cnpj' => $cpfCnpj,
        ]);
    }

    public function test_sync_creates_asaas_customer_and_marks_tenant_as_synced(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*' => Http::response(['id' => 'cus_novo123'], 200),
        ]);

        $tenant = $this->makeTenant('123.456.789-01');

        app(AsaasService::class)->syncTenantCustomer($tenant);
        $tenant->refresh();

        $this->assertSame('cus_novo123', $tenant->asaas_customer_id);
        $this->assertSame('synced', $tenant->asaas_status);
        $this->assertNotNull($tenant->asaas_synced_at);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/customers')
            && $request['cpfCnpj'] === '12345678901');
    }

    public function test_sync_customer_also_creates_subscription_when_mrr_is_set(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*/customers' => Http::response(['id' => 'cus_com_mrr'], 200),
            'sandbox.asaas.com/*/subscriptions' => Http::response(['id' => 'sub_novo456'], 200),
        ]);

        $plan = Plan::create([
            'name' => 'Plano Mensal '.uniqid(), 'price' => 297, 'base_price' => 297, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Com MRR '.uniqid(), 'slug' => 'tenant-com-mrr-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
            'cpf_cnpj' => '123.456.789-01', 'mrr_value' => 297,
        ]);

        app(AsaasService::class)->syncTenantCustomer($tenant);
        $tenant->refresh();

        $this->assertSame('sub_novo456', $tenant->asaas_subscription_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/subscriptions')
            && $request['customer'] === 'cus_com_mrr'
            && $request['value'] === 297.0
            && $request['cycle'] === 'MONTHLY'
            && $request['billingType'] === 'UNDEFINED');
    }

    public function test_sync_subscription_is_skipped_without_mrr(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*/customers' => Http::response(['id' => 'cus_sem_mrr'], 200),
        ]);

        $tenant = $this->makeTenant('123.456.789-01');
        $tenant->update(['mrr_value' => 0]);

        app(AsaasService::class)->syncTenantCustomer($tenant);
        $tenant->refresh();

        $this->assertNull($tenant->asaas_subscription_id);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/subscriptions'));
    }

    public function test_sync_without_cpf_cnpj_marks_as_pending_without_calling_api(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake();

        $tenant = $this->makeTenant(null);

        app(AsaasService::class)->syncTenantCustomer($tenant);
        $tenant->refresh();

        $this->assertSame('pending', $tenant->asaas_status);
        $this->assertNull($tenant->asaas_customer_id);
        Http::assertNothingSent();
    }

    public function test_sync_without_api_key_marks_as_error_without_calling_api(): void
    {
        config(['services.asaas.api_key' => null]);
        Http::fake();

        $tenant = $this->makeTenant();

        app(AsaasService::class)->syncTenantCustomer($tenant);
        $tenant->refresh();

        $this->assertSame('error', $tenant->asaas_status);
        Http::assertNothingSent();
    }

    public function test_sync_marks_as_error_when_asaas_api_fails(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*' => Http::response(['errors' => [['description' => 'CPF inválido']]], 400),
        ]);

        $tenant = $this->makeTenant();

        app(AsaasService::class)->syncTenantCustomer($tenant);
        $tenant->refresh();

        $this->assertSame('error', $tenant->asaas_status);
        $this->assertNull($tenant->asaas_customer_id);
    }

    private function superAdmin(): User
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        config(['oravel.super_admins' => [$super->email]]);
        $super->enableTwoFactorAuthentication();
        $super->confirmTwoFactorAuthentication();

        return $super;
    }

    public function test_creating_tenant_via_central_panel_syncs_asaas_customer(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*' => Http::response(['id' => 'cus_painel_central'], 200),
        ]);

        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $plan = Plan::create([
            'name' => 'Plano Painel '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        $slug = 'tenant-painel-asaas-'.uniqid();

        Livewire::test(CreateTenant::class)
            ->fillForm([
                'name' => 'Empresa Painel Asaas',
                'slug' => $slug,
                'plan_id' => $plan->id,
                'status' => 'active',
                'cpf_cnpj' => '123.456.789-01',
                'admin_name' => 'Admin Painel',
                'admin_email' => 'admin-painel-asaas-'.uniqid().'@oravel.com.br',
                'admin_password' => 'senha12345',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tenant = Tenant::where('slug', $slug)->first();

        $this->assertNotNull($tenant);
        $this->assertSame('cus_painel_central', $tenant->asaas_customer_id);
        $this->assertSame('synced', $tenant->asaas_status);
    }

    public function test_edit_tenant_sync_action_updates_asaas_status(): void
    {
        config(['services.asaas.api_key' => 'test-key']);
        Http::fake([
            'sandbox.asaas.com/*' => Http::response(['id' => 'cus_via_edicao'], 200),
        ]);

        $this->actingAs($this->superAdmin());
        Filament::setCurrentPanel(Filament::getPanel('central'));

        $tenant = $this->makeTenant('123.456.789-01');

        Livewire::test(EditTenant::class, ['record' => $tenant->getKey()])
            ->callAction('sync_asaas')
            ->assertHasNoErrors();

        $this->assertSame('cus_via_edicao', $tenant->fresh()->asaas_customer_id);
        $this->assertSame('synced', $tenant->fresh()->asaas_status);
    }
}
