<?php

namespace Tests\Feature;

use App\Filament\Pages\TechnicianDailyTasks;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Minhas Ordens de Serviço" -- destino padrao do tecnico puro (sem
 * admin, sem departamento supervisionado) no lugar do Dashboard, pedido
 * explicito do usuario 2026-08-04. Sem grafico, so as O.S. do tecnico:
 * aba "Abertas" (comportamento original desta pagina) + aba "Encerradas"
 * (nova, so O.S. de manutencao com status Concluída).
 */
class TechnicianDailyTasksTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAndTechnician(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Tecnico '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Tecnico '.uniqid(), 'slug' => 'tenant-tecnico-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $technician = User::create([
            'name' => 'Tecnico Campo', 'email' => 'tecnico-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $technician->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        return [$tenant, $technician];
    }

    public function test_pure_technician_is_redirected_here_instead_of_the_dashboard(): void
    {
        [, $technician] = $this->makeTenantAndTechnician();
        $this->actingAs($technician);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('filament.admin.pages.technician-daily-tasks'));
    }

    public function test_admin_panel_home_lands_the_pure_technician_on_this_page_not_hour_meter(): void
    {
        [, $technician] = $this->makeTenantAndTechnician();
        $this->actingAs($technician);

        // RedirectToHomeController do Filament segue navigationSort -- essa
        // pagina precisa ficar antes de qualquer outro item navegavel do
        // tecnico (ex: "Registrar Horímetro"), senao /admin manda ele pro
        // lugar errado. Ver AdminPanelProvider::navigationItems() e
        // TechnicianDailyTasks::$navigationSort.
        $response = $this->get('/admin');

        $response->assertRedirect();
        $this->assertSame(
            route('filament.admin.pages.technician-daily-tasks'),
            $response->headers->get('Location')
        );
    }

    public function test_open_tab_shows_pending_maintenance_orders_only(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $openAsset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Aberto', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL,
        ]);
        $closedAsset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Encerrado', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL,
        ]);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $openAsset->id, 'technician_id' => $technician->id,
            'description' => 'Em aberto', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Em Andamento',
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $closedAsset->id, 'technician_id' => $technician->id,
            'description' => 'Encerrada', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída',
        ]);

        $this->actingAs($technician);

        Livewire::test(TechnicianDailyTasks::class)
            ->assertSet('activeTab', 'aberta')
            ->assertSee('Gerador Aberto')
            ->assertDontSee('Gerador Encerrado');
    }

    public function test_closed_tab_shows_completed_maintenance_orders(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Fechado', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL,
        ]);

        $open = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $technician->id,
            'description' => 'Em aberto', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Em Andamento',
        ]);
        $closed = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $technician->id,
            'description' => 'Encerrada', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída',
        ]);

        $this->actingAs($technician);

        Livewire::test(TechnicianDailyTasks::class)
            ->set('activeTab', 'encerrada')
            ->assertSee('Gerador Fechado')
            ->assertDontSee('Gerador Aberto');
    }

    public function test_closed_tasks_only_include_this_technicians_own_orders(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $otherTechnician = User::create([
            'name' => 'Outro Tecnico', 'email' => 'outro-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Compartilhado', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL,
        ]);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $otherTechnician->id,
            'description' => 'Do outro tecnico', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída',
        ]);

        $this->actingAs($technician);

        $component = Livewire::test(TechnicianDailyTasks::class)
            ->set('activeTab', 'encerrada');

        $this->assertCount(0, $component->get('closedTasks'));
    }

    public function test_does_not_leak_closed_orders_from_another_tenant(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        [$otherTenant] = $this->makeTenantAndTechnician();

        $foreignAsset = Asset::create([
            'tenant_id' => $otherTenant->id, 'name' => 'Gerador Alheio', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL,
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $otherTenant->id, 'asset_id' => $foreignAsset->id, 'technician_id' => $technician->id,
            'description' => 'Ordem de outro tenant', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída',
        ]);

        $this->actingAs($technician);

        $component = Livewire::test(TechnicianDailyTasks::class)
            ->set('activeTab', 'encerrada');

        $this->assertCount(0, $component->get('closedTasks'));
    }
}
