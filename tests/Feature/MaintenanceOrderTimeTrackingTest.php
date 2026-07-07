<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource\Pages\EditMaintenanceOrder;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceStatusHistory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cobre a refatoracao que tirou o calculo de total_time_seconds/last_timer_start
 * das actions da pagina de edicao (Iniciar/Pausar/Reprogramar/Transferir/
 * Cancelar/Concluir) e deixou o hook MaintenanceOrder::booted() como unica
 * fonte de verdade -- e a correcao de autoria (user_id) no historico de status.
 */
class MaintenanceOrderTimeTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano OS '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant OS '.uniqid(), 'slug' => 'tenant-os-'.uniqid(),
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

    public function test_iniciar_sets_started_at_and_logs_status_change_with_authoring_user(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Timer', 'status' => 'disponivel']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Atendimento', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
        ]);

        $this->actingAs($admin);

        Livewire::test(EditMaintenanceOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('iniciar');

        $order->refresh();
        $this->assertSame('Em Andamento', $order->status);
        $this->assertNotNull($order->started_at);
        $this->assertNotNull($order->last_timer_start);

        $history = MaintenanceStatusHistory::where('maintenance_order_id', $order->id)->first();
        $this->assertNotNull($history, 'Esperava um registro em maintenance_status_histories');
        $this->assertSame('Em Andamento', $history->new_status);
        $this->assertSame('Aberto', $history->old_status);
        $this->assertSame($admin->id, $history->user_id);
    }

    public function test_pausar_accumulates_elapsed_time_exactly_once(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Timer', 'status' => 'disponivel']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Atendimento', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Em Andamento', 'started_at' => now()->subMinutes(10),
            'last_timer_start' => now()->subMinutes(10), 'total_time_seconds' => 0,
        ]);

        $this->actingAs($admin);

        Livewire::test(EditMaintenanceOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('pausar');

        $order->refresh();
        $this->assertSame('Pausada', $order->status);
        $this->assertNull($order->last_timer_start);
        // ~600s (10 min) acumulados uma unica vez -- nao 1200s (dobrado) nem 0.
        $this->assertGreaterThan(590, $order->total_time_seconds);
        $this->assertLessThan(650, $order->total_time_seconds);
    }

    public function test_full_cycle_pausar_iniciar_concluir_does_not_double_count_time(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Timer', 'status' => 'disponivel']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Atendimento', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Em Andamento', 'started_at' => now()->subMinutes(5),
            'last_timer_start' => now()->subMinutes(5), 'total_time_seconds' => 0,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EditMaintenanceOrder::class, ['record' => $order->getRouteKey()]);

        $component->callAction('pausar');
        $order->refresh();
        $afterPause = $order->total_time_seconds;
        $this->assertGreaterThan(290, $afterPause);
        $this->assertLessThan(320, $afterPause);

        $component->callAction('iniciar');
        $order->refresh();
        $this->assertSame('Em Andamento', $order->status);
        // Reiniciou o timer -- nao deve ter somado nada extra so por reiniciar.
        $this->assertSame($afterPause, $order->total_time_seconds);

        $component->callAction('concluir');
        $order->refresh();
        $this->assertSame('Concluída', $order->status);
        $this->assertNotNull($order->finished_at);
        // Tempo desde o "iniciar" ate o "concluir" foi de milissegundos no teste --
        // total_time_seconds deve ficar essencialmente igual ao acumulado na pausa,
        // nao dobrar (o que aconteceria se o hook E a action somassem separadamente).
        $this->assertLessThan($afterPause + 5, $order->total_time_seconds);

        $this->assertCount(3, MaintenanceStatusHistory::where('maintenance_order_id', $order->id)->get());
    }
}
