<?php

namespace Tests\Feature;

use App\Filament\Resources\EquipmentDamageResource\Pages\ViewEquipmentDamage;
use App\Models\AIAnalysis;
use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EquipmentDamageDiagnosisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class EquipmentDamageDiagnosisServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Avaria '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_equipment_damages', 'tabela_assets', 'tabela_maintenance_orders', 'ia_diagnostico_avarias'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Avaria '.uniqid(), 'slug' => 'tenant-avaria-'.uniqid(),
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

    private function makeDamage(Tenant $tenant, User $admin): EquipmentDamage
    {
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Teste', 'tag' => 'AST-'.uniqid(), 'status' => 'locado']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS de teste', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        return EquipmentDamage::create([
            'tenant_id' => $tenant->id,
            'maintenance_order_id' => $order->id,
            'asset_id' => $asset->id,
            'reported_by_user_id' => $admin->id,
            'severity' => EquipmentDamage::SEVERITY_MODERADA,
            'damage_type' => EquipmentDamage::DAMAGE_TYPE_MOTOR,
            'description' => 'Vazamento de óleo no motor',
            'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
        ]);
    }

    public function test_analyze_stores_parsed_response_and_marks_analysis_as_concluded(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $damage = $this->makeDamage($tenant, $admin);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => json_encode([
                        'diagnostico_provavel' => 'Retentor de óleo desgastado',
                        'causa_raiz' => 'Desgaste natural',
                        'acoes_corretivas' => ['Trocar retentor'],
                        'tempo_estimado_reparo' => '4 horas',
                        'pecas_necessarias' => ['Retentor'],
                        'custo_estimado_min' => 200,
                        'custo_estimado_max' => 400,
                        'equipamento_substituto_sugerido' => null,
                    ])],
                ],
            ], 200),
        ]);

        $analysis = app(EquipmentDamageDiagnosisService::class)->analyze($damage, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_CONCLUIDA, $analysis->status);
        $this->assertSame(AIAnalysis::TYPE_AVARIA, $analysis->type);
        $this->assertSame($damage->id, $analysis->equipment_damage_id);
        $this->assertSame('Retentor de óleo desgastado', $analysis->response['diagnostico_provavel']);
        $this->assertSame($damage->asset->name, $analysis->reference_label);
    }

    /**
     * Bug real corrigido em AnthropicApiClient (2026-07-26): a resposta
     * pode vir com um bloco "thinking" ANTES do bloco de texto (extended
     * thinking) -- content[0] pode nao ser o texto. Garante que o
     * diagnostico ainda e' extraido corretamente nesse caso.
     */
    public function test_analyze_extracts_text_block_even_when_a_thinking_block_comes_first(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $damage = $this->makeDamage($tenant, $admin);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'thinking', 'thinking' => 'Analisando os dados da avaria...'],
                    ['type' => 'text', 'text' => json_encode([
                        'diagnostico_provavel' => 'Correia partida',
                        'causa_raiz' => 'Fadiga do material',
                        'acoes_corretivas' => ['Trocar correia'],
                        'tempo_estimado_reparo' => '2 horas',
                        'pecas_necessarias' => ['Correia'],
                        'custo_estimado_min' => 100,
                        'custo_estimado_max' => 150,
                        'equipamento_substituto_sugerido' => null,
                    ])],
                ],
            ], 200),
        ]);

        $analysis = app(EquipmentDamageDiagnosisService::class)->analyze($damage, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_CONCLUIDA, $analysis->status);
        $this->assertSame('Correia partida', $analysis->response['diagnostico_provavel']);
    }

    public function test_analyze_marks_analysis_as_failed_when_api_key_is_missing(): void
    {
        config(['services.anthropic.key' => null]);

        [$tenant, $admin] = $this->makeTenantAdmin();
        $damage = $this->makeDamage($tenant, $admin);

        $analysis = app(EquipmentDamageDiagnosisService::class)->analyze($damage, $admin->id);

        $this->assertSame(AIAnalysis::STATUS_FALHOU, $analysis->status);
        $this->assertNotNull($analysis->error);
    }

    public function test_view_equipment_damage_action_triggers_analysis_and_creates_ai_analysis_record(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $damage = $this->makeDamage($tenant, $admin);
        $this->actingAs($admin);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    ['type' => 'text', 'text' => json_encode([
                        'diagnostico_provavel' => 'ok', 'causa_raiz' => 'ok',
                        'acoes_corretivas' => [], 'tempo_estimado_reparo' => 'ok',
                        'pecas_necessarias' => [], 'custo_estimado_min' => 0,
                        'custo_estimado_max' => 0, 'equipamento_substituto_sugerido' => null,
                    ])],
                ],
            ], 200),
        ]);

        Livewire::test(ViewEquipmentDamage::class, ['record' => $damage->id])
            ->callAction('analisar_ia')
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('ai_analyses', [
            'tenant_id' => $tenant->id,
            'equipment_damage_id' => $damage->id,
            'type' => AIAnalysis::TYPE_AVARIA,
            'status' => AIAnalysis::STATUS_CONCLUIDA,
        ]);
    }
}
