<?php

namespace Tests\Feature;

use App\Models\AIAnalysis;
use App\Models\Asset;
use App\Models\HorimeterReading;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PreventiveMaintenanceAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PreventiveMaintenanceAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Preventiva '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_assets', 'tabela_maintenance_plans', 'ia_diagnostico_avarias'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Preventiva '.uniqid(), 'slug' => 'tenant-preventiva-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    private function fakeClaudeJsonResponse(array $payload): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => json_encode($payload)],
                ],
            ], 200),
        ]);
    }

    public function test_marks_analysis_as_failed_without_calling_api_when_nothing_overdue_or_broken(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel']);

        Http::fake();

        $analysis = app(PreventiveMaintenanceAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_FALHOU, $analysis->status);
        Http::assertNothingSent();
    }

    public function test_flags_overdue_asset_using_maintenance_plan_due_status(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Compressor', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel', 'horimetro_atual' => 500,
        ]);

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 100, 'is_critical' => false,
        ]);

        $this->fakeClaudeJsonResponse([
            'resumo_geral' => 'ok', 'equipamentos_criticos_comentario' => 'ok',
            'padroes_quebra_pos_preventiva' => 'ok', 'recomendacoes' => [],
        ]);

        $analysis = app(PreventiveMaintenanceAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_CONCLUIDA, $analysis->status);
        $atrasados = $analysis->response['equipamentos_atrasados'];
        $this->assertCount(1, $atrasados);
        $this->assertSame('Compressor', $atrasados[0]['nome']);
        // due_at_hours = 100 + 250 = 350; overdue = 500 - 350 = 150
        $this->assertEquals(150.0, $atrasados[0]['overdue_hours']);
    }

    public function test_detects_breakdown_shortly_after_preventive_on_both_sides_of_threshold(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $assetQuebrouLogo = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Rápido', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel']);
        $assetDemorou = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Estável', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel']);

        // Preventiva concluida ha 20 dias, corretiva aberta 10 dias depois -> 10 dias, abaixo do threshold de 30.
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetQuebrouLogo->id, 'technician_id' => $admin->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Concluída', 'finished_at' => now()->subDays(20),
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetQuebrouLogo->id, 'technician_id' => $admin->id,
            'description' => 'Quebrou', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
        ])->forceFill(['created_at' => now()->subDays(10)])->save();

        // Preventiva concluida ha 90 dias, corretiva aberta 45 dias depois -> 45 dias, acima do threshold.
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetDemorou->id, 'technician_id' => $admin->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Concluída', 'finished_at' => now()->subDays(90),
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetDemorou->id, 'technician_id' => $admin->id,
            'description' => 'Quebrou depois', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => 'Aberto',
        ])->forceFill(['created_at' => now()->subDays(45)])->save();

        $this->fakeClaudeJsonResponse([
            'resumo_geral' => 'ok', 'equipamentos_criticos_comentario' => 'ok',
            'padroes_quebra_pos_preventiva' => 'ok', 'recomendacoes' => [],
        ]);

        $analysis = app(PreventiveMaintenanceAnalysisService::class)->analyzeTenant($tenant->id, $admin->id, 30);

        $quebras = collect($analysis->response['quebras_pos_preventiva'])->keyBy('nome');

        $this->assertSame(10, $quebras['Ativo Rápido']['dias_ate_quebra']);
        $this->assertTrue($quebras['Ativo Rápido']['quebrou_logo_apos']);

        $this->assertSame(45, $quebras['Ativo Estável']['dias_ate_quebra']);
        $this->assertFalse($quebras['Ativo Estável']['quebrou_logo_apos']);
    }

    public function test_mtbf_uses_horimeter_readings_when_available_and_null_otherwise(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $comHorimetro = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Instrumentado', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel']);
        $semHorimetro = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Sem Leitura', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel']);

        foreach ([$comHorimetro, $semHorimetro] as $asset) {
            MaintenanceOrder::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
                'description' => 'Corretiva 1', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
                'status' => 'Concluída', 'finished_at' => now()->subDays(30),
            ])->forceFill(['created_at' => now()->subDays(31)])->save();

            MaintenanceOrder::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
                'description' => 'Corretiva 2', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
                'status' => 'Aberto',
            ])->forceFill(['created_at' => now()->subDays(10)])->save();
        }

        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $comHorimetro->id, 'reading' => 1000, 'source' => 'manual',
            'recorded_at' => now()->subDays(30),
        ]);
        HorimeterReading::create([
            'tenant_id' => $tenant->id, 'asset_id' => $comHorimetro->id, 'reading' => 1200, 'source' => 'manual',
            'recorded_at' => now()->subDays(10),
        ]);

        $this->fakeClaudeJsonResponse([
            'resumo_geral' => 'ok', 'equipamentos_criticos_comentario' => 'ok',
            'padroes_quebra_pos_preventiva' => 'ok', 'recomendacoes' => [],
        ]);

        $analysis = app(PreventiveMaintenanceAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $mtbf = collect($analysis->response['mtbf_por_ativo'])->keyBy('nome');

        $this->assertEquals(200.0, $mtbf['Ativo Instrumentado']['media_horas_trabalhadas']);
        $this->assertNull($mtbf['Ativo Sem Leitura']['media_horas_trabalhadas']);
    }

    public function test_marks_analysis_as_failed_when_api_key_is_missing(): void
    {
        config(['services.anthropic.key' => null]);

        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel', 'horimetro_atual' => 500]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 100,
        ]);

        $analysis = app(PreventiveMaintenanceAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_FALHOU, $analysis->status);
        $this->assertNotNull($analysis->error);
    }
}
