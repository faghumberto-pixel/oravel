<?php

namespace Tests\Feature;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\EquipmentMovement;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceStatusHistory;
use App\Models\PatioEntry;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre a lacuna documentada no "Estudo de Caso": SolicitacaoLocacao não
 * tem histórico de status próprio, e MaintenanceOrder/PatioEntry não têm FK
 * pra ela -- timelineEvents() cruza as 4 fontes por Ativo + janela de
 * tempo (exceto EquipmentMovement, que tem FK direta).
 */
class SolicitacaoLocacaoTimelineTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Timeline '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_solicitacao_locacao', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Timeline '.uniqid(), 'slug' => 'tenant-timeline-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_timeline_aggregates_all_four_sources_in_chronological_order(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Construtora Horizonte Sul']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador GR-014', 'status' => Asset::STATUS_DISPONIVEL]);

        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'asset_id' => $asset->id,
            'data_saida_prevista' => now()->addWeek(), 'status_comercial' => 'reserva_manutencao',
            'purpose' => 'Canteiro de obras - Fase 2',
        ]);
        $solicitacao->forceFill(['created_at' => now()->subDays(4)])->save();

        // 2. Manutenção -- OS + histórico de status no mesmo Ativo
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'concluido',
        ]);
        $order->forceFill(['created_at' => now()->subDays(3), 'finished_at' => now()->subDays(2)])->save();
        MaintenanceStatusHistory::create([
            'maintenance_order_id' => $order->id, 'old_status' => 'aguardando_diagnostico',
            'new_status' => 'em_manutencao', 'created_at' => now()->subDays(3)->addHours(2),
        ]);

        // 3. Logística -- EquipmentMovement com FK direta
        $movement = EquipmentMovement::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'solicitacao_locacao_id' => $solicitacao->id,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO, 'status' => EquipmentMovement::STATUS_CONCLUIDO,
        ]);
        $movement->forceFill([
            'scheduled_at' => now()->subDay(),
            'approved_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
        ])->save();

        // 4. Portaria -- PatioEntry pelo mesmo Ativo
        PatioEntry::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'direction' => PatioEntry::DIRECTION_SAIDA, 'reason' => PatioEntry::REASON_MOBILIZACAO,
            'arrived_at' => now()->subMinutes(30), 'registered_by_user_id' => $admin->id,
        ]);

        $events = $solicitacao->timelineEvents();

        $sources = $events->pluck('source');
        $this->assertContains('comercial', $sources);
        $this->assertContains('manutencao', $sources);
        $this->assertContains('logistica', $sources);
        $this->assertContains('portaria', $sources);

        // Ordem cronológica: cada 'at' >= o anterior.
        $timestamps = $events->pluck('at')->values();
        for ($i = 1; $i < $timestamps->count(); $i++) {
            $this->assertTrue(
                $timestamps[$i]->gte($timestamps[$i - 1]),
                "Evento {$i} não está em ordem cronológica"
            );
        }

        // 1 criação + 1 "situação atual" (o forceFill acima mexeu em
        // updated_at) + 1 OS aberta + 1 transição de status + 1 OS
        // concluída + 3 marcos do movimento (scheduled/approved/completed)
        // + 1 portaria = 9
        $this->assertCount(9, $events);
    }

    public function test_timeline_excludes_maintenance_orders_from_unrelated_assets(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Alvo', 'status' => Asset::STATUS_DISPONIVEL]);
        $outroAsset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Não Relacionado', 'status' => Asset::STATUS_DISPONIVEL]);

        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'asset_id' => $asset->id,
            'data_saida_prevista' => now()->addWeek(), 'status_comercial' => 'proposta_em_andamento',
        ]);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $outroAsset->id,
            'description' => 'OS de outro ativo', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        $events = $solicitacao->timelineEvents();

        $this->assertFalse($events->contains('title', 'Ordem de Serviço aberta (Corretiva)'));
    }

    public function test_timeline_page_renders(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Timeline']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id,
            'data_saida_prevista' => now()->addWeek(), 'status_comercial' => 'proposta_em_andamento',
        ]);

        $this->get(SolicitacaoLocacaoResource::getUrl('timeline', ['record' => $solicitacao]))
            ->assertOk()
            ->assertSee('Cliente Timeline')
            ->assertSee('Solicitação de Locação criada');
    }
}
