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

class PropostaComercialApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makePropostaAprovadaInterna(): PropostaComercial
    {
        $plan = Plan::create([
            'name' => 'Plano Aprovacao Publica '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_proposta_comercial', 'tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Aprovacao Publica '.uniqid(), 'slug' => 'tenant-aprovacao-publica-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Aprovação Pública', 'email' => 'cliente@example.com']);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Plataformas']);

        $proposta = PropostaComercial::create([
            'tenant_id' => $tenant->id, 'client_id' => $client->id, 'seller_user_id' => $admin->id,
        ]);
        PropostaComercialItem::create([
            'tenant_id' => $tenant->id, 'proposta_comercial_id' => $proposta->id,
            'type' => PropostaComercialItem::TYPE_EQUIPAMENTO, 'asset_category_id' => $category->id,
            'description' => 'Plataforma 12m', 'quantity' => 1, 'unit_price' => 2000,
        ]);
        $proposta->refresh();
        $proposta->enviarParaComercial();
        $proposta->refresh();
        $proposta->aprovar($admin);

        return $proposta->fresh();
    }

    public function test_show_marca_client_viewed_at(): void
    {
        $proposta = $this->makePropostaAprovadaInterna();

        $this->assertNull($proposta->client_viewed_at);

        $response = $this->get(route('proposta-comercial.public-approval', $proposta->approval_token));

        $response->assertOk();
        $this->assertNotNull($proposta->fresh()->client_viewed_at);
    }

    public function test_show_com_token_invalido_nao_quebra_a_pagina(): void
    {
        $response = $this->get(route('proposta-comercial.public-approval', 'token-que-nao-existe'));

        $response->assertOk();
        $response->assertSee('Link inválido');
    }

    public function test_approve_aceita_e_redireciona(): void
    {
        $proposta = $this->makePropostaAprovadaInterna();

        $response = $this->post(route('proposta-comercial.public-accept', $proposta->approval_token));

        $response->assertRedirect(route('proposta-comercial.public-approval', $proposta->approval_token));
        $this->assertSame(PropostaComercial::STATUS_ACEITA_PELO_CLIENTE, $proposta->fresh()->status);
    }

    public function test_reject_exige_motivo_e_recusa(): void
    {
        $proposta = $this->makePropostaAprovadaInterna();

        $response = $this->post(route('proposta-comercial.public-reject', $proposta->approval_token), [
            'reason' => 'Preço fora do orçamento disponível.',
        ]);

        $response->assertRedirect(route('proposta-comercial.public-approval', $proposta->approval_token));
        $proposta->refresh();
        $this->assertSame(PropostaComercial::STATUS_RECUSADA_PELO_CLIENTE, $proposta->status);
        $this->assertSame('Preço fora do orçamento disponível.', $proposta->rejection_reason);
    }

    public function test_approve_em_token_ja_respondido_nao_quebra(): void
    {
        $proposta = $this->makePropostaAprovadaInterna();
        $proposta->aceitarPeloCliente();

        $response = $this->post(route('proposta-comercial.public-accept', $proposta->approval_token));

        $response->assertRedirect(route('proposta-comercial.public-approval', $proposta->approval_token));
    }
}
