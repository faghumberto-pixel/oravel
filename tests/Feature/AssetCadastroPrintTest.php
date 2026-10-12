<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AssetCadastroPrintTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantAdmin(): array
    {
        $plan = Plan::create(['name' => 'Plano Ficha '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets']]);
        $tenant = Tenant::create(['name' => 'Empresa Ficha', 'slug' => 'ficha-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => 'ficha-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_ficha_mostra_so_campos_preenchidos(): void
    {
        [$tenant, $admin] = $this->tenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Caminhão Munck', 'patrimonio' => 'PAT-77', 'status' => 'disponivel', 'placa' => 'ABC1D23', 'licenciamento_vencimento' => '2026-12-31']);

        $this->actingAs($admin)->get(route('asset.print-cadastro', ['asset' => $asset->id]))
            ->assertOk()
            ->assertSee('Caminhão Munck')
            ->assertSee('PAT-77')
            ->assertSee('ABC1D23')
            ->assertSee('31/12/2026')
            ->assertDontSee('Seguradora');
    }

    public function test_ativo_de_outro_tenant_da_404(): void
    {
        [, $admin] = $this->tenantAdmin();
        [$outroTenant] = $this->tenantAdmin();
        $alheio = Asset::withoutGlobalScopes()->create(['tenant_id' => $outroTenant->id, 'name' => 'Alheio', 'status' => 'disponivel']);

        $this->actingAs($admin)->get(route('asset.print-cadastro', ['asset' => $alheio->id]))->assertNotFound();
    }
}
