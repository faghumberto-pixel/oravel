<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Pacotes;
use App\Support\Precos;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Cliente escolhe mais de um produto no cadastro: valor = soma dos valores cheios, desconto sobre o total, módulos = união. */
class CheckoutVariosProdutosTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.api_key' => 'KEY', 'services.asaas.base_url' => 'https://asaas.test/v3']);
        Http::fake(['asaas.test/*' => Http::response(['id' => 'cus_1', 'data' => []], 200)]);
    }

    private function vitrine(string $codigo, float $preco): Plan
    {
        $p = Pacotes::planoDeVitrine($codigo);
        $p->update(['base_price' => $preco, 'price' => $preco, 'is_active' => true, 'implementation_fee' => 0]);

        return $p->fresh();
    }

    private function payload(Plan $plano, array $extra = []): array
    {
        return $extra + [
            'plan_id' => $plano->id, 'company_name' => 'Empresa '.uniqid(), 'segment' => Client::NICHE_CONSTRUCAO_CIVIL, 'equipment_types' => ['gerador'],
            'cpf_cnpj' => '123.456.789-09', 'cep' => '13480-000', 'logradouro' => 'Rua A', 'numero' => '1', 'bairro' => 'Centro', 'cidade' => 'Limeira', 'uf' => 'SP',
            'admin_name' => 'Admin', 'admin_email' => 'adm-'.uniqid().'@oravel.com.br', 'telefone' => '(19) 99999-9999', 'admin_password' => 'senha12345',
            'forma_pagamento' => 'cartao', 'terms_accepted' => '1',
        ];
    }

    public function test_dois_produtos_somam_o_valor_cheio_e_o_desconto_vale_sobre_o_total(): void
    {
        $servicos = $this->vitrine('servicos', 300);
        $this->vitrine('vendas', 200);

        $this->post('/assinar', $this->payload($servicos, ['produtos_extra' => ['vendas'], 'company_name' => 'Empresa Combo']))->assertSessionHasNoErrors();

        $tenant = Tenant::where('name', 'Empresa Combo')->firstOrFail();
        $plano = $tenant->plan;

        $this->assertSame(Pacotes::TAG_COMBO, $plano->campaign_tag);
        $this->assertEqualsCanonicalizing(['servicos', 'vendas'], $plano->pacotes);
        $this->assertSame('500.00', (string) $plano->base_price);
        $this->assertEqualsWithDelta(Precos::recorrente(500), (float) $tenant->mrr_value, 0.001);
        $this->assertContains('tabela_crm_leads', array_keys($plano->features));
        $this->assertContains('tabela_quotes', array_keys($plano->features));
        $this->assertEqualsCanonicalizing(['servicos', 'vendas'], Pacotes::produtosDoTenant($tenant));
        $this->assertSame($servicos->id, Pacotes::vitrine('servicos')->id, 'a vitrine do site continua sendo a mesma e o combo não aparece nela');
    }

    public function test_boleto_pix_paga_o_cheio_da_soma(): void
    {
        $servicos = $this->vitrine('servicos', 300);
        $this->vitrine('vendas', 200);

        $this->post('/assinar', $this->payload($servicos, ['produtos_extra' => ['vendas'], 'forma_pagamento' => 'boleto_pix', 'company_name' => 'Empresa Combo Boleto']))->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(500.0, (float) Tenant::where('name', 'Empresa Combo Boleto')->value('mrr_value'), 0.001);
    }

    public function test_sem_extras_o_cadastro_segue_com_a_vitrine_como_sempre(): void
    {
        $servicos = $this->vitrine('servicos', 300);

        $this->post('/assinar', $this->payload($servicos, ['company_name' => 'Empresa Simples']))->assertSessionHasNoErrors();

        $tenant = Tenant::where('name', 'Empresa Simples')->firstOrFail();
        $this->assertSame($servicos->id, $tenant->plan_id);
        $this->assertEqualsWithDelta(Precos::recorrente(300), (float) $tenant->mrr_value, 0.001);
    }

    public function test_produto_fora_de_venda_ou_repetido_nao_entra(): void
    {
        $servicos = $this->vitrine('servicos', 300);
        Pacotes::planoDeVitrine('vendas')->update(['is_active' => false, 'base_price' => 200, 'price' => 200]);

        $this->post('/assinar', $this->payload($servicos, ['produtos_extra' => ['vendas'], 'company_name' => 'Empresa Fora']))->assertSessionHasErrors('produtos_extra');
        $this->assertNull(Tenant::where('name', 'Empresa Fora')->first());

        $this->post('/assinar', $this->payload($servicos, ['produtos_extra' => ['servicos'], 'company_name' => 'Empresa Repetida']))->assertSessionHasNoErrors();
        $this->assertSame($servicos->id, Tenant::where('name', 'Empresa Repetida')->value('plan_id'), 'o mesmo produto escolhido de novo não vira combo');
    }

    public function test_formulario_oferece_os_outros_produtos_a_venda(): void
    {
        $servicos = $this->vitrine('servicos', 300);
        $this->vitrine('vendas', 200);

        $this->get(route('checkout.pacote', 'servicos'))->assertOk()
            ->assertSee('Quer incluir outros produtos?')->assertSee('value="vendas"', false)->assertDontSee('value="servicos"', false);
    }

    public function test_campos_do_cadastro_encolhem_no_celular_em_vez_de_vazar_do_cartao(): void
    {
        $this->vitrine('servicos', 300);

        // Bug de 20/10/2026: sem min-width 0 os campos tinham largura mínima maior que a tela e o formulário ficava cortado no celular.
        $this->get(route('checkout.pacote', 'servicos'))->assertOk()
            ->assertSee('min-width: 0', false)->assertSee('.oravel-glass-card form { min-width: 0', false);
    }
}
