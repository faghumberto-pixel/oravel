<?php

namespace Tests\Feature;

use App\Filament\Pages\EventosEFalhas;
use App\Filament\Pages\PainelCriticidade;
use App\Filament\Resources\MaintenancePlanResource\Widgets\MaintenancePlanStats;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Item 2 do pedido de 2026-07-22 (3 módulos de frota): em vez de tabelas
 * novas de "template" paralelas, MaintenancePlan ganhou interval_days/
 * is_critical/source e um item de grupo pode ser "personalizado" por
 * ativo (MaintenancePlan::applicableFor() + Asset::copyMaintenancePlanTemplateItem()).
 * O ponto crítico: um item personalizado NÃO pode contar duas vezes
 * (uma como item do grupo, outra como override do ativo) nas 3 telas que
 * já liam MaintenancePlan antes desta mudança.
 */
class MaintenancePlanTemplateOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Override '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_plans', 'tabela_checklist_groups'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Override '.uniqid(), 'slug' => 'tenant-override-'.uniqid(),
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

    private function makeAssetInGroup(Tenant $tenant, ChecklistGroup $group, float $horimetroAtual = 0): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel', 'checklist_group_id' => $group->id, 'horimetro_atual' => $horimetroAtual,
        ]);
    }

    public function test_applicable_for_returns_group_items_when_no_override_exists(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = $this->makeAssetInGroup($tenant, $group);

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo', 'interval_hours' => 250,
        ]);

        $planos = MaintenancePlan::applicableFor($asset);

        $this->assertCount(1, $planos);
        $this->assertSame('Troca de óleo', $planos->first()->name);
    }

    public function test_applicable_for_prefers_asset_override_over_group_item_with_same_name(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = $this->makeAssetInGroup($tenant, $group);

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo', 'interval_hours' => 250,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Troca de óleo', 'interval_hours' => 150, 'source' => MaintenancePlan::SOURCE_TEMPLATE,
        ]);

        $planos = MaintenancePlan::applicableFor($asset);

        // So' 1 (nao 2) -- o override do ativo substitui, nao soma.
        $this->assertCount(1, $planos);
        $this->assertSame(150, $planos->first()->interval_hours);
    }

    public function test_copy_maintenance_plan_template_item_is_idempotent(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = $this->makeAssetInGroup($tenant, $group);

        $templateItem = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo', 'interval_hours' => 250, 'is_critical' => true,
        ]);

        $copy1 = $asset->copyMaintenancePlanTemplateItem($templateItem);
        $copy2 = $asset->copyMaintenancePlanTemplateItem($templateItem);

        $this->assertSame($copy1->id, $copy2->id);
        $this->assertSame(1, MaintenancePlan::where('asset_id', $asset->id)->count());
        $this->assertSame(MaintenancePlan::SOURCE_TEMPLATE, $copy1->source);
        $this->assertTrue($copy1->is_critical);
    }

    public function test_due_status_considers_interval_days_independently_of_hours(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = $this->makeAssetInGroup($tenant, $group, horimetroAtual: 10);

        $plano = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Inspeção anual', 'interval_days' => 30,
            'last_service_date' => now()->subDays(40),
        ]);

        // Vencido por DIAS mesmo com pouquíssimo horímetro acumulado (sem
        // interval_hours definido pra este item).
        $status = $plano->dueStatusForAsset($asset);
        $this->assertTrue($status['is_overdue']);
        $this->assertGreaterThan(0, $status['overdue_days']);
    }

    public function test_painel_criticidade_does_not_double_count_an_overridden_item(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = $this->makeAssetInGroup($tenant, $group, horimetroAtual: 500);

        // Item do grupo vencido...
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo', 'interval_hours' => 100,
        ]);
        // ...personalizado pra este ativo (ainda vencido, mas é 1 item só).
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Troca de óleo', 'interval_hours' => 100, 'source' => MaintenancePlan::SOURCE_TEMPLATE,
        ]);

        $this->actingAs($admin);

        Livewire::test(PainelCriticidade::class)
            ->assertSee('Gerador Teste');

        // getLinhasProperty() devolve 1 array por ATIVO (nao por plano) --
        // o ativo so' pode aparecer 1 vez na lista, mesmo tendo 2 linhas de
        // MaintenancePlan (grupo + override) apontando pro mesmo item.
        $linhas = (new PainelCriticidade)->getLinhasProperty();
        $this->assertCount(1, $linhas->where('asset.id', $asset->id));
        $this->assertTrue($linhas->firstWhere('asset.id', $asset->id)['preventiva_vencida']);
    }

    public function test_eventos_e_falhas_lists_the_overridden_item_only_once(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = $this->makeAssetInGroup($tenant, $group, horimetroAtual: 500);

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo', 'interval_hours' => 100,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Troca de óleo', 'interval_hours' => 100, 'source' => MaintenancePlan::SOURCE_TEMPLATE,
        ]);

        $this->actingAs($admin);

        $linhas = (new EventosEFalhas)->getPreventivasVencidasProperty();

        $linhasDoAtivo = $linhas->filter(fn (array $linha) => $linha['asset']->id === $asset->id);
        $this->assertCount(1, $linhasDoAtivo);
    }

    public function test_maintenance_plan_stats_widget_does_not_double_count_overridden_items(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = $this->makeAssetInGroup($tenant, $group, horimetroAtual: 500);

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo', 'interval_hours' => 100, 'is_active' => true,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Troca de óleo', 'interval_hours' => 100, 'is_active' => true, 'source' => MaintenancePlan::SOURCE_TEMPLATE,
        ]);

        $this->actingAs($admin);

        // Le' o Stat "Vencidos por Horimetro" direto (nao por texto solto na
        // tela -- "Total de Itens de Preventiva" legitimamente mostra 2 aqui,
        // que nao e' o que este teste verifica).
        $widget = new MaintenancePlanStats;
        $getStats = new \ReflectionMethod($widget, 'getStats');
        $getStats->setAccessible(true);
        $stats = $getStats->invoke($widget);

        $vencidos = collect($stats)->first(fn ($stat) => str_contains($stat->getLabel(), 'Vencidos'));

        $this->assertSame(1, $vencidos->getValue());
    }
}
