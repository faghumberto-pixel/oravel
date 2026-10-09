<?php

namespace Tests\Feature;

use App\Filament\Pages\PropostaComercialKanban;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\Plan;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 28/09/2026: "equipamento solicitado pelo comercial
 * pode ficar muito bem no Kanban Comercial" -- board agrupado por status
 * da Proposta Comercial, cada card mostrando se o equipamento já foi
 * solicitado (SolicitacaoLocacao criada em aprovar()).
 */
class PropostaComercialKanbanTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Kanban Proposta '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial', 'tabela_solicitacao_locacao', 'menu_proposta_comercial_kanban'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Kanban Proposta '.uniqid(), 'slug' => 'tenant-kanban-proposta-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_groups_propostas_by_status(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Kanban']);

        PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
            'status' => PropostaComercial::STATUS_RASCUNHO,
        ]);
        PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
            'status' => PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL,
        ]);

        $this->actingAs($admin);
        $page = new PropostaComercialKanban;
        $records = $page->getRecords();

        $this->assertCount(1, $records->get(PropostaComercial::STATUS_RASCUNHO));
        $this->assertCount(1, $records->get(PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL));
    }

    public function test_page_renders_and_flags_equipamento_ja_solicitado(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Board', 'email' => 'cliente-'.uniqid().'@teste.com']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Gerador', 'quantity' => 1, 'unit_price' => 1000,
        ]);
        $proposta->refresh();
        $proposta->enviarParaComercial();
        $proposta->refresh();
        $proposta->aprovar($admin);

        $this->actingAs($admin);

        $this->get(PropostaComercialKanban::getUrl())
            ->assertOk()
            ->assertSee('Cliente Board')
            ->assertSee('Equipamento já solicitado');
    }

    public function test_proposta_sem_solicitacao_nao_mostra_o_selo(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Sem Selo']);
        PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
            'status' => PropostaComercial::STATUS_RASCUNHO,
        ]);

        $this->actingAs($admin);

        $this->get(PropostaComercialKanban::getUrl())
            ->assertOk()
            ->assertSee('Cliente Sem Selo')
            ->assertDontSee('Equipamento já solicitado');
    }
}
