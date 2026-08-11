<?php

namespace Tests\Feature;

use App\Filament\Widgets\GestaoAVista\CausasFalhaBarChart;
use App\Filament\Widgets\GestaoAVista\ConclusoesPanel;
use App\Filament\Widgets\GestaoAVista\CustoTotalMetricCard;
use App\Filament\Widgets\GestaoAVista\DisponibilidadeEvolucao;
use App\Filament\Widgets\GestaoAVista\DisponibilidadeGauge;
use App\Filament\Widgets\GestaoAVista\EfetividadeEvolucao;
use App\Filament\Widgets\GestaoAVista\EfetividadeGauge;
use App\Filament\Widgets\GestaoAVista\ManutencaoRealizadaEvolucao;
use App\Filament\Widgets\GestaoAVista\ManutencaoRealizadaGauge;
use App\Filament\Widgets\GestaoAVista\MtbfMetricCard;
use App\Filament\Widgets\GestaoAVista\MttrMetricCard;
use App\Filament\Widgets\GestaoAVista\OsResumoStats;
use App\Filament\Widgets\GestaoAVista\TempoParadaEvolucaoAreaChart;
use App\Filament\Widgets\GestaoAVista\TempoParadaMetricCard;
use App\Filament\Widgets\GestaoAVista\TiposManutencaoAreaChart;
use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Smoke test de TODOS os widgets do dashboard "Gestao a Vista" -- confirma
 * que cada um monta sem erro via Livewire::test(), com filtros passados e
 * com dados reais no tenant (nao so' o caso vazio). Nao valida pixel a
 * pixel, so' "renderiza sem quebrar" -- suficiente pra pegar erros de
 * assinatura/tipo antes de integrar na pagina de verdade.
 */
class GestaoAVistaWidgetsSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Smoke '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders', 'tabela_asset_downtime_events'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Smoke '.uniqid(), 'slug' => 'tenant-smoke-'.uniqid(),
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

    private function seedData(Tenant $tenant, User $admin): void
    {
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel']);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Concluída',
        ]);

        $corretiva = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Corretiva', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída', 'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO,
        ]);
        $corretiva->forceFill([
            'started_at' => now()->subDays(5)->subHours(3),
            'finished_at' => now()->subDays(5),
        ])->saveQuietly();

        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now()->subDays(5)->subHours(3), 'ended_at' => now()->subDays(5),
            'reason' => AssetDowntimeEvent::REASON_MANUTENCAO_CORRETIVA, 'maintenance_order_id' => $corretiva->id,
        ]);
    }

    private function filtros(): array
    {
        return [
            'from' => now()->subDays(30)->toDateString(),
            'until' => now()->toDateString(),
            'branchId' => null,
            'assetId' => null,
        ];
    }

    public function test_all_gestao_a_vista_widgets_mount_without_error(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->seedData($tenant, $admin);
        $this->actingAs($admin);

        $widgets = [
            OsResumoStats::class,
            TiposManutencaoAreaChart::class,
            CustoTotalMetricCard::class,
            ManutencaoRealizadaGauge::class,
            ManutencaoRealizadaEvolucao::class,
            DisponibilidadeGauge::class,
            DisponibilidadeEvolucao::class,
            EfetividadeGauge::class,
            EfetividadeEvolucao::class,
            MtbfMetricCard::class,
            MttrMetricCard::class,
            TempoParadaMetricCard::class,
            CausasFalhaBarChart::class,
            TempoParadaEvolucaoAreaChart::class,
            ConclusoesPanel::class,
        ];

        foreach ($widgets as $widgetClass) {
            Livewire::test($widgetClass, $this->filtros())->assertOk();
        }
    }

    public function test_all_gestao_a_vista_widgets_mount_without_error_when_tenant_has_no_data(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $widgets = [
            OsResumoStats::class,
            TiposManutencaoAreaChart::class,
            CustoTotalMetricCard::class,
            ManutencaoRealizadaGauge::class,
            ManutencaoRealizadaEvolucao::class,
            DisponibilidadeGauge::class,
            DisponibilidadeEvolucao::class,
            EfetividadeGauge::class,
            EfetividadeEvolucao::class,
            MtbfMetricCard::class,
            MttrMetricCard::class,
            TempoParadaMetricCard::class,
            CausasFalhaBarChart::class,
            TempoParadaEvolucaoAreaChart::class,
            ConclusoesPanel::class,
        ];

        foreach ($widgets as $widgetClass) {
            Livewire::test($widgetClass, $this->filtros())->assertOk();
        }
    }
}
