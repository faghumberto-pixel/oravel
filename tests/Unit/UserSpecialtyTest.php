<?php

namespace Tests\Unit;

use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSpecialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSpecialtyTest extends TestCase
{
    use RefreshDatabase;

    private function makeTechnician(): User
    {
        $plan = Plan::create([
            'name' => 'Plano Specialty '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Specialty '.uniqid(), 'slug' => 'tenant-specialty-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        return User::create([
            'name' => 'Tecnico Specialty', 'email' => 'specialty-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
    }

    public function test_technician_has_specialty_after_assigning_it(): void
    {
        $technician = $this->makeTechnician();

        UserSpecialty::create([
            'user_id' => $technician->id,
            'specialty' => MaintenanceOrder::FAILURE_CATEGORY_ELETRICO,
        ]);

        $this->assertTrue($technician->hasSpecialty(MaintenanceOrder::FAILURE_CATEGORY_ELETRICO));
        $this->assertFalse($technician->hasSpecialty(MaintenanceOrder::FAILURE_CATEGORY_MOTOR));
    }

    public function test_technician_can_have_multiple_specialties(): void
    {
        $technician = $this->makeTechnician();

        UserSpecialty::create(['user_id' => $technician->id, 'specialty' => MaintenanceOrder::FAILURE_CATEGORY_ELETRICO]);
        UserSpecialty::create(['user_id' => $technician->id, 'specialty' => MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO]);

        $this->assertCount(2, $technician->specialties);
        $this->assertTrue($technician->hasSpecialty(MaintenanceOrder::FAILURE_CATEGORY_ELETRICO));
        $this->assertTrue($technician->hasSpecialty(MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO));
    }
}
