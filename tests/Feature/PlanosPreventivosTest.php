<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenancePlanResource\Pages\ListMaintenancePlans;
use App\Filament\Resources\MaintenancePlanResource\Support\PlanStatus;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\HorimeterReading;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PlanosPreventivosTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Planos Preventivos '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_maintenance_plans', 'tabela_assets'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Planos Preventivos '.uniqid(), 'slug' => 'tenant-planos-preventivos-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Planos Preventivos', 'email' => 'admin-planos-preventivos-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_status_dentro_do_prazo_para_plano_por_ativo_longe_do_vencimento(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Em Dia', 'status' => Asset::STATUS_DISPONIVEL,
            'horimetro_atual' => 100,
        ]);
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de óleo',
            'interval_hours' => 1000, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('dentro_do_prazo', PlanStatus::forPlan($plan));
    }

    public function test_status_vencido_para_plano_por_ativo_que_ja_passou_do_horimetro(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Vencido', 'status' => Asset::STATUS_DISPONIVEL,
            'horimetro_atual' => 500,
        ]);
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('vencido', PlanStatus::forPlan($plan));
    }

    public function test_status_de_plano_de_grupo_e_o_pior_caso_entre_os_ativos(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Misto']);
        Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Em Dia', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 100,
        ]);
        Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Vencido', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('vencido', PlanStatus::forPlan($plan));
    }

    public function test_status_a_vencer_quando_plano_vence_dentro_do_mes_atual(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 1));

        [$tenant] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo A Vencer', 'status' => Asset::STATUS_DISPONIVEL,
            'horimetro_atual' => 950,
        ]);
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 900,
            'recorded_at' => now()->subDays(5), 'source' => 'manual',
        ]);
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 950,
            'recorded_at' => now(), 'source' => 'manual',
        ]);
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de óleo',
            'interval_hours' => 1000, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('a_vencer', PlanStatus::forPlan($plan));

        Carbon::setTestNow();
    }

    public function test_plano_de_grupo_sem_ativo_vinculado_fica_dentro_do_prazo(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Vazio']);
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('dentro_do_prazo', PlanStatus::forPlan($plan));
    }

    public function test_filtro_status_do_plano_restringe_a_tabela(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $assetVencido = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Vencido', 'status' => Asset::STATUS_DISPONIVEL,
            'horimetro_atual' => 500,
        ]);
        $planVencido = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetVencido->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $assetEmDia = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Em Dia', 'status' => Asset::STATUS_DISPONIVEL,
            'horimetro_atual' => 100,
        ]);
        $planEmDia = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetEmDia->id, 'name' => 'Troca de óleo',
            'interval_hours' => 1000, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(ListMaintenancePlans::class)
            ->filterTable('plan_status', 'vencido')
            ->assertCanSeeTableRecords(MaintenancePlan::where('id', $planVencido->id)->get())
            ->assertCanNotSeeTableRecords(MaintenancePlan::where('id', $planEmDia->id)->get());
    }

    public function test_filtros_ficam_visiveis_acima_da_tabela(): void
    {
        [, $admin] = $this->makeTenantAdmin();

        $this->actingAs($admin);

        $html = Livewire::test(ListMaintenancePlans::class)->html();

        $this->assertStringContainsString('fi-ta-filters-above-content', $html);
    }

    public function test_cards_de_status_aparecem_na_listagem(): void
    {
        [, $admin] = $this->makeTenantAdmin();

        $this->actingAs($admin);

        $html = Livewire::test(ListMaintenancePlans::class)->html();

        $this->assertStringContainsString('Vencidos', $html);
        $this->assertStringContainsString('A Vencer', $html);
        $this->assertStringContainsString('Dentro do Prazo', $html);
    }

    public function test_botao_imprimir_gera_relatorio_com_status_do_plano(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Impressão', 'patrimonio' => 'PAT-PP-01',
            'status' => Asset::STATUS_DISPONIVEL, 'horimetro_atual' => 500,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(ListMaintenancePlans::class);
        $reflection = new \ReflectionMethod(ListMaintenancePlans::class, 'getHeaderActions');
        $reflection->setAccessible(true);
        $actions = $reflection->invoke($component->instance());

        $imprimir = collect($actions)->first(fn ($action) => $action->getName() === 'imprimir_planos');
        $this->assertNotNull($imprimir);

        $url = $imprimir->getUrl();
        $this->assertNotEmpty($url);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('PAT-PP-01');
        $response->assertSee('Vencido');
    }
}
