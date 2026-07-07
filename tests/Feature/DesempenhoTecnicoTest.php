<?php

namespace Tests\Feature;

use App\Filament\Pages\DesempenhoTecnico;
use App\Models\Asset;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesempenhoTecnicoTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Desempenho '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders', 'tabela_clients'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Desempenho '.uniqid(), 'slug' => 'tenant-desempenho-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeOrder(Tenant $tenant, Asset $asset, User $technician, array $overrides = []): MaintenanceOrder
    {
        $order = MaintenanceOrder::create(array_merge([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $technician->id,
            'description' => 'Atendimento', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
        ], $overrides));

        // created_at/finished_at nao sao mass-assignable (nao estao no
        // $fillable do model) -- ajusta direto no banco pra simular datas
        // passadas sem disparar os hooks de status do model.
        if (isset($overrides['created_at']) || isset($overrides['finished_at'])) {
            MaintenanceOrder::whereKey($order->id)->update(array_filter([
                'created_at' => $overrides['created_at'] ?? null,
                'finished_at' => $overrides['finished_at'] ?? null,
            ]));
        }

        return $order->fresh();
    }

    public function test_desempenho_aggregates_per_technician_with_drilldown_url(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $tecnico = User::create(['name' => 'Técnico João', 'email' => 'joao-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Desempenho', 'status' => 'disponivel']);

        $this->makeOrder($tenant, $asset, $tecnico, ['status' => 'Concluída', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'total_time_seconds' => 3600]);
        $this->makeOrder($tenant, $asset, $tecnico, ['status' => 'Concluída', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'total_time_seconds' => 7200]);
        $this->makeOrder($tenant, $asset, $tecnico, ['status' => 'Concluída', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'total_time_seconds' => 1800]);

        $this->actingAs($admin);

        $response = $this->get(DesempenhoTecnico::getUrl());

        $response->assertOk();
        $response->assertSee('Técnico João');
        $response->assertSee('1h 30min'); // media corretiva: (3600+7200)/2 = 5400s = 1h30
        $response->assertSee('30min'); // media preventiva
    }

    public function test_retrabalho_shows_new_order_opened_shortly_after_previous_conclusion(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $tecnico = User::create(['name' => 'Técnico Maria', 'email' => 'maria-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $cliente = Client::create(['tenant_id' => $tenant->id, 'name' => 'Construtora Retrabalho', 'city' => 'Curitiba', 'uf' => 'PR']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Compressor Retrabalho', 'status' => 'disponivel', 'client_id' => $cliente->id]);

        $osAntiga = $this->makeOrder($tenant, $asset, $tecnico, [
            'status' => 'Concluída', 'client_id' => $cliente->id,
            'finished_at' => now()->subDays(10),
        ]);

        $this->makeOrder($tenant, $asset, $tecnico, [
            'status' => 'Aberto', 'client_id' => $cliente->id, 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'created_at' => now()->subDays(5),
        ]);

        $this->actingAs($admin);

        $response = $this->get(DesempenhoTecnico::getUrl());

        $response->assertOk();
        $response->assertSee('Compressor Retrabalho');
        $response->assertSee('Técnico Maria');
        $response->assertSee('Construtora Retrabalho');
        $response->assertSee('Curitiba/PR');
    }

    public function test_new_order_outside_window_is_not_retrabalho(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $tecnico = User::create(['name' => 'Técnico Pedro', 'email' => 'pedro-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Fora Da Janela', 'status' => 'disponivel']);

        $this->makeOrder($tenant, $asset, $tecnico, ['status' => 'Concluída', 'finished_at' => now()->subDays(90)]);
        $this->makeOrder($tenant, $asset, $tecnico, ['status' => 'Aberto', 'created_at' => now()->subDays(50)]);

        $this->actingAs($admin);

        $response = $this->get(DesempenhoTecnico::getUrl());

        $response->assertOk();
        $response->assertDontSee('Ativo Fora Da Janela');
    }

    public function test_report_does_not_leak_another_tenants_data(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $tecnicoA = User::create(['name' => 'Técnico Tenant A', 'email' => 'ta-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenantA->id]);
        $assetA = Asset::create(['tenant_id' => $tenantA->id, 'name' => 'Ativo Tenant A', 'status' => 'disponivel']);
        $this->makeOrder($tenantA, $assetA, $tecnicoA, ['status' => 'Concluída', 'total_time_seconds' => 3600]);

        [$tenantB, $adminB] = $this->makeTenantAdmin();

        $this->actingAs($adminB);

        $response = $this->get(DesempenhoTecnico::getUrl());

        $response->assertOk();
        $response->assertDontSee('Técnico Tenant A');
        $response->assertDontSee('Ativo Tenant A');
    }
}
