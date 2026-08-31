<?php

namespace Tests\Feature;

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

class PropostaComercialStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Proposta Fluxo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial', 'tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Proposta Fluxo '.uniqid(), 'slug' => 'tenant-proposta-fluxo-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function makePropostaEnviada(Tenant $tenant, User $admin): PropostaComercial
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Fluxo', 'email' => 'cliente-'.uniqid().'@teste.com']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Empilhadeira 2.5t', 'quantity' => 1, 'unit_price' => 1000,
        ]);
        $proposta->refresh();
        $proposta->enviarParaComercial();

        return $proposta->fresh();
    }

    public function test_aprovar_muda_status_para_aprovada_interna_sem_criar_solicitacao(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $proposta = $this->makePropostaEnviada($tenant, $admin);

        $proposta->aprovar($admin);
        $proposta->refresh();

        $this->assertSame(PropostaComercial::STATUS_APROVADA_INTERNA, $proposta->status);
        $this->assertNull($proposta->solicitacao_locacao_id);
    }

    public function test_aceitar_pelo_cliente_cria_solicitacao_locacao(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $proposta = $this->makePropostaEnviada($tenant, $admin);
        $proposta->aprovar($admin);
        $proposta->refresh();

        $proposta->aceitarPeloCliente();
        $proposta->refresh();

        $this->assertSame(PropostaComercial::STATUS_ACEITA_PELO_CLIENTE, $proposta->status);
        $this->assertNotNull($proposta->client_responded_at);
        $this->assertNotNull($proposta->solicitacao_locacao_id);
    }

    public function test_aceitar_pelo_cliente_falha_se_nao_estiver_aprovada_interna(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $proposta = $this->makePropostaEnviada($tenant, $admin);

        $this->expectException(\RuntimeException::class);
        $proposta->aceitarPeloCliente();
    }

    public function test_recusar_pelo_cliente_registra_motivo_sem_criar_solicitacao(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $proposta = $this->makePropostaEnviada($tenant, $admin);
        $proposta->aprovar($admin);
        $proposta->refresh();

        $proposta->recusarPeloCliente('Preço acima do orçamento.');
        $proposta->refresh();

        $this->assertSame(PropostaComercial::STATUS_RECUSADA_PELO_CLIENTE, $proposta->status);
        $this->assertSame('Preço acima do orçamento.', $proposta->rejection_reason);
        $this->assertNull($proposta->solicitacao_locacao_id);
    }

    public function test_reabrir_para_edicao_aceita_rejeitada_e_recusada_pelo_cliente(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $propostaRejeitada = $this->makePropostaEnviada($tenant, $admin);
        $propostaRejeitada->rejeitar($admin, 'Sem orçamento');
        $propostaRejeitada->refresh();
        $propostaRejeitada->reabrirParaEdicao();
        $this->assertSame(PropostaComercial::STATUS_RASCUNHO, $propostaRejeitada->fresh()->status);

        $propostaRecusada = $this->makePropostaEnviada($tenant, $admin);
        $propostaRecusada->aprovar($admin);
        $propostaRecusada->refresh();
        $propostaRecusada->recusarPeloCliente('Não quero mais');
        $propostaRecusada->refresh();
        $propostaRecusada->reabrirParaEdicao();
        $this->assertSame(PropostaComercial::STATUS_RASCUNHO, $propostaRecusada->fresh()->status);
    }

    public function test_mark_viewed_by_client_so_registra_a_primeira_visualizacao(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $proposta = $this->makePropostaEnviada($tenant, $admin);
        $proposta->aprovar($admin);
        $proposta->refresh();
        $proposta->update(['approval_token' => 'token-teste']);

        $proposta->markViewedByClient();
        $primeiraVisualizacao = $proposta->fresh()->client_viewed_at;

        $this->travel(1)->hours();
        $proposta->markViewedByClient();

        $this->assertEquals($primeiraVisualizacao, $proposta->fresh()->client_viewed_at);
    }
}
