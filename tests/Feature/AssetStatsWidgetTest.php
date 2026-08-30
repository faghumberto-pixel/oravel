<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Filament\Resources\AssetResource\Widgets\AssetStats;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Asset Stats '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_plans'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Asset Stats '.uniqid(), 'slug' => 'tenant-asset-stats-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function getStats(): array
    {
        $widget = new AssetStats;
        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        return $method->invoke($widget);
    }

    public function test_conta_total_de_ativos(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo 1', 'status' => Asset::STATUS_DISPONIVEL]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo 2', 'status' => Asset::STATUS_DISPONIVEL]);

        $this->actingAs($admin);

        $total = collect($this->getStats())->first(fn ($stat) => $stat->getLabel() === 'Total de Ativos');

        $this->assertSame(2, $total->getValue());
    }

    public function test_conta_ativos_sem_pmp_considerando_plano_proprio_e_de_grupo(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Com Plano']);

        $comPlanoDeGrupo = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Com Plano de Grupo', 'status' => Asset::STATUS_DISPONIVEL, 'checklist_group_id' => $group->id]);
        MaintenancePlan::create(['tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo', 'interval_hours' => 500]);

        $comPlanoProprio = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Com Plano Próprio', 'status' => Asset::STATUS_DISPONIVEL]);
        MaintenancePlan::create(['tenant_id' => $tenant->id, 'asset_id' => $comPlanoProprio->id, 'name' => 'Troca de óleo', 'interval_hours' => 500]);

        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Sem Nenhum Plano', 'status' => Asset::STATUS_DISPONIVEL]);

        $this->actingAs($admin);

        $semPmp = collect($this->getStats())->first(fn ($stat) => $stat->getLabel() === 'Ativos sem PMP');

        $this->assertSame(1, $semPmp->getValue());
    }

    public function test_pagina_de_ativos_nao_tem_mais_widgets_de_grafico(): void
    {
        [, $admin] = $this->makeTenant();
        $this->actingAs($admin);

        $reflection = new \ReflectionMethod(ListAssets::class, 'getHeaderWidgets');
        $reflection->setAccessible(true);
        $widgets = $reflection->invoke(new ListAssets);

        $this->assertSame([AssetStats::class], $widgets);
    }
}
