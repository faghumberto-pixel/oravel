<?php

namespace Tests\Feature;

use App\Filament\Pages\AgendaTecnico;
use App\Filament\Widgets\AgendaTecnicoWidget;
use App\Models\Appointment;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaTecnicoTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Manut '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Manut '.uniqid(), 'slug' => 'tenant-manut-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_page_loads_and_widget_fetches_appointments_and_orders(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'status' => 'disponivel']);

        Appointment::create([
            'tenant_id' => $tenant->id, 'technician_id' => $admin->id,
            'assunto' => 'Visita técnica', 'scheduled_at' => now()->addDay(),
        ]);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Manutenção preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'scheduled_at' => now()->addDays(2),
        ]);

        $this->actingAs($admin);

        $this->get(AgendaTecnico::getUrl())->assertOk();

        $events = Livewire::test(AgendaTecnicoWidget::class)
            ->instance()
            ->fetchEvents([
                'start' => now()->startOfMonth()->toIso8601String(),
                'end' => now()->endOfMonth()->toIso8601String(),
            ]);

        $this->assertCount(2, $events);
    }

    public function test_non_admin_only_sees_own_appointments(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $techA = User::create([
            'name' => 'Tecnico A', 'email' => 'tec-a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $techA->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $tecnicoRole = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $techA->assignRole($tecnicoRole);

        $techB = User::create([
            'name' => 'Tecnico B', 'email' => 'tec-b-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $techB->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $techB->assignRole($tecnicoRole);

        Appointment::create(['tenant_id' => $tenant->id, 'technician_id' => $techA->id, 'assunto' => 'Compromisso A', 'scheduled_at' => now()->addDay()]);
        Appointment::create(['tenant_id' => $tenant->id, 'technician_id' => $techB->id, 'assunto' => 'Compromisso B', 'scheduled_at' => now()->addDay()]);

        $this->actingAs($techA);

        $events = Livewire::test(AgendaTecnicoWidget::class)
            ->instance()
            ->fetchEvents([
                'start' => now()->startOfMonth()->toIso8601String(),
                'end' => now()->endOfMonth()->toIso8601String(),
            ]);

        $this->assertCount(1, $events);
    }

    public function test_appointment_create_fills_correct_columns(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $appointment = Appointment::create([
            'tenant_id' => $tenant->id,
            'technician_id' => $admin->id,
            'assunto' => 'Teste de preenchimento',
            'descricao' => 'Descrição',
            'urgente' => true,
            'scheduled_at' => now()->addHour(),
        ]);

        $appointment->refresh();

        $this->assertSame('Teste de preenchimento', $appointment->assunto);
        $this->assertSame($admin->id, $appointment->technician_id);
        $this->assertTrue($appointment->urgente);
        $this->assertFalse($appointment->completed);
        $this->assertNotNull($appointment->scheduled_at);
    }

    public function test_non_owner_technician_cannot_update_or_delete_appointment(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $techA = User::create([
            'name' => 'Tecnico A', 'email' => 'tec-a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $techA->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $tecnicoRole = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $techA->assignRole($tecnicoRole);

        $techB = User::create([
            'name' => 'Tecnico B', 'email' => 'tec-b-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $techB->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $techB->assignRole($tecnicoRole);

        $appointment = Appointment::create(['tenant_id' => $tenant->id, 'technician_id' => $techB->id, 'assunto' => 'Compromisso B', 'scheduled_at' => now()->addDay()]);

        $this->assertFalse($techA->can('update', $appointment));
        $this->assertFalse($techA->can('delete', $appointment));
        $this->assertTrue($techB->can('update', $appointment));
        $this->assertTrue($admin->can('update', $appointment));
    }
}
