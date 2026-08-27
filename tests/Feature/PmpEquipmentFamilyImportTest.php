<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenancePlanResource\Pages\ListMaintenancePlans;
use App\Models\ChecklistGroup;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\PmpEquipmentFamily;
use App\Models\PmpTemplateItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Import do catálogo global (PmpEquipmentFamily, painel central, sem
 * tenant_id) pra dentro de um ChecklistGroup do tenant --
 * MaintenancePlan::importFromFamilyTemplate() é cópia pontual, não link
 * vivo. Testa via ação real na tela (Livewire), não só a chamada direta
 * do método (já coberta em MaintenancePlanProjectionTest indiretamente).
 */
class PmpEquipmentFamilyImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Import PMP '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_plans', 'tabela_checklist_groups'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Import PMP '.uniqid(), 'slug' => 'tenant-import-pmp-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Import PMP', 'email' => 'admin-import-pmp-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeFamily(): PmpEquipmentFamily
    {
        $family = PmpEquipmentFamily::create(['segment' => 'empilhadeiras', 'name' => 'Elétricos Leves / Modulares']);

        PmpTemplateItem::create([
            'pmp_equipment_family_id' => $family->id, 'name' => 'Teste conexão dos cabos de força',
            'periodicity_label' => 'Diária', 'interval_days' => 1, 'is_critical' => true,
        ]);
        PmpTemplateItem::create([
            'pmp_equipment_family_id' => $family->id, 'name' => 'Teste sopragem dos módulos',
            'periodicity_label' => '250-300h / Mensal', 'interval_hours' => 250,
        ]);

        return $family;
    }

    public function test_import_action_creates_maintenance_plans_in_target_group(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $family = $this->makeFamily();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras Retráteis']);

        $this->actingAs($admin);

        Livewire::test(ListMaintenancePlans::class)
            ->callAction('importFamilyTemplate', data: [
                'pmp_equipment_family_id' => $family->id,
                'checklist_group_id' => $group->id,
            ]);

        $plans = MaintenancePlan::where('tenant_id', $tenant->id)
            ->where('checklist_group_id', $group->id)
            ->get();

        $this->assertCount(2, $plans);
        $this->assertTrue($plans->contains('name', 'Teste conexão dos cabos de força'));
        $this->assertTrue($plans->firstWhere('name', 'Teste conexão dos cabos de força')->is_critical);
    }

    public function test_importing_twice_does_not_duplicate(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $family = $this->makeFamily();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras Retráteis']);

        $this->actingAs($admin);

        Livewire::test(ListMaintenancePlans::class)
            ->callAction('importFamilyTemplate', data: [
                'pmp_equipment_family_id' => $family->id,
                'checklist_group_id' => $group->id,
            ]);
        Livewire::test(ListMaintenancePlans::class)
            ->callAction('importFamilyTemplate', data: [
                'pmp_equipment_family_id' => $family->id,
                'checklist_group_id' => $group->id,
            ]);

        $this->assertSame(2, MaintenancePlan::where('tenant_id', $tenant->id)->where('checklist_group_id', $group->id)->count());
    }

    public function test_editing_imported_plan_afterwards_does_not_resync_with_catalog(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $family = $this->makeFamily();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras Retráteis']);

        $imported = MaintenancePlan::importFromFamilyTemplate($family, $group);
        $plan = $imported->firstWhere('name', 'Teste conexão dos cabos de força');
        $plan->update(['interval_days' => 2, 'is_critical' => false]);

        // Reimportar não deve sobrescrever a customização do tenant --
        // override por nome (mesmo padrão de copyMaintenancePlanTemplateItem).
        MaintenancePlan::importFromFamilyTemplate($family->fresh('templateItems'), $group);

        $plan->refresh();
        $this->assertSame(2, $plan->interval_days);
        $this->assertFalse($plan->is_critical);
    }
}
