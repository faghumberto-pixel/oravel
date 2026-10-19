<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Support\Pacotes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProdutosDoContratoTest extends TestCase
{
    use DatabaseTransactions;

    private function plano(array $attrs = []): Plan
    {
        return Plan::create($attrs + ['name' => 'Contrato '.uniqid(), 'price' => 100, 'is_active' => true, 'features' => []]);
    }

    public function test_contrato_antigo_sem_lista_segue_o_pacote_e_o_personalizado_segue_locadora(): void
    {
        $this->assertSame(['manutencao'], Pacotes::produtosDoPlano($this->plano(['pacote' => 'manutencao'])));
        $this->assertSame(['locadora'], Pacotes::produtosDoPlano($this->plano()));
        $this->assertSame(['locadora'], Pacotes::produtosDoPlano(null));
        $this->assertSame(['vendas'], Pacotes::produtosDoPlano($this->plano(['pacote' => 'crm'])), 'código antigo é normalizado');
    }

    public function test_lista_de_produtos_tem_o_principal_sem_repetir_e_ignora_codigo_desconhecido(): void
    {
        $plano = $this->plano(['pacote' => 'servicos', 'pacotes' => ['vendas', 'servicos', 'inexistente']]);

        $this->assertEqualsCanonicalizing(['servicos', 'vendas'], Pacotes::produtosDoPlano($plano));
        $this->assertSame(Pacotes::produtosDoPlano($plano), Pacotes::produtosDoTenant(Tenant::factory()->create(['plan_id' => $plano->id])));
    }

    public function test_modulos_de_varios_produtos_sao_a_uniao_sem_repetir(): void
    {
        $juntos = Pacotes::featuresDeProdutos(['servicos', 'vendas']);

        $this->assertEmpty(array_diff(Pacotes::features('servicos'), $juntos));
        $this->assertEmpty(array_diff(Pacotes::features('vendas'), $juntos));
        $this->assertSame(count($juntos), count(array_unique($juntos)));
        $this->assertContains('tabela_crm_leads', $juntos);
        $this->assertContains('tabela_quotes', $juntos);
        $this->assertSame(Pacotes::features('manutencao'), Pacotes::featuresDeProdutos(['manutencao']));
    }
}
