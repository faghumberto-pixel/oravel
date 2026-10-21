<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Decisão de 21/10/2026: sem venda online. O cliente fala com a Oravel e o site não divulga valor. */
class VendaOnlineDesligadaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.venda_online' => false, 'services.asaas.api_key' => 'KEY', 'services.asaas.base_url' => 'https://asaas.test/v3']);
        Http::fake(['asaas.test/*' => Http::response(['id' => 'cus_1', 'data' => []], 200)]);
        Cache::flush();
    }

    private function vitrine(string $codigo = 'manutencao'): Plan
    {
        $p = Pacotes::planoDeVitrine($codigo);
        $p->update(['base_price' => 199, 'price' => 199, 'is_active' => true]);

        return $p->fresh();
    }

    public function test_link_do_pacote_manda_para_o_contato_com_o_pacote(): void
    {
        $this->vitrine();

        $this->get(route('checkout.pacote', 'manutencao'))->assertRedirect('https://oravel.com.br/contato.php?pacote=manutencao');
        $this->get('/assinar/inexistente')->assertRedirect('https://oravel.com.br/contato.php');
    }

    public function test_link_com_plano_de_vitrine_tambem_manda_para_o_contato(): void
    {
        $plano = $this->vitrine();

        $this->get('/assinar?plano='.$plano->id)->assertRedirect('https://oravel.com.br/contato.php?pacote=manutencao');
    }

    public function test_contrato_montado_pela_equipe_continua_abrindo_o_cadastro(): void
    {
        $contrato = Plan::create(['name' => 'Contrato do cliente X', 'price' => 500, 'base_price' => 500, 'is_active' => true, 'features' => ['tabela_clients' => true]]);

        $this->get('/assinar?plano='.$contrato->id)->assertOk();
    }

    public function test_cadastro_por_post_de_plano_de_vitrine_nao_cria_empresa(): void
    {
        $plano = $this->vitrine();
        $antes = Tenant::count();

        $this->post('/assinar', [
            'plan_id' => $plano->id, 'company_name' => 'Empresa Bloqueada', 'segment' => Client::NICHE_CONSTRUCAO_CIVIL, 'equipment_types' => ['gerador'],
            'cpf_cnpj' => '123.456.789-09', 'cep' => '13480-000', 'logradouro' => 'Rua A', 'numero' => '1', 'bairro' => 'Centro', 'cidade' => 'Limeira', 'uf' => 'SP',
            'admin_name' => 'Admin', 'admin_email' => 'adm-'.uniqid().'@oravel.com.br', 'telefone' => '(19) 99999-9999', 'admin_password' => 'senha12345',
            'forma_pagamento' => 'cartao', 'terms_accepted' => '1',
        ])->assertRedirect('https://oravel.com.br/contato.php?pacote=manutencao');

        $this->assertSame($antes, Tenant::count());
        $this->assertNull(Tenant::where('name', 'Empresa Bloqueada')->first());
    }

    public function test_api_publica_nao_divulga_nenhum_valor(): void
    {
        $this->vitrine();

        $this->getJson('/api/public/planos')->assertOk()->assertExactJson(['data' => [], 'taxas' => []]);
    }

    public function test_com_a_chave_ligada_o_fluxo_antigo_volta(): void
    {
        config(['oravel.venda_online' => true]);
        $this->vitrine();

        $this->get(route('checkout.pacote', 'manutencao'))->assertOk();
    }
}
