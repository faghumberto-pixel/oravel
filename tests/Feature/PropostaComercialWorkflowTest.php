<?php

namespace Tests\Feature;

use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-25: proposta comercial (equipamento e/ou
 * serviço), personalizável item a item, vendedor de campo cria → time
 * Comercial revisa → aprovação "aciona" o equipamento/serviço (cria uma
 * SolicitacaoLocacao real). Distinta de Quote (aprovado pelo cliente
 * final, vira conta a receber) -- aqui quem aprova é interno.
 */
class PropostaComercialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithClient(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Teste Proposta '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial', 'tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Teste Proposta', 'slug' => 'tenant-proposta-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $client = Client::create([
            'name' => 'Cliente Teste', 'tenant_id' => $tenant->id,
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $client, $admin];
    }

    public function test_cannot_send_proposta_without_client(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $proposta = PropostaComercial::create(['tenant_id' => $tenant->id, 'seller_user_id' => $admin->id]);
        $proposta->items()->create([
            'tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_SERVICO,
            'description' => 'Motorista', 'quantity' => 1, 'unit_price' => 500,
        ]);

        $this->expectException(\RuntimeException::class);
        $proposta->enviarParaComercial();
    }

    public function test_cannot_send_proposta_without_items(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $proposta->enviarParaComercial();
    }

    public function test_full_lifecycle_send_approve_creates_solicitacao_locacao(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Gerador']);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);

        $proposta->items()->create([
            'tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_EQUIPAMENTO,
            'asset_category_id' => $category->id, 'description' => 'Gerador 180 kVA',
            'quantity' => 1, 'unit_price' => 5000, 'unit_period' => 'mensal',
            'start_date' => now()->addDays(3)->toDateString(),
        ]);
        $proposta->items()->create([
            'tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_SERVICO,
            'description' => 'Técnico 24h dedicado', 'quantity' => 1, 'unit_price' => 8000,
        ]);

        // observer recalcula total_value sozinho a cada item salvo
        $this->assertSame('13000.00', $proposta->fresh()->total_value);

        $proposta->enviarParaComercial();
        $this->assertSame(PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL, $proposta->status);
        $this->assertNotNull($proposta->sent_at);

        $proposta->aprovar($admin);
        $this->assertSame(PropostaComercial::STATUS_APROVADA, $proposta->status);
        $this->assertNotNull($proposta->reviewed_at);
        $this->assertSame($admin->id, $proposta->reviewed_by_user_id);
        $this->assertNotNull($proposta->solicitacao_locacao_id);

        $solicitacao = SolicitacaoLocacao::findOrFail($proposta->solicitacao_locacao_id);
        $this->assertSame($client->id, $solicitacao->customer_id);
        $this->assertSame($category->id, $solicitacao->category_id);
        $this->assertSame('proposta_em_andamento', $solicitacao->status_comercial);
        $this->assertNotNull($solicitacao->data_saida_prevista);
    }

    public function test_proposta_100_por_cento_servico_aprova_mas_nao_cria_solicitacao(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);
        $proposta->items()->create([
            'tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_SERVICO,
            'description' => 'Motorista 24h', 'quantity' => 1, 'unit_price' => 6000,
        ]);
        $proposta->enviarParaComercial();

        $proposta->aprovar($admin);

        $this->assertSame(PropostaComercial::STATUS_APROVADA, $proposta->status);
        $this->assertNull($proposta->solicitacao_locacao_id);
        $this->assertSame(0, SolicitacaoLocacao::count());
    }

    public function test_rejection_records_reason(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);
        $proposta->items()->create([
            'tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_SERVICO,
            'description' => 'Motorista', 'quantity' => 1, 'unit_price' => 500,
        ]);
        $proposta->enviarParaComercial();

        $proposta->rejeitar($admin, 'Preço fora do praticado pela concorrência.');

        $this->assertSame(PropostaComercial::STATUS_REJEITADA, $proposta->status);
        $this->assertSame('Preço fora do praticado pela concorrência.', $proposta->rejection_reason);
    }

    public function test_reabrir_para_edicao_volta_pra_rascunho_e_limpa_revisao(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);
        $proposta->items()->create([
            'tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_SERVICO,
            'description' => 'Motorista', 'quantity' => 1, 'unit_price' => 500,
        ]);
        $proposta->enviarParaComercial();
        $proposta->rejeitar($admin, 'Ajustar valor.');

        $proposta->reabrirParaEdicao();

        $this->assertSame(PropostaComercial::STATUS_RASCUNHO, $proposta->status);
        $this->assertNull($proposta->rejection_reason);
        $this->assertNull($proposta->reviewed_by_user_id);
        $this->assertNull($proposta->reviewed_at);
    }

    public function test_cannot_approve_proposta_still_in_draft(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);

        $this->expectException(\RuntimeException::class);
        $proposta->aprovar($admin);
    }

    public function test_deleting_item_recalculates_total(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);
        $item1 = $proposta->items()->create([
            'tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_SERVICO,
            'description' => 'Motorista', 'quantity' => 1, 'unit_price' => 500,
        ]);
        $proposta->items()->create([
            'tenant_id' => $tenant->id, 'type' => PropostaComercialItem::TYPE_SERVICO,
            'description' => 'Técnico', 'quantity' => 1, 'unit_price' => 300,
        ]);

        $this->assertSame('800.00', $proposta->fresh()->total_value);

        $item1->delete();

        $this->assertSame('300.00', $proposta->fresh()->total_value);
    }

    public function test_fill_from_template_copies_terms_by_value(): void
    {
        [$tenant, $client, $admin] = $this->makeTenantWithClient();
        $this->actingAs($admin);

        $template = \App\Models\PropostaComercialTemplate::create([
            'tenant_id' => $tenant->id, 'name' => 'Padrão', 'is_default' => true,
            'is_active' => true, 'default_terms' => 'Pagamento em até 30 dias.',
        ]);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);
        $proposta->fillFromTemplate();
        $proposta->save();

        $this->assertSame('Pagamento em até 30 dias.', $proposta->fresh()->terms);

        $template->update(['default_terms' => 'Texto alterado depois.']);

        $this->assertSame('Pagamento em até 30 dias.', $proposta->fresh()->terms, 'Editar o template não pode retroagir em proposta já criada.');
    }
}
