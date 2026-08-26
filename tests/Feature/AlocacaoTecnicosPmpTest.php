<?php

namespace Tests\Feature;

use App\Filament\Pages\AlocacaoTecnicosPmp;
use App\Models\Asset;
use App\Models\MaintenanceDueAlert;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\TechnicianAllocation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSpecialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlocacaoTecnicosPmpTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Alocacao Page '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Alocacao Page '.uniqid(), 'slug' => 'tenant-alocacao-page-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Alocacao', 'email' => 'admin-alocacao-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_corrective_order_without_technician_appears_in_queue_as_unallocated(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Fila', 'status' => Asset::STATUS_DISPONIVEL]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'Falha elétrica', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_ELETRICO,
            'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(AlocacaoTecnicosPmp::class);
        $queue = $page->instance()->queueItems;

        $this->assertCount(1, $queue);
        $this->assertFalse($queue->first()['allocated']);
        $this->assertSame(MaintenanceOrder::FAILURE_CATEGORY_ELETRICO, $queue->first()['failure_category']);
    }

    public function test_allocate_creates_technician_allocation_and_syncs_order(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Alocar', 'status' => Asset::STATUS_DISPONIVEL]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'Falha mecânica', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_MOTOR,
            'internal_status' => 'aguardando_diagnostico',
        ]);
        $technician = User::create([
            'name' => 'Tecnico Alocar', 'email' => 'tec-alocar-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(AlocacaoTecnicosPmp::class)
            ->call('allocate', 'os:'.$order->id, $technician->id, now()->addDay()->toDateTimeString());

        $allocation = TechnicianAllocation::where('maintenance_order_id', $order->id)->sole();
        $this->assertSame($technician->id, $allocation->technician_id);

        $order->refresh();
        $this->assertSame($technician->id, $order->technician_id);
    }

    public function test_allocating_technician_without_specialty_warns_but_does_not_block(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Sem Especialidade', 'status' => Asset::STATUS_DISPONIVEL]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'Falha hidráulica', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO,
            'internal_status' => 'aguardando_diagnostico',
        ]);
        $technician = User::create([
            'name' => 'Tecnico Eletricista', 'email' => 'tec-eletricista-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        UserSpecialty::create(['user_id' => $technician->id, 'specialty' => MaintenanceOrder::FAILURE_CATEGORY_ELETRICO]);

        $this->actingAs($admin);

        Livewire::test(AlocacaoTecnicosPmp::class)
            ->call('allocate', 'os:'.$order->id, $technician->id, now()->addDay()->toDateTimeString());

        // Não bloqueou -- a alocação foi criada mesmo com especialidade
        // incompatível, filosofia de aviso-nunca-bloqueio confirmada com o
        // usuário.
        $this->assertSame(1, TechnicianAllocation::where('maintenance_order_id', $order->id)->count());
    }

    public function test_allocating_alert_creates_allocation_without_maintenance_order(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Alerta', 'status' => Asset::STATUS_DISPONIVEL,
            'horimetro_atual' => 500,
        ]);
        $plano = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano Alerta',
            'interval_hours' => 250, 'last_service_hours' => 0,
        ]);
        $alert = MaintenanceDueAlert::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plano->id,
            'alerted_at' => now(),
        ]);
        $technician = User::create([
            'name' => 'Tecnico Alerta', 'email' => 'tec-alerta-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(AlocacaoTecnicosPmp::class)
            ->call('allocate', 'alert:'.$alert->id, $technician->id, now()->addDay()->toDateTimeString());

        $allocation = TechnicianAllocation::where('maintenance_due_alert_id', $alert->id)->sole();
        $this->assertSame($technician->id, $allocation->technician_id);
        $this->assertNull($allocation->maintenance_order_id);
    }
}
