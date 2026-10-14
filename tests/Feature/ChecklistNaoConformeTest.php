<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource\Pages\EditMaintenanceOrder;
use App\Livewire\MaintenanceChecklistMobile;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use App\Models\MaintenanceOrderPendencia;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regressao dos 3 buracos achados ao conferir a aula de Checklist de
 * Inspecao (14/10/2026): item Nao Conforme travava o progresso, nao gerava
 * pendencia, e os itens PMP apareciam tambem na aba de vistoria.
 */
class ChecklistNaoConformeTest extends TestCase
{
    use DatabaseTransactions;

    private function setUpOrder(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Checklist '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_maintenance_orders'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Checklist '.uniqid(), 'slug' => 'tenant-checklist-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $user = User::create([
            'name' => 'Tecnico', 'email' => 'chk-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $role = new Role(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $role->save();
        $user->assignRole($role);

        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Compressores']);
        foreach (['Nivel de oleo', 'Correias'] as $nome) {
            MaintenanceOrderChecklist::create([
                'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
                'is_template' => true, 'item_name' => $nome,
            ]);
        }
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Compressor', 'tag' => 'AST-'.uniqid(),
            'patrimonio' => 'PAT-'.uniqid(), 'status' => 'disponivel',
            'checklist_group_id' => $group->id, 'horimetro_atual' => 300,
        ]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $user->id,
            'description' => 'Corretiva', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        return [$tenant, $user, $asset, $order];
    }

    public function test_nao_conforme_item_counts_as_answered_so_progress_reaches_100(): void
    {
        [$tenant, $user, $asset, $order] = $this->setUpOrder();
        [$a, $b] = $order->checklists()->orderBy('item_name')->get()->all();

        $a->update(['status' => 'conforme']);
        $b->update(['status' => 'nao_conforme']);

        Livewire::actingAs($user)
            ->test(MaintenanceChecklistMobile::class, ['maintenanceOrder' => $order])
            ->assertSet('progress', 100);

        // Continua sem contar como concluido.
        $this->assertFalse((bool) $b->fresh()->is_completed);
    }

    public function test_unanswered_items_keep_progress_below_100(): void
    {
        [$tenant, $user, $asset, $order] = $this->setUpOrder();
        $order->checklists()->orderBy('item_name')->first()->update(['status' => 'conforme']);

        Livewire::actingAs($user)
            ->test(MaintenanceChecklistMobile::class, ['maintenanceOrder' => $order])
            ->assertSet('progress', 50);
    }

    public function test_nao_conforme_opens_one_pendencia_per_item(): void
    {
        [$tenant, $user, $asset, $order] = $this->setUpOrder();
        $item = $order->checklists()->where('item_name', 'Correias')->first();

        $this->actingAs($user);
        $item->update(['status' => 'nao_conforme', 'notes' => 'Desgastada']);

        $pendencias = MaintenanceOrderPendencia::where('maintenance_order_id', $order->id)->get();
        $this->assertCount(1, $pendencias);
        $this->assertStringContainsString('Correias', $pendencias->first()->description);
        $this->assertSame(MaintenanceOrderPendencia::STATUS_ABERTA, $pendencias->first()->status);

        // Corrigiu e voltou a falhar: nao abre uma segunda igual.
        $item->update(['status' => 'conforme']);
        $item->update(['status' => 'nao_conforme']);
        $this->assertSame(1, MaintenanceOrderPendencia::where('maintenance_order_id', $order->id)->count());
    }

    public function test_conforme_item_does_not_open_pendencia(): void
    {
        [$tenant, $user, $asset, $order] = $this->setUpOrder();
        $order->checklists()->first()->update(['status' => 'conforme']);

        $this->assertSame(0, MaintenanceOrderPendencia::where('maintenance_order_id', $order->id)->count());
    }

    public function test_pmp_items_do_not_show_in_the_vistoria_tab(): void
    {
        [$tenant, $user, $asset, $order] = $this->setUpOrder();
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de filtro XYZ',
            'interval_hours' => 100, 'is_active' => true,
        ]);
        $nova = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $user->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);
        $this->assertTrue($nova->checklists()->where('checklist_type', 'pmp')->exists());

        $this->actingAs($user);
        $component = Livewire::test(
            EditMaintenanceOrder::class,
            ['record' => $nova->getKey()]
        );

        $vistoria = collect($component->get('data.checklists') ?? [])->pluck('item_name')->all();
        $this->assertContains('Nivel de oleo', $vistoria);
        $this->assertNotContains('Troca de filtro XYZ', $vistoria);
        $this->assertContains('Troca de filtro XYZ', collect($component->get('data.pmp_items') ?? [])->pluck('item_name')->all());
    }
}
