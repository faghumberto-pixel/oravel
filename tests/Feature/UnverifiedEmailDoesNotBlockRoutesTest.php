<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressao: maintenance.report/maintenance.kanban.print exigiam 'verified'
 * (email confirmado) -- nenhum outro lugar do app exige isso, e nao existe
 * fluxo real de verificacao pros usuarios provisionados via
 * TenantProvisioner/admin. Travava ate' o proprio super admin em PROD (sem
 * email_verified_at), "erro pedindo verificar email" reportado pelo
 * usuario. O mesmo 'verified' tambem foi removido do grupo
 * dashboard/profile/trocar-senha (mesmo motivo), nao coberto aqui por um
 * bug pre-existente e nao relacionado na resolucao de tenant da rota
 * /dashboard em ambiente de teste.
 */
class UnverifiedEmailDoesNotBlockRoutesTest extends TestCase
{
    use RefreshDatabase;

    private function unverifiedTenantAdmin(): User
    {
        $plan = Plan::create([
            'name' => 'Plano Teste Verificacao', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => [],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Teste Verificacao', 'slug' => 'tenant-verificacao-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $user = User::create([
            'name' => 'Usuario Sem Verificar', 'email' => 'naoverificado-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        // email_verified_at intencionalmente NULL -- reproduz exatamente o
        // estado real do super admin em PROD.
        $user->forceFill(['is_approved' => true])->save();

        return $user;
    }

    public function test_user_without_verified_email_can_open_maintenance_report(): void
    {
        $user = $this->unverifiedTenantAdmin();
        $this->assertNull($user->email_verified_at);

        $response = $this->actingAs($user)->get(route('maintenance.report'));

        $response->assertOk();
    }

    public function test_user_without_verified_email_can_open_maintenance_kanban_print(): void
    {
        $user = $this->unverifiedTenantAdmin();

        $response = $this->actingAs($user)->get(route('maintenance.kanban.print'));

        $this->assertNotSame(302, $response->getStatusCode(), 'Não pode redirecionar pra tela de verificação de email.');
    }
}
