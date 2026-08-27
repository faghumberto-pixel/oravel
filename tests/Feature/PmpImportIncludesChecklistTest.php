<?php

namespace Tests\Feature;

use App\Models\ChecklistGroup;
use App\Models\MaintenanceOrderChecklist;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\PmpEquipmentFamily;
use App\Models\PmpTemplateChecklistItem;
use App\Models\PmpTemplateItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MaintenancePlan::importChecklistFromFamilyTemplate() complementa
 * importFromFamilyTemplate() -- import da família traz planos E checklist
 * juntos, num único clique da ação "Importar Template PMP".
 */
class PmpImportIncludesChecklistTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Import Checklist '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_plans', 'tabela_checklist_groups'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Import Checklist '.uniqid(), 'slug' => 'tenant-import-checklist-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeFamilyWithChecklist(): PmpEquipmentFamily
    {
        $family = PmpEquipmentFamily::create(['segment' => 'empilhadeiras', 'name' => 'Família Checklist Teste']);

        PmpTemplateChecklistItem::create([
            'pmp_equipment_family_id' => $family->id, 'section' => '1. Estrutural', 'item_name' => 'Pneus sem trincas', 'sort_order' => 1,
        ]);
        PmpTemplateChecklistItem::create([
            'pmp_equipment_family_id' => $family->id, 'section' => '2. Energia', 'item_name' => 'Conectores de bateria', 'sort_order' => 2,
        ]);

        return $family;
    }

    public function test_importing_family_creates_checklist_template_items_in_target_group(): void
    {
        $tenant = $this->makeTenant();
        $family = $this->makeFamilyWithChecklist();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Import Checklist']);

        $checklist = MaintenancePlan::importChecklistFromFamilyTemplate($family->fresh('checklistItems'), $group);

        $this->assertCount(2, $checklist);

        $items = MaintenanceOrderChecklist::where('tenant_id', $tenant->id)
            ->where('checklist_group_id', $group->id)
            ->where('is_template', true)
            ->get();

        $this->assertCount(2, $items);
        $this->assertTrue($items->contains('item_name', 'Pneus sem trincas'));
        $this->assertTrue($items->contains('section', '2. Energia'));
    }

    public function test_reimporting_checklist_does_not_duplicate(): void
    {
        $tenant = $this->makeTenant();
        $family = $this->makeFamilyWithChecklist();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Import Checklist 2']);

        MaintenancePlan::importChecklistFromFamilyTemplate($family->fresh('checklistItems'), $group);
        MaintenancePlan::importChecklistFromFamilyTemplate($family->fresh('checklistItems'), $group);

        $this->assertSame(2, MaintenanceOrderChecklist::where('tenant_id', $tenant->id)
            ->where('checklist_group_id', $group->id)
            ->where('is_template', true)
            ->count());
    }

    public function test_imported_plan_carries_auto_create_order_from_catalog_item(): void
    {
        $tenant = $this->makeTenant();
        $family = PmpEquipmentFamily::create(['segment' => 'empilhadeiras', 'name' => 'Família Auto Order Teste']);
        PmpTemplateItem::create([
            'pmp_equipment_family_id' => $family->id, 'name' => 'Item Auto Order',
            'periodicity_label' => 'Diária', 'interval_days' => 1,
        ]);
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Auto Order']);

        $imported = MaintenancePlan::importFromFamilyTemplate($family->fresh('templateItems'), $group);

        $this->assertTrue($imported->first()->auto_create_order);
    }
}
