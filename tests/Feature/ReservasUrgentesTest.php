<?php

namespace Tests\Feature;

use App\Filament\Pages\ReservasUrgentes;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido explícito do usuário: hoje o único aviso pra Manutenção quando o
 * Comercial marca "Reservar para Manutenção (Urgente)" é reativo (faixa no
 * Kanban + bloqueio ao abrir OS nova) -- esta tela vira a fila de verdade.
 */
class ReservasUrgentesTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Reservas '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_solicitacao_locacao', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Reservas '.uniqid(), 'slug' => 'tenant-reservas-'.uniqid(),
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

    public function test_lists_urgent_reservations_with_open_order_and_deadline(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Construtora Urgente']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Urgente', 'status' => Asset::STATUS_MANUTENCAO]);

        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'asset_id' => $asset->id, 'data_saida_prevista' => now()->addDays(3),
            'status_comercial' => 'reserva_manutencao',
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'status' => 'Aberto',
        ]);

        $page = new ReservasUrgentes;
        $reservas = $page->getReservas();

        $this->assertCount(1, $reservas);
        $row = $reservas->first();
        $this->assertSame($asset->id, $row['asset']->id);
        $this->assertSame($order->id, $row['openOrder']->id);
        $this->assertSame(3, $row['diasRestantes']);
        $this->assertFalse($row['vencida']);
    }

    public function test_overdue_reservation_without_open_order_is_flagged(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Vencido']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Vencido', 'status' => Asset::STATUS_MANUTENCAO]);

        SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'asset_id' => $asset->id, 'data_saida_prevista' => now()->subDays(2),
            'status_comercial' => 'reserva_manutencao',
        ]);

        $page = new ReservasUrgentes;
        $kpis = $page->getKpis();

        $this->assertSame(1, $kpis['total']);
        $this->assertSame(1, $kpis['semOs']);
        $this->assertSame(1, $kpis['vencidas']);
        $this->assertSame(0, $kpis['prontas']);

        $row = $page->getReservas()->first();
        $this->assertTrue($row['vencida']);
        $this->assertNull($row['openOrder']);
    }

    public function test_asset_already_available_counts_as_ready_to_release(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Pronto']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Pronto', 'status' => Asset::STATUS_DISPONIVEL]);

        SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'asset_id' => $asset->id, 'data_saida_prevista' => now()->addWeek(),
            'status_comercial' => 'reserva_manutencao',
        ]);

        $page = new ReservasUrgentes;
        $this->assertSame(1, $page->getKpis()['prontas']);
    }

    public function test_reservation_without_any_asset_yet_shows_category_only_row(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Sem Ativo']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);

        SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'data_saida_prevista' => now()->addWeek(),
            'status_comercial' => 'reserva_manutencao',
        ]);

        $page = new ReservasUrgentes;
        $reservas = $page->getReservas();

        $this->assertCount(1, $reservas);
        $this->assertNull($reservas->first()['asset']);
    }

    public function test_solicitacoes_not_marked_urgent_are_excluded(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Normal']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Normal', 'status' => Asset::STATUS_DISPONIVEL]);

        SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'asset_id' => $asset->id, 'data_saida_prevista' => now()->addWeek(),
            'status_comercial' => 'proposta_em_andamento',
        ]);

        $page = new ReservasUrgentes;
        $this->assertCount(0, $page->getReservas());
    }

    public function test_page_renders(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Render']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Render', 'status' => Asset::STATUS_MANUTENCAO]);
        SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'asset_id' => $asset->id, 'data_saida_prevista' => now()->addWeek(),
            'status_comercial' => 'reserva_manutencao',
        ]);

        $this->get(ReservasUrgentes::getUrl())
            ->assertOk()
            ->assertSee('Cliente Render')
            ->assertSee('Gerador Render');
    }
}
