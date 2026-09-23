<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bloqueio real de acesso por inadimplência (pedido do usuário 2026-09-23):
 * asaas_payment_status virava 'atrasado'/'cancelado' só como informação, sem
 * travar nada de fato. Cobre Tenant::isAccessBlockedForNonPayment() (a regra)
 * e App\Http\Middleware\EnsureTenantPaymentIsCurrent (o gate de verdade, numa
 * rota real do painel).
 */
class TenantPaymentBlockingTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithAdmin(array $tenantOverrides = []): array
    {
        $plan = Plan::create([
            'name' => 'Plano Bloqueio '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);

        $tenant = Tenant::create(array_merge([
            'name' => 'Tenant Bloqueio '.uniqid(), 'slug' => 'tenant-bloqueio-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ], $tenantOverrides));

        $admin = TenantProvisioner::provision($tenant, [
            'name' => 'Admin Bloqueio', 'email' => 'admin-bloqueio-'.uniqid().'@oravel.com.br',
            'password' => 'senha12345',
        ]);

        // TenantProvisioner::provision() devolve o objeto em memória sem
        // re-buscar do banco -- is_approved aparece null ali (mesmo já
        // sendo true de fato, via default da coluna), porque o create() do
        // Eloquent não inclui esse campo no INSERT (não está no array
        // passado) e não relê o registro depois. actingAs() usaria esse
        // objeto desatualizado como o usuário autenticado, derrubando
        // canAccessPanel() -- refresh() garante que os testes usem o valor
        // real gravado no banco, não o estado obsoleto em memória.
        $admin->refresh();

        return [$tenant, $admin];
    }

    // ---------- Tenant::isAccessBlockedForNonPayment() ----------

    public function test_tenant_sem_status_de_pagamento_nunca_e_bloqueado(): void
    {
        [$tenant] = $this->makeTenantWithAdmin();

        $this->assertFalse($tenant->isAccessBlockedForNonPayment());
    }

    public function test_tenant_em_dia_nao_e_bloqueado(): void
    {
        [$tenant] = $this->makeTenantWithAdmin(['asaas_payment_status' => Tenant::PAYMENT_STATUS_EM_DIA]);

        $this->assertFalse($tenant->isAccessBlockedForNonPayment());
    }

    public function test_tenant_cancelado_e_bloqueado_imediatamente_sem_tolerancia(): void
    {
        [$tenant] = $this->makeTenantWithAdmin(['asaas_payment_status' => Tenant::PAYMENT_STATUS_CANCELADO]);

        $this->assertTrue($tenant->isAccessBlockedForNonPayment());
    }

    public function test_tenant_atrasado_dentro_do_prazo_de_tolerancia_nao_e_bloqueado(): void
    {
        config(['oravel.payment_grace_days' => 5]);
        [$tenant] = $this->makeTenantWithAdmin([
            'asaas_payment_status' => Tenant::PAYMENT_STATUS_ATRASADO,
            'asaas_overdue_since' => now()->subDays(3),
        ]);

        $this->assertFalse($tenant->isAccessBlockedForNonPayment());
    }

    public function test_tenant_atrasado_alem_do_prazo_de_tolerancia_e_bloqueado(): void
    {
        config(['oravel.payment_grace_days' => 5]);
        [$tenant] = $this->makeTenantWithAdmin([
            'asaas_payment_status' => Tenant::PAYMENT_STATUS_ATRASADO,
            'asaas_overdue_since' => now()->subDays(6),
        ]);

        $this->assertTrue($tenant->isAccessBlockedForNonPayment());
    }

    public function test_tenant_atrasado_sem_overdue_since_ainda_nao_e_bloqueado(): void
    {
        // Cenário defensivo: status 'atrasado' mas sem o timestamp de
        // transição (não deveria acontecer via webhook, mas não pode travar
        // sem saber desde quando).
        [$tenant] = $this->makeTenantWithAdmin(['asaas_payment_status' => Tenant::PAYMENT_STATUS_ATRASADO]);

        $this->assertFalse($tenant->isAccessBlockedForNonPayment());
    }

    public function test_super_admin_nunca_e_bloqueado(): void
    {
        config(['oravel.super_admins' => ['super@oravel.com.br']]);
        [$tenant] = $this->makeTenantWithAdmin(['asaas_payment_status' => Tenant::PAYMENT_STATUS_CANCELADO]);

        $superAdmin = User::create([
            'name' => 'Super Admin', 'email' => 'super@oravel.com.br',
            'password' => bcrypt('senha12345'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $this->actingAs($superAdmin);
        $this->assertFalse($tenant->isAccessBlockedForNonPayment());
    }

    // ---------- Middleware EnsureTenantPaymentIsCurrent (rota real) ----------

    public function test_admin_bloqueado_e_redirecionado_pra_tela_de_bloqueio(): void
    {
        [$tenant, $admin] = $this->makeTenantWithAdmin([
            'asaas_payment_status' => Tenant::PAYMENT_STATUS_CANCELADO,
        ]);

        $response = $this->actingAs($admin)->get('/admin/painel-controle');

        $response->assertRedirect(route('admin.conta-bloqueada'));
    }

    public function test_admin_em_dia_acessa_normalmente(): void
    {
        [$tenant, $admin] = $this->makeTenantWithAdmin([
            'asaas_payment_status' => Tenant::PAYMENT_STATUS_EM_DIA,
        ]);

        $response = $this->actingAs($admin)->get('/admin/painel-controle');

        $response->assertOk();
    }

    public function test_tela_de_bloqueio_nao_entra_em_loop_de_redirecionamento(): void
    {
        [$tenant, $admin] = $this->makeTenantWithAdmin([
            'asaas_payment_status' => Tenant::PAYMENT_STATUS_CANCELADO,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.conta-bloqueada'));

        $response->assertOk();
        $response->assertSee($tenant->name);
    }

    public function test_tela_de_bloqueio_mostra_link_da_fatura_quando_disponivel(): void
    {
        [$tenant, $admin] = $this->makeTenantWithAdmin([
            'asaas_payment_status' => Tenant::PAYMENT_STATUS_ATRASADO,
            'asaas_overdue_since' => now()->subDays(10),
            'asaas_current_invoice_url' => 'https://sandbox.asaas.com/i/pay_bloqueado',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.conta-bloqueada'));

        $response->assertOk();
        $response->assertSee('https://sandbox.asaas.com/i/pay_bloqueado', false);
    }

    public function test_admin_bloqueado_ainda_consegue_deslogar(): void
    {
        [$tenant, $admin] = $this->makeTenantWithAdmin([
            'asaas_payment_status' => Tenant::PAYMENT_STATUS_CANCELADO,
        ]);

        $response = $this->actingAs($admin)->post(route('filament.admin.auth.logout'));

        $response->assertRedirect();
        $this->assertGuest();
    }
}
