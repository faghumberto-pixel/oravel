<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetDowntimeEventResource\Pages\ManageAssetDowntimeEvents;
use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Item 3 do pedido de 2026-07-22 (3 módulos de frota): histórico de
 * paradas -- abertura/fechamento automáticos por OS corretiva (ver
 * MaintenanceOrder::booted()) + tela pra paradas manuais.
 */
class AssetDowntimeEventTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Downtime '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_asset_downtime_events', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Downtime '.uniqid(), 'slug' => 'tenant-downtime-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeAsset(Tenant $tenant): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel',
        ]);
    }

    public function test_creating_a_corrective_order_opens_a_downtime_event(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Quebrou', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        $evento = AssetDowntimeEvent::where('maintenance_order_id', $order->id)->sole();
        $this->assertSame($asset->id, $evento->asset_id);
        $this->assertSame(AssetDowntimeEvent::REASON_MANUTENCAO_CORRETIVA, $evento->reason);
        $this->assertNull($evento->ended_at);
        $this->assertTrue($evento->fresh()->exists());
    }

    public function test_creating_a_preventive_order_does_not_open_a_downtime_event(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Revisão de rotina', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        $this->assertSame(0, AssetDowntimeEvent::count());
    }

    public function test_concluding_the_order_closes_its_downtime_event(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Quebrou', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
        ]);

        $evento = AssetDowntimeEvent::where('maintenance_order_id', $order->id)->sole();
        $this->assertNull($evento->ended_at);

        $order->update(['status' => 'Concluída']);

        $this->assertNotNull($evento->fresh()->ended_at);
    }

    public function test_duration_accessor_reflects_open_vs_closed_events(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $aberto = AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now()->subHours(2), 'reason' => AssetDowntimeEvent::REASON_QUEBRA,
        ]);
        $this->assertGreaterThanOrEqual(119, $aberto->duration);

        $fechado = AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now()->subHours(3), 'ended_at' => now()->subHours(1),
            'reason' => AssetDowntimeEvent::REASON_QUEBRA,
        ]);
        $this->assertSame(120, $fechado->duration);
    }

    public function test_scope_open_only_returns_events_without_ended_at(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $aberto = AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now(), 'reason' => AssetDowntimeEvent::REASON_QUEBRA,
        ]);
        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now()->subDay(), 'ended_at' => now(), 'reason' => AssetDowntimeEvent::REASON_QUEBRA,
        ]);

        $this->assertSame([$aberto->id], AssetDowntimeEvent::open()->pluck('id')->all());
    }

    public function test_manual_registration_action_creates_an_event_with_registered_by(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        Livewire::test(ManageAssetDowntimeEvents::class)
            ->callTableAction('create', data: [
                'asset_id' => $asset->id,
                'reason' => AssetDowntimeEvent::REASON_OCIOSO_SEM_USO,
                'started_at' => now(),
            ])
            ->assertHasNoTableActionErrors();

        $evento = AssetDowntimeEvent::sole();
        $this->assertSame($admin->id, $evento->registered_by);
        $this->assertNull($evento->maintenance_order_id);
    }

    public function test_encerrar_action_closes_an_open_event(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $evento = AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now(), 'reason' => AssetDowntimeEvent::REASON_QUEBRA,
        ]);

        Livewire::test(ManageAssetDowntimeEvents::class)
            ->callTableAction('encerrar', $evento);

        $this->assertNotNull($evento->fresh()->ended_at);
    }

    public function test_events_do_not_leak_across_tenants(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = $this->makeAsset($tenantA);
        $this->actingAs($adminA);
        AssetDowntimeEvent::create([
            'tenant_id' => $tenantA->id, 'asset_id' => $assetA->id,
            'started_at' => now(), 'reason' => AssetDowntimeEvent::REASON_QUEBRA,
        ]);

        [$tenantB, $adminB] = $this->makeTenantAdmin();
        $this->actingAs($adminB);

        $this->assertSame(0, AssetDowntimeEvent::count());
    }

    public function test_table_shows_the_assets_patrimonio(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'tag' => 'AST-'.uniqid(),
            'patrimonio' => 'PAT-0099', 'status' => 'disponivel',
        ]);
        $this->actingAs($admin);

        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now(), 'reason' => AssetDowntimeEvent::REASON_QUEBRA,
        ]);

        Livewire::test(ManageAssetDowntimeEvents::class)
            ->assertSee('PAT-0099');
    }
}
