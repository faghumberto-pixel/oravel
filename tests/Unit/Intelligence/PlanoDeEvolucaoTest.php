<?php

namespace Tests\Unit\Intelligence;

use App\Domain\Intelligence\Services\PlanoDeEvolucao;
use App\Domain\Intelligence\Support\Catalogo;
use App\Domain\Intelligence\Support\Graficos;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/** Puro (sem banco): vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/Intelligence */
class PlanoDeEvolucaoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = new Container;
        $app->instance('config', new Repository(['intelligence' => ['areas' => ['frota' => 'Saúde da Frota', 'financeira' => 'Saúde Financeira', 'oficina' => 'Saúde da Oficina', 'geral' => 'Índice Geral']]]));
        Container::setInstance($app);
        Facade::setFacadeApplication($app);
    }

    private function ind(string $chave, string $area, float $valor, ?float $nota, ?float $anterior = null): array
    {
        return ['nota' => $nota, 'area' => $area, 'chave' => $chave, 'titulo' => Catalogo::indicadores()[$chave]['titulo'] ?? $chave, 'unidade' => '%', 'valor' => $valor,
            'anterior' => $anterior, 'delta' => $anterior === null ? null : $valor - $anterior, 'sentido' => null, 'serie' => []];
    }

    private function resumo(): array
    {
        return ['areas' => [
            ['chave' => 'frota', 'rotulo' => 'Saúde da Frota', 'nota' => 90.0, 'faixa' => 'Excelente', 'delta' => null, 'serie' => [], 'serie_alinhada' => []],
            ['chave' => 'financeira', 'rotulo' => 'Saúde Financeira', 'nota' => 20.0, 'faixa' => 'Crítico', 'delta' => null, 'serie' => [], 'serie_alinhada' => []],
            ['chave' => 'oficina', 'rotulo' => 'Saúde da Oficina', 'nota' => 55.0, 'faixa' => 'Atenção', 'delta' => null, 'serie' => [], 'serie_alinhada' => []],
        ], 'indicadores' => [
            $this->ind('disponibilidade_frota', 'frota', 94.0, 96.0),
            $this->ind('inadimplencia_pct', 'financeira', 14.0, 8.0, 10.0),
            $this->ind('margem_operacional', 'financeira', 10.0, 33.0),
            $this->ind('preventiva_pct', 'oficina', 30.0, 40.0),
            $this->ind('retrabalho_inexistente', 'oficina', 1.0, null),
            $this->ind('receita_por_equipamento', 'frota', 4000.0, null),
        ]];
    }

    public function test_diagnostico_ordena_do_pior_e_separa_fortes(): void
    {
        $d = (new PlanoDeEvolucao)->diagnostico($this->resumo());

        $this->assertSame(['inadimplencia_pct', 'margem_operacional', 'preventiva_pct'], array_column($d['atencao'], 'chave'));
        $this->assertSame(['disponibilidade_frota'], array_column($d['fortes'], 'chave'));
        $this->assertSame('Crítico', $d['atencao'][0]['faixa']);
        $this->assertNotSame('', $d['atencao'][0]['impacto']);
        $this->assertSame('Saúde Financeira', $d['areas'][0]['rotulo'], 'áreas da pior para a melhor');
        $this->assertNotContains('receita_por_equipamento', array_column($d['atencao'], 'chave'), 'informativo (sem nota) não vira ponto de atenção');
    }

    public function test_plano_prioriza_define_prazo_e_meta_do_ciclo(): void
    {
        $p = (new PlanoDeEvolucao)->plano($this->resumo());

        $this->assertSame('Saúde Financeira', $p['foco']);
        $inad = collect($p['itens'])->firstWhere('chave', 'inadimplencia_pct');
        $this->assertSame('Alta', $inad['prioridade']);
        $this->assertSame(30, $inad['prazo']);
        $this->assertSame(2.0, $inad['meta'], 'a meta é o valor que vale 100 no catálogo');
        $this->assertSame(8.0, $inad['alvo_ciclo'], 'meio do caminho entre 14 e 2');
        $this->assertNotEmpty($inad['acoes']);
        $this->assertSame(30, collect($p['itens'])->firstWhere('chave', 'margem_operacional')['prazo'], 'nota 33 (< 35) é prioridade alta');
        $prev = collect($p['itens'])->firstWhere('chave', 'preventiva_pct');
        $this->assertSame(['Média', 60], [$prev['prioridade'], $prev['prazo']], 'nota 40 é prioridade média, 60 dias');
        $this->assertArrayNotHasKey('disponibilidade_frota', array_flip(array_column($p['itens'], 'chave')), 'nota 80+ não precisa de plano');
        $this->assertCount(array_sum(array_map('count', $p['prazos'])), $p['itens']);
    }

    public function test_tudo_bem_gera_plano_vazio(): void
    {
        $r = ['areas' => [], 'indicadores' => [$this->ind('disponibilidade_frota', 'frota', 96.0, 100.0)]];
        $p = (new PlanoDeEvolucao)->plano($r);

        $this->assertSame([], $p['itens']);
        $this->assertNull((new Graficos)->esforcoDoPlano($p));
    }

    public function test_todo_indicador_com_nota_tem_orientacao(): void
    {
        foreach (Catalogo::indicadores() as $chave => $def) {
            if ($def['ruim'] !== null) {
                $o = Catalogo::orientacao()[$chave] ?? null;
                $this->assertNotNull($o, "falta orientação de {$chave}");
                $this->assertNotSame('', $o['impacto']);
                $this->assertGreaterThanOrEqual(3, count($o['acoes']), $chave);
            }
        }
    }
}
