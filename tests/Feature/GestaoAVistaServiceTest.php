<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetDowntimeEvent;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\GestaoAVistaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre os calculos do dashboard "Gestao a Vista" (GestaoAVistaService),
 * mesmo padrao de setup de AssetDowntimeEventTest -- Plan/Tenant/User
 * criados na mao (sem Factory classes), RefreshDatabase, actingAs()
 * explicito por tenant.
 */
class GestaoAVistaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(array $tenantAttrs = []): array
    {
        $plan = Plan::create([
            'name' => 'Plano Gestao a Vista '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_asset_downtime_events', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create(array_merge([
            'name' => 'Tenant Gestao '.uniqid(), 'slug' => 'tenant-gestao-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ], $tenantAttrs));

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function makeAsset(Tenant $tenant, array $attrs = []): Asset
    {
        return Asset::create(array_merge([
            'tenant_id' => $tenant->id, 'name' => 'Bomba Teste', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel',
        ], $attrs));
    }

    private function baseFiltros(array $overrides = []): array
    {
        return array_merge([
            'from' => now()->subDays(30)->toDateString(),
            'until' => now()->toDateString(),
            'branchId' => null,
            'assetId' => null,
        ], $overrides);
    }

    // ---------------------------------------------------------------
    // resumoOs
    // ---------------------------------------------------------------

    public function test_resumo_os_counts_planned_and_completed(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS 1', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Concluída',
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS 2', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
        ]);

        $service = new GestaoAVistaService($tenant->id);
        $resumo = $service->resumoOs($this->baseFiltros());

        $this->assertSame(2, $resumo['planejadas']);
        $this->assertSame(1, $resumo['concluidas']);
    }

    public function test_resumo_os_excludes_reserva_type(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Reserva', 'maintenance_type' => MaintenanceOrder::TYPE_RESERVA,
            'status' => MaintenanceOrder::STATUS_RESERVADO,
        ]);

        $service = new GestaoAVistaService($tenant->id);
        $resumo = $service->resumoOs($this->baseFiltros());

        $this->assertSame(0, $resumo['planejadas']);
    }

    public function test_resumo_os_flags_overdue_orders_by_sla(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $atrasada = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Atrasada', 'maintenance_type' => MaintenanceOrder::TYPE_EMERGENCIA,
            'status' => 'Aberto', 'sla_target_minutes' => 60,
        ]);
        $atrasada->forceFill(['created_at' => now()->subHours(3)])->saveQuietly();

        $noPrazo = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'No prazo', 'maintenance_type' => MaintenanceOrder::TYPE_EMERGENCIA,
            'status' => 'Aberto', 'sla_target_minutes' => 480,
        ]);

        $service = new GestaoAVistaService($tenant->id);
        $resumo = $service->resumoOs($this->baseFiltros());

        $this->assertSame(1, $resumo['atrasadas']);
    }

    public function test_resumo_os_returns_zero_without_any_order(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $service = new GestaoAVistaService($tenant->id);
        $resumo = $service->resumoOs($this->baseFiltros());

        $this->assertSame(['planejadas' => 0, 'concluidas' => 0, 'atrasadas' => 0], $resumo);
    }

    // ---------------------------------------------------------------
    // custoTotal
    // ---------------------------------------------------------------

    public function test_custo_total_sums_orders_in_period(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        // total_order_cost so' e recalculado em updating() (isDirty
        // labor_cost/logistics_cost), nao em create() -- mesmo padrao ja
        // usado pelos testes de custo do proprio MaintenanceOrder no
        // projeto: criar e depois atualizar, como o formulario real faz.
        $os = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS cara', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'material_cost' => 50,
        ]);
        $os->update(['labor_cost' => 100, 'logistics_cost' => 20]);

        $service = new GestaoAVistaService($tenant->id);
        $custo = $service->custoTotal($this->baseFiltros());

        $this->assertSame(170.0, $custo['valor']);
        $this->assertNull($custo['variacao_percentual']);
    }

    public function test_custo_total_zero_without_orders(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $service = new GestaoAVistaService($tenant->id);
        $custo = $service->custoTotal($this->baseFiltros());

        $this->assertSame(0.0, $custo['valor']);
    }

    // ---------------------------------------------------------------
    // percentualManutencaoRealizada
    // ---------------------------------------------------------------

    public function test_percentual_manutencao_realizada_computes_ratio(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        for ($i = 0; $i < 4; $i++) {
            MaintenanceOrder::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
                'description' => "OS $i", 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
                'status' => $i < 3 ? 'Concluída' : 'Aberto',
            ]);
        }

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->percentualManutencaoRealizada($this->baseFiltros());

        $this->assertSame(75.0, $resultado['percentual']);
    }

    public function test_percentual_manutencao_realizada_null_without_planned_orders(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->percentualManutencaoRealizada($this->baseFiltros());

        $this->assertNull($resultado['percentual']);
    }

    // ---------------------------------------------------------------
    // disponibilidadeEquipamentos
    // ---------------------------------------------------------------

    public function test_disponibilidade_full_without_downtime_events(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->makeAsset($tenant);
        $this->actingAs($admin);

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->disponibilidadeEquipamentos($this->baseFiltros());

        $this->assertSame(100.0, $resultado['percentual']);
    }

    public function test_disponibilidade_drops_with_open_downtime_event(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now()->subDays(29), 'reason' => AssetDowntimeEvent::REASON_QUEBRA,
        ]);

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->disponibilidadeEquipamentos($this->baseFiltros());

        $this->assertLessThan(100.0, $resultado['percentual']);
    }

    public function test_disponibilidade_null_without_any_asset(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->disponibilidadeEquipamentos($this->baseFiltros());

        $this->assertNull($resultado['percentual']);
    }

    // ---------------------------------------------------------------
    // efetividadeManutencao
    // ---------------------------------------------------------------

    public function test_efetividade_full_without_rework(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $os = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Corretiva sem retrabalho', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída',
        ]);
        $os->forceFill(['finished_at' => now()->subDays(20)])->saveQuietly();

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->efetividadeManutencao($this->baseFiltros());

        $this->assertSame(100.0, $resultado['percentual']);
    }

    public function test_efetividade_drops_with_rework_within_window(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $primeira = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Corretiva original', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída',
        ]);
        $primeira->forceFill(['finished_at' => now()->subDays(20)])->saveQuietly();

        $retrabalho = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Voltou quebrado', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
        ]);
        $retrabalho->forceFill(['created_at' => now()->subDays(15)])->saveQuietly();

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->efetividadeManutencao($this->baseFiltros());

        $this->assertSame(0.0, $resultado['percentual']);
    }

    public function test_efetividade_null_without_completed_corrective(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->efetividadeManutencao($this->baseFiltros());

        $this->assertNull($resultado['percentual']);
    }

    // ---------------------------------------------------------------
    // mtbf / mttr (casos de borda de divisão por zero)
    // ---------------------------------------------------------------

    public function test_mtbf_null_without_downtime_events(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->makeAsset($tenant);
        $this->actingAs($admin);

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->mtbf($this->baseFiltros());

        $this->assertNull($resultado['valor_horas']);
    }

    public function test_mttr_null_without_completed_corrective_orders(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Concluída',
        ]);

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->mttr($this->baseFiltros());

        $this->assertNull($resultado['valor_horas']);
    }

    public function test_mttr_computes_average_repair_time(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        $os = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Corretiva', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída',
        ]);
        $os->forceFill([
            'started_at' => now()->subDays(10)->subHours(4),
            'finished_at' => now()->subDays(10),
        ])->saveQuietly();

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->mttr($this->baseFiltros());

        $this->assertSame(4.0, $resultado['valor_horas']);
    }

    // ---------------------------------------------------------------
    // tempoParadaNaoPlanejada
    // ---------------------------------------------------------------

    public function test_tempo_parada_zero_without_downtime_events(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->makeAsset($tenant);
        $this->actingAs($admin);

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->tempoParadaNaoPlanejada($this->baseFiltros());

        $this->assertSame(0.0, $resultado['valor_horas']);
    }

    public function test_tempo_parada_sums_closed_event_duration(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        AssetDowntimeEvent::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'started_at' => now()->subDays(10)->subHours(5),
            'ended_at' => now()->subDays(10),
            'reason' => AssetDowntimeEvent::REASON_MANUTENCAO_CORRETIVA,
        ]);

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->tempoParadaNaoPlanejada($this->baseFiltros());

        $this->assertSame(5.0, $resultado['valor_horas']);
    }

    // ---------------------------------------------------------------
    // principaisCausasFalha
    // ---------------------------------------------------------------

    public function test_causas_falha_empty_when_category_not_filled(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Corretiva sem categoria', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída',
        ]);

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->principaisCausasFalha($this->baseFiltros());

        $this->assertSame([], $resultado);
    }

    public function test_causas_falha_groups_and_computes_percentual(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Hidráulica 1', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída', 'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO,
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Hidráulica 2', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída', 'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO,
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Elétrica', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Concluída', 'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_ELETRICO,
        ]);

        $service = new GestaoAVistaService($tenant->id);
        $resultado = $service->principaisCausasFalha($this->baseFiltros());

        $this->assertSame(MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO, $resultado[0]['categoria']);
        $this->assertSame(2, $resultado[0]['quantidade']);
        $this->assertEqualsWithDelta(66.7, $resultado[0]['percentual'], 0.1);
    }

    // ---------------------------------------------------------------
    // Tenant::getTarget() (default vs. configurado)
    // ---------------------------------------------------------------

    public function test_tenant_get_target_falls_back_to_default_when_unset(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $this->assertSame(90.0, $tenant->getTarget('disponibilidade'));
        $this->assertSame(85.0, $tenant->getTarget('efetividade'));
    }

    public function test_tenant_get_target_uses_configured_value(): void
    {
        [$tenant] = $this->makeTenantAdmin(['targets' => ['disponibilidade' => 95.0]]);

        $this->assertSame(95.0, $tenant->getTarget('disponibilidade'));
        // Chave nao configurada continua no default, mesmo com targets parcial.
        $this->assertSame(85.0, $tenant->getTarget('efetividade'));
    }

    // ---------------------------------------------------------------
    // conclusoes (smoke -- so' confirma que roda sem erro e gera bullets)
    // ---------------------------------------------------------------

    public function test_conclusoes_generates_bullets_with_data(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);
        $this->actingAs($admin);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Concluída',
        ]);

        $service = new GestaoAVistaService($tenant->id);
        $bullets = $service->conclusoes($this->baseFiltros());

        $this->assertNotEmpty($bullets);
    }

    public function test_conclusoes_empty_without_any_data(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $service = new GestaoAVistaService($tenant->id);
        $bullets = $service->conclusoes($this->baseFiltros());

        $this->assertSame([], $bullets);
    }

    // ---------------------------------------------------------------
    // Isolamento entre tenants
    // ---------------------------------------------------------------

    public function test_service_does_not_leak_data_across_tenants(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = $this->makeAsset($tenantA);
        $this->actingAs($adminA);
        MaintenanceOrder::create([
            'tenant_id' => $tenantA->id, 'asset_id' => $assetA->id, 'technician_id' => $adminA->id,
            'description' => 'OS do tenant A', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Concluída',
        ]);

        [$tenantB] = $this->makeTenantAdmin();

        $service = new GestaoAVistaService($tenantB->id);
        $resumo = $service->resumoOs($this->baseFiltros());

        $this->assertSame(0, $resumo['planejadas']);
    }
}
