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
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

/**
 * Discutido explicitamente com o usuário: se a OS de Reserva bloqueia o
 * Ativo e o Comercial nunca fecha o contrato (cancela, ou volta a
 * solicitação pra proposta), a reserva precisa se desfazer sozinha --
 * senão o Ativo fica "reservado" pra sempre por engano.
 */
class SolicitacaoLocacaoReservaRevogacaoTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Revogacao '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_solicitacao_locacao', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Revogacao '.uniqid(), 'slug' => 'tenant-revogacao-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $gerenteManutencao = User::create([
            'name' => 'Gerente Manutenção', 'email' => 'gerente-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $gerenteManutencao->forceFill(['email_verified_at' => now()])->save();
        $gerenteManutencao->assignRole(Role::firstOrCreate(['name' => 'Gerente de Manutenção', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin, $gerenteManutencao];
    }

    public function test_cancelling_the_request_auto_releases_the_reserved_asset(): void
    {
        [$tenant, $admin, $gerenteManutencao] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Cancela']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Cancela', 'status' => Asset::STATUS_MANUTENCAO]);
        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'asset_id' => $asset->id, 'data_saida_prevista' => now()->addWeek(),
            'status_comercial' => 'reserva_manutencao',
        ]);

        (new ReservasUrgentes)->abrirOsReserva($solicitacao->id, $asset->id);
        $asset->refresh();
        $this->assertSame(Asset::STATUS_RESERVADO, $asset->status);

        $solicitacao->update(['status_comercial' => 'cancelado']);

        $asset->refresh();
        $this->assertSame(Asset::STATUS_DISPONIVEL, $asset->status);

        $order = MaintenanceOrder::where('solicitacao_locacao_id', $solicitacao->id)->sole();
        $this->assertSame('Cancelada', $order->status);

        $this->assertSame(
            1,
            DatabaseNotification::where('notifiable_id', $gerenteManutencao->id)->count(),
            'Gerente de Manutenção deveria ser notificado da revogação'
        );
    }

    public function test_reverting_to_proposta_also_releases_the_asset(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Reverte']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Reverte', 'status' => Asset::STATUS_MANUTENCAO]);
        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'asset_id' => $asset->id, 'data_saida_prevista' => now()->addWeek(),
            'status_comercial' => 'reserva_manutencao',
        ]);

        (new ReservasUrgentes)->abrirOsReserva($solicitacao->id, $asset->id);
        $solicitacao->update(['status_comercial' => 'proposta_em_andamento']);

        $asset->refresh();
        $this->assertSame(Asset::STATUS_DISPONIVEL, $asset->status);
    }

    public function test_closing_the_contract_does_not_touch_an_already_released_asset(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Fecha']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Fecha', 'status' => Asset::STATUS_MANUTENCAO]);
        $solicitacao = SolicitacaoLocacao::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'customer_id' => $client->id,
            'category_id' => $category->id, 'asset_id' => $asset->id, 'data_saida_prevista' => now()->addWeek(),
            'status_comercial' => 'reserva_manutencao',
        ]);

        $page = new ReservasUrgentes;
        $page->abrirOsReserva($solicitacao->id, $asset->id);
        $order = MaintenanceOrder::where('solicitacao_locacao_id', $solicitacao->id)->sole();
        $page->concluirReserva($order->id);

        $solicitacao->update(['status_comercial' => 'contrato_fechado']);

        $order->refresh();
        // A revogação automática só age sobre OS ainda abertas -- a que já
        // foi concluída manualmente (caminho feliz) não pode ser
        // "cancelada por engano" quando o contrato fecha depois.
        $this->assertSame('Concluída', $order->status);
    }
}
