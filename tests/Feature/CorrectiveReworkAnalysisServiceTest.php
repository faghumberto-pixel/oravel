<?php

namespace Tests\Feature;

use App\Models\AIAnalysis;
use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CorrectiveReworkAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CorrectiveReworkAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Retrabalho '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_assets', 'ia_diagnostico_avarias'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Retrabalho '.uniqid(), 'slug' => 'tenant-retrabalho-'.uniqid(),
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

    private function makeAsset(Tenant $tenant): Asset
    {
        return Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Perkins', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel',
        ]);
    }

    private function makeCorrectiveOrder(Tenant $tenant, User $admin, Asset $asset, string $createdAt, ?string $finishedAt = null): MaintenanceOrder
    {
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Falha reportada', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'status' => $finishedAt ? 'Concluída' : 'Aberto',
            'finished_at' => $finishedAt,
        ]);

        $order->forceFill(['created_at' => $createdAt])->save();

        return $order;
    }

    public function test_marks_analysis_as_failed_without_calling_api_when_no_rework_detected(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);

        // Uma unica corretiva concluida, sem nenhuma OS seguinte -- nao ha retrabalho.
        $this->makeCorrectiveOrder($tenant, $admin, $asset, now()->subDays(10)->toDateTimeString(), now()->subDays(9)->toDateTimeString());

        Http::fake();

        $analysis = app(CorrectiveReworkAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_FALHOU, $analysis->status);
        $this->assertStringContainsString('Nenhum retrabalho', $analysis->error);
        Http::assertNothingSent();
    }

    public function test_detects_rework_within_window_and_ignores_outside_window(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $assetDentro = $this->makeAsset($tenant);
        $assetFora = $this->makeAsset($tenant);

        // Dentro da janela de 30 dias: concluida ha 20 dias, nova aberta 5 dias depois -> conta.
        $this->makeCorrectiveOrder($tenant, $admin, $assetDentro, now()->subDays(20)->toDateTimeString(), now()->subDays(20)->toDateTimeString());
        $this->makeCorrectiveOrder($tenant, $admin, $assetDentro, now()->subDays(15)->toDateTimeString());

        // Fora da janela de 30 dias: concluida ha 60 dias, nova so' 40 dias depois -> nao conta.
        $this->makeCorrectiveOrder($tenant, $admin, $assetFora, now()->subDays(60)->toDateTimeString(), now()->subDays(60)->toDateTimeString());
        $this->makeCorrectiveOrder($tenant, $admin, $assetFora, now()->subDays(20)->toDateTimeString());

        $this->fakeClaudeJsonResponse([
            'resumo_geral' => 'ok', 'principais_causas_interpretadas' => 'ok',
            'equipamentos_criticos_comentario' => 'ok', 'recomendacoes' => [],
        ]);

        $analysis = app(CorrectiveReworkAnalysisService::class)->analyzeTenant($tenant->id, $admin->id, 30);

        $this->assertSame(AIAnalysis::STATUS_CONCLUIDA, $analysis->status);
        $this->assertSame(1, $analysis->response['total_retrabalhos']);
        $this->assertCount(1, $analysis->response['ranking_retrabalho']);
        $this->assertSame($assetDentro->name, $analysis->response['ranking_retrabalho'][0]['nome']);
        $this->assertSame(5, $analysis->response['ranking_retrabalho'][0]['intervalos_dias'][0]);
    }

    public function test_ignores_non_corrective_orders_for_rework_counting(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);

        // Preventiva concluida seguida de corretiva dentro da janela -- NAO conta
        // como retrabalho aqui (isso e' "quebra pos preventiva", outro servico).
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'status' => 'Concluída', 'finished_at' => now()->subDays(20),
        ])->forceFill(['created_at' => now()->subDays(21)])->save();

        $this->makeCorrectiveOrder($tenant, $admin, $asset, now()->subDays(10)->toDateTimeString());

        Http::fake();

        $analysis = app(CorrectiveReworkAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_FALHOU, $analysis->status);
        Http::assertNothingSent();
    }

    public function test_aggregates_causes_from_equipment_damage_enum(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);

        $antiga = $this->makeCorrectiveOrder($tenant, $admin, $asset, now()->subDays(20)->toDateTimeString(), now()->subDays(20)->toDateTimeString());
        $nova = $this->makeCorrectiveOrder($tenant, $admin, $asset, now()->subDays(10)->toDateTimeString());

        EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $nova->id, 'asset_id' => $asset->id,
            'reported_by_user_id' => $admin->id, 'severity' => EquipmentDamage::SEVERITY_MODERADA,
            'damage_type' => EquipmentDamage::DAMAGE_TYPE_MOTOR, 'description' => 'Vazamento',
            'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR, 'cause' => EquipmentDamage::CAUSE_DESGASTE_NATURAL,
        ]);

        $this->fakeClaudeJsonResponse([
            'resumo_geral' => 'ok', 'principais_causas_interpretadas' => 'ok',
            'equipamentos_criticos_comentario' => 'ok', 'recomendacoes' => [],
        ]);

        $analysis = app(CorrectiveReworkAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_CONCLUIDA, $analysis->status);
        $this->assertCount(1, $analysis->response['causas_classificadas']);
        $this->assertSame('Desgaste Natural', $analysis->response['causas_classificadas'][0]['causa']);
        $this->assertSame(1, $analysis->response['causas_classificadas'][0]['total']);
        $this->assertNotNull($antiga);
    }

    public function test_marks_analysis_as_failed_when_api_key_is_missing(): void
    {
        config(['services.anthropic.key' => null]);

        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = $this->makeAsset($tenant);

        $this->makeCorrectiveOrder($tenant, $admin, $asset, now()->subDays(20)->toDateTimeString(), now()->subDays(20)->toDateTimeString());
        $this->makeCorrectiveOrder($tenant, $admin, $asset, now()->subDays(10)->toDateTimeString());

        $analysis = app(CorrectiveReworkAnalysisService::class)->analyzeTenant($tenant->id, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_FALHOU, $analysis->status);
        $this->assertNotNull($analysis->error);
    }
}
