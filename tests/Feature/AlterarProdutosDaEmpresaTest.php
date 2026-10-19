<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProdutosDaEmpresa;
use App\Support\Pacotes;
use App\Support\Precos;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AlterarProdutosDaEmpresaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.api_key' => 'KEY', 'services.asaas.base_url' => 'https://asaas.test/v3']);
        Http::fake(['asaas.test/*' => Http::response(['id' => 'sub_1'], 200)]);
    }

    private function vitrine(string $codigo, float $preco): Plan
    {
        $p = Pacotes::planoDeVitrine($codigo);
        $p->update(['base_price' => $preco, 'price' => $preco, 'is_active' => true, 'implementation_fee' => 0]);

        return $p->fresh();
    }

    private function empresa(Plan $plano, string $forma = 'cartao'): Tenant
    {
        return Tenant::factory()->create([
            'plan_id' => $plano->id, 'payment_method' => $forma, 'asaas_subscription_id' => 'sub_1',
            'mrr_value' => Precos::paraForma((float) $plano->base_price, $forma),
        ]);
    }

    public function test_upgrade_troca_o_contrato_recalcula_a_mensalidade_e_envia_ao_asaas_sem_mexer_nos_dados(): void
    {
        $servicos = $this->vitrine('servicos', 300);
        $this->vitrine('vendas', 200);
        $empresa = $this->empresa($servicos);
        $usuario = User::factory()->create(['tenant_id' => $empresa->id]);

        $depois = app(ProdutosDaEmpresa::class)->alterar($empresa, ['servicos', 'vendas']);

        $this->assertEqualsCanonicalizing(['servicos', 'vendas'], Pacotes::produtosDoTenant($depois));
        $this->assertEqualsWithDelta(Precos::recorrente(500), (float) $depois->mrr_value, 0.001);
        $this->assertFalse((bool) $depois->mrr_pendente_sincronizar, 'o valor já foi enviado ao Asaas');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/subscriptions/sub_1') && abs($r['value'] - Precos::recorrente(500)) < 0.001);
        $this->assertNotNull(User::withoutGlobalScopes()->find($usuario->id), 'os dados da empresa seguem intactos');
        $this->assertSame(1, $depois->events()->where('event_type', 'contrato_alterado')->count());
    }

    public function test_voltar_para_um_produto_usa_a_vitrine_e_apaga_o_contrato_combinado_sobrando(): void
    {
        $servicos = $this->vitrine('servicos', 300);
        $this->vitrine('vendas', 200);
        $empresa = $this->empresa($servicos);
        $servico = app(ProdutosDaEmpresa::class);

        $combo = $servico->alterar($empresa, ['servicos', 'vendas'])->plan_id;
        $volta = $servico->alterar($empresa->refresh(), ['vendas']);

        $this->assertSame(Pacotes::vitrine('vendas')->id, $volta->plan_id);
        $this->assertNull(Plan::find($combo), 'o combo que ninguém mais usa é removido');
        $this->assertEqualsWithDelta(Precos::recorrente(200), (float) $volta->mrr_value, 0.001);
    }

    public function test_boleto_pix_paga_o_cheio_e_produto_fora_de_venda_e_recusado(): void
    {
        $servicos = $this->vitrine('servicos', 300);
        $this->vitrine('vendas', 200);
        $empresa = $this->empresa($servicos, 'boleto_pix');

        $depois = app(ProdutosDaEmpresa::class)->alterar($empresa, ['servicos', 'vendas']);
        $this->assertEqualsWithDelta(500.0, (float) $depois->mrr_value, 0.001);

        $this->expectException(\InvalidArgumentException::class);
        app(ProdutosDaEmpresa::class)->alterar($depois, ['estoque_inexistente']);
    }
}
