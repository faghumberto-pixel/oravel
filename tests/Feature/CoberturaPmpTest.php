<?php

namespace Tests\Feature;

use App\Filament\Pages\CoberturaPmp;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoberturaPmpTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Cobertura PMP '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_maintenance_plans', 'tabela_assets'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Cobertura PMP '.uniqid(), 'slug' => 'tenant-cobertura-pmp-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Cobertura PMP', 'email' => 'admin-cobertura-pmp-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_status_sem_grupo_quando_ativo_sem_grupo_e_sem_plano_proprio(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Sem Grupo', 'status' => Asset::STATUS_DISPONIVEL]);

        $this->assertSame('sem_grupo', CoberturaPmp::statusFor($asset));
    }

    public function test_status_em_dia_quando_plano_longe_do_vencimento(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Em Dia']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Em Dia', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 100,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 1000, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('em_dia', CoberturaPmp::statusFor($asset));
    }

    public function test_status_vencido_quando_plano_ja_passou_do_horimetro(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Vencido']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Vencido', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 500,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('vencido', CoberturaPmp::statusFor($asset));
    }

    public function test_status_vencendo_quando_plano_vence_dentro_do_mes_atual(): void
    {
        // Fixa "hoje" no dia 1 do mês -- sem isso o teste é flaky perto do
        // fim do mês real (projectedDueDates() joga a projeção de +5 dias
        // pro mês seguinte quando o teste roda, por exemplo, no dia 29).
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::create(2026, 3, 1));

        [$tenant] = $this->makeTenantAdmin();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Grupo Vencendo']);
        // Uso médio diário de 10h/dia (50h em 5 dias) -- faltam 50h pro
        // vencimento (1000-950), então projeta vencer em +5 dias, ainda
        // dentro do mês fixado acima.
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Vencendo', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id, 'horimetro_atual' => 950,
        ]);
        \App\Models\HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 900,
            'recorded_at' => now()->subDays(5), 'source' => 'manual',
        ]);
        \App\Models\HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'reading' => 950,
            'recorded_at' => now(), 'source' => 'manual',
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id, 'name' => 'Troca de óleo',
            'interval_hours' => 1000, 'last_service_hours' => 0, 'is_active' => true,
        ]);

        $this->assertSame('vencendo', CoberturaPmp::statusFor($asset));

        \Illuminate\Support\Carbon::setTestNow();
    }
}
