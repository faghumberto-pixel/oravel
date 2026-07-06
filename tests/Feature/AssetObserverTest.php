<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressao: AssetObserver nunca era registrado em lugar nenhum (nem
 * AppServiceProvider nem atributo no model) -- o alerta de "ativo critico em
 * manutencao" nunca disparava, independente de qualquer logica interna.
 * Alem disso, o metodo usava User::role('oficina') direto, que o Spatie
 * resolve por nome GLOBALMENTE (ignora tenant_id) -- com dois tenants tendo
 * cada um seu proprio papel "oficina", sempre resolvia pro primeiro criado
 * no banco inteiro. Ambos corrigidos: registrado em AppServiceProvider::boot()
 * e a resolucao do papel agora e explicita por tenant_id.
 */
class AssetObserverTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Oficina '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Oficina '.uniqid(), 'slug' => 'tenant-oficina-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeOficinaUser(Tenant $tenant, string $name): User
    {
        $user = User::create([
            'name' => $name,
            'email' => str($name)->slug().'-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'),
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'oficina', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCriticalAsset(Tenant $tenant): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id,
            'name' => 'Guindaste Crítico',
            'tag' => 'AST-'.uniqid(),
            'status' => 'operando',
            'criticidade_peso' => 5,
        ]);
    }

    public function test_notifies_oficina_role_when_critical_asset_enters_maintenance(): void
    {
        $tenant = $this->makeTenant();
        $oficinaUser = $this->makeOficinaUser($tenant, 'Oficina User');
        $asset = $this->makeCriticalAsset($tenant);

        $this->actingAs($oficinaUser);

        $asset->update(['status' => 'manutencao']);

        $this->assertSame(1, $oficinaUser->fresh()->notifications()->count());
    }

    public function test_does_not_notify_when_asset_is_not_top_criticality(): void
    {
        $tenant = $this->makeTenant();
        $oficinaUser = $this->makeOficinaUser($tenant, 'Oficina User');
        $asset = $this->makeCriticalAsset($tenant);
        $asset->update(['criticidade_peso' => 3]);

        $this->actingAs($oficinaUser);
        $asset->update(['status' => 'manutencao']);

        $this->assertSame(0, $oficinaUser->fresh()->notifications()->count());
    }

    public function test_notification_reaches_correct_tenant_even_when_another_tenant_has_the_same_role_name_created_first(): void
    {
        $tenantA = $this->makeTenant();
        $this->makeOficinaUser($tenantA, 'Oficina Tenant A');

        $tenantB = $this->makeTenant();
        $oficinaB = $this->makeOficinaUser($tenantB, 'Oficina Tenant B');
        $assetB = $this->makeCriticalAsset($tenantB);

        $this->actingAs($oficinaB);
        $assetB->update(['status' => 'manutencao']);

        $this->assertSame(1, $oficinaB->fresh()->notifications()->count());
    }
}
