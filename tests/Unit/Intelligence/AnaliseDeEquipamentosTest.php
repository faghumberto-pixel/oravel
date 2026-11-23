<?php

namespace Tests\Unit\Intelligence;

use App\Domain\Intelligence\Services\AnaliseDeEquipamentos;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/** Puro (sem banco): vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/Intelligence */
class AnaliseDeEquipamentosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $cfg = require __DIR__.'/../../../config/intelligence.php';
        $app = new Container;
        $app->instance('config', new Repository(['intelligence' => $cfg]));
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
    }

    private function eq(array $extra = []): array
    {
        // mês de 30 dias (setembro): 15 dias locado = 50% de utilização
        return array_merge(['patrimonio' => 'EMP-1', 'nome' => 'Empilhadeira', 'categoria' => 'Empilhadeira', 'ano_fabricacao' => 2018, 'valor' => 100000.0, 'receita' => 9000.0,
            'custo_mes' => 1000.0, 'custo_acumulado' => 10000.0, 'os_corretivas' => 2, 'os_preventivas' => 1, 'reincidencias' => 0, 'dias_locado' => 15, 'dias_indisponivel' => 3], $extra);
    }

    private function um(array $extra = [], array $anteriores = []): array
    {
        $l = $this->eq($extra);

        return (new AnaliseDeEquipamentos)->analisar([$l], '2026-09-30', $anteriores)[$l['patrimonio']];
    }

    public function test_calcula_os_indicadores_do_equipamento(): void
    {
        $a = $this->um();

        $this->assertSame(50.0, $a['utilizacao_pct']);
        $this->assertSame(90.0, $a['disponibilidade_pct']);
        $this->assertSame(0.1, $a['razao_custo_valor']);
        $this->assertSame(11.11, $a['custo_sobre_receita_pct']);
        $this->assertSame(0.0, $a['reincidencia_pct']);
        $this->assertSame(8, $a['idade_anos']);
        // (9000 - 1000) * 365/30 / 100000 * 100
        $this->assertEquals(97.33, $a['retorno_anual_pct']);
        $this->assertSame('saudavel', $a['classificacao']);
        $this->assertSame(1, $a['versao_regras']);
        $this->assertNull($a['tendencia'], 'sem mês anterior não há tendência');
    }

    public function test_trocar_ou_vender_pela_razao_do_erp_e_o_motivo_traz_o_numero(): void
    {
        $a = $this->um(['custo_acumulado' => 67000.0]);

        $this->assertSame('trocar_vender', $a['classificacao']);
        $this->assertStringContainsString('67,0%', $a['motivo']);
        $this->assertStringContainsString('60,0%', $a['motivo']);
        // limiar exatamente no limite (60%) já é "trocar ou vender"
        $this->assertSame('trocar_vender', $this->um(['custo_acumulado' => 60000.0])['classificacao']);
        $this->assertNotSame('trocar_vender', $this->um(['custo_acumulado' => 59990.0])['classificacao']);
    }

    public function test_trocar_ou_vender_por_retorno_negativo_dois_meses_com_baixa_utilizacao(): void
    {
        $ant = ['EMP-1' => ['linha' => $this->eq(), 'analise' => ['retorno_anual_pct' => -5.0, 'classificacao' => 'revisar']]];
        $base = ['receita' => 500.0, 'custo_mes' => 1500.0, 'dias_locado' => 3];

        $a = $this->um($base, $ant);
        $this->assertSame('trocar_vender', $a['classificacao']);
        $this->assertStringContainsString('dois meses seguidos', $a['motivo']);

        // só um mês negativo (ou utilização alta) não basta
        $this->assertNotSame('trocar_vender', $this->um($base)['classificacao']);
        $this->assertNotSame('trocar_vender', $this->um(['dias_locado' => 25] + $base, $ant)['classificacao']);
    }

    public function test_revisar_junta_todos_os_motivos_que_bateram(): void
    {
        $a = $this->um(['custo_acumulado' => 45000.0, 'os_corretivas' => 4, 'reincidencias' => 2, 'custo_mes' => 3500.0]);

        $this->assertSame('revisar', $a['classificacao']);
        $this->assertStringContainsString('45,0%', $a['motivo']);
        $this->assertStringContainsString('2 de 4 corretivas', $a['motivo']);
        $this->assertStringContainsString('38,9%', $a['motivo']);
    }

    public function test_uma_reincidencia_em_uma_corretiva_nao_e_sinal(): void
    {
        $a = $this->um(['os_corretivas' => 1, 'reincidencias' => 1]);

        $this->assertSame(100.0, $a['reincidencia_pct']);
        $this->assertSame('saudavel', $a['classificacao'], 'abaixo do mínimo de corretivas: ruído, não sinal');
    }

    public function test_subutilizado_e_risco_de_parada(): void
    {
        $sub = $this->um(['dias_locado' => 6, 'dias_indisponivel' => 2]);
        $this->assertSame('subutilizado', $sub['classificacao']);
        $this->assertStringContainsString('20,0%', $sub['motivo']);

        // sem mês anterior: indisponível em mais de 25% do mês e com corretiva
        $risco = $this->um(['dias_locado' => 20, 'dias_indisponivel' => 10]);
        $this->assertSame('risco_parada', $risco['classificacao']);

        // com mês anterior, só é risco se as corretivas SUBIRAM
        $ant = ['EMP-1' => ['linha' => $this->eq(['os_corretivas' => 5]), 'analise' => ['retorno_anual_pct' => 10.0, 'classificacao' => 'saudavel']]];
        $this->assertSame('saudavel', $this->um(['dias_locado' => 20, 'dias_indisponivel' => 10], $ant)['classificacao']);
        $subiu = ['EMP-1' => ['linha' => $this->eq(['os_corretivas' => 1]), 'analise' => ['retorno_anual_pct' => 10.0, 'classificacao' => 'saudavel']]];
        $this->assertSame('risco_parada', $this->um(['dias_locado' => 20, 'dias_indisponivel' => 10], $subiu)['classificacao']);
    }

    public function test_sem_dados_nao_inventa_classificacao(): void
    {
        $a = $this->um(['valor' => null, 'receita' => null, 'custo_mes' => null, 'custo_acumulado' => null, 'os_corretivas' => null, 'reincidencias' => null, 'dias_locado' => null, 'dias_indisponivel' => null]);

        $this->assertSame('sem_dados', $a['classificacao']);
        $this->assertNull($a['utilizacao_pct']);
        $this->assertNull($a['retorno_anual_pct']);
        $this->assertNull($a['razao_custo_valor']);
        $this->assertSame(8, $a['idade_anos'], 'a idade sozinha não basta para avaliar');
    }

    public function test_tendencia_compara_a_gravidade_com_o_mes_anterior(): void
    {
        $pior = ['EMP-1' => ['linha' => $this->eq(), 'analise' => ['retorno_anual_pct' => 10.0, 'classificacao' => 'saudavel']]];
        $this->assertSame('piorando', $this->um(['custo_acumulado' => 70000.0], $pior)['tendencia']);

        $melhor = ['EMP-1' => ['linha' => $this->eq(), 'analise' => ['retorno_anual_pct' => 10.0, 'classificacao' => 'trocar_vender']]];
        $this->assertSame('melhorando', $this->um([], $melhor)['tendencia']);

        $igual = ['EMP-1' => ['linha' => $this->eq(), 'analise' => ['retorno_anual_pct' => 10.0, 'classificacao' => 'saudavel']]];
        $this->assertSame('estavel', $this->um([], $igual)['tendencia']);
    }

    public function test_agregados_que_a_aba_gera_para_a_empresa(): void
    {
        $linhas = [
            $this->eq(['patrimonio' => 'A', 'dias_locado' => 30, 'dias_indisponivel' => 0, 'valor' => 100000.0, 'receita' => 9000.0, 'custo_mes' => 1000.0, 'os_corretivas' => 2, 'os_preventivas' => 1]),
            $this->eq(['patrimonio' => 'B', 'dias_locado' => 15, 'dias_indisponivel' => 15, 'valor' => 50000.0, 'receita' => 3000.0, 'custo_mes' => 500.0, 'os_corretivas' => 1, 'os_preventivas' => 0]),
        ];
        $a = (new AnaliseDeEquipamentos)->agregados($linhas, '2026-09-30');

        $this->assertSame(2, $a['frota_total']);
        $this->assertSame(2, $a['frota_locada'], '(30+15) dias ÷ 30 = 1,5 equipamento-mês, arredondado');
        // 45 dias ÷ 30 = 1,5 locado e 15 ÷ 30 = 0,5 indisponível: arredondar os dois para cima daria 3 > 2 equipamentos, então o excedente sai da indisponível
        $this->assertSame(0, $a['frota_indisponivel']);
        $this->assertSame(150000.0, $a['valor_frota']);
        $this->assertSame(12000.0, $a['receita_mensal']);
        $this->assertSame(1500.0, $a['custo_manutencao']);
        $this->assertSame(3, $a['os_corretivas']);
        $this->assertSame(1, $a['os_preventivas']);
        $this->assertLessThanOrEqual($a['frota_total'], $a['frota_locada'] + $a['frota_indisponivel'], 'nunca passa do total');

        // campo que nenhuma linha informou não é inventado
        $b = (new AnaliseDeEquipamentos)->agregados([['patrimonio' => 'A', 'nome' => 'x']], '2026-09-30');
        $this->assertSame(['frota_total' => 1], $b);
    }

    public function test_resumo_da_empresa_alimenta_os_indicadores_novos(): void
    {
        $linhas = [
            $this->eq(['patrimonio' => 'A', 'os_corretivas' => 4, 'reincidencias' => 2, 'custo_acumulado' => 70000.0, 'valor' => 100000.0]),
            $this->eq(['patrimonio' => 'B', 'os_corretivas' => 6, 'reincidencias' => 1, 'valor' => 100000.0, 'dias_locado' => 3]),
            $this->eq(['patrimonio' => 'C', 'os_corretivas' => 0, 'reincidencias' => 0, 'valor' => 200000.0]),
            $this->eq(['patrimonio' => 'D', 'os_corretivas' => 0, 'reincidencias' => 0, 'valor' => 200000.0]),
        ];
        $svc = new AnaliseDeEquipamentos;
        $r = $svc->resumoDaEmpresa($linhas, $svc->analisar($linhas, '2026-09-30'));

        $this->assertSame(30.0, $r['equip_reincidencia_pct'], '3 reincidências em 10 corretivas');
        $this->assertSame(25.0, $r['equip_subutilizados_pct'], '1 de 4 equipamentos');
        $this->assertSame(16.67, $r['equip_valor_trocar_vender_pct'], 'R$ 100 mil de R$ 600 mil');
    }
}
