<?php

namespace Tests\Unit\Assistente;

use App\Support\Assistente\Resultado;
use PHPUnit\Framework\TestCase;

/** Puro, sem app nem banco: vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/Assistente/ResultadoTest.php */
class ResultadoTest extends TestCase
{
    private const U1 = '76995301-3be6-47e8-b826-d9246881541a';

    private const U2 = 'eae133f8-788c-4778-acd3-d5671eab9ef6';

    public function test_remove_coluna_cujo_valor_e_so_uuid(): void
    {
        $r = Resultado::semCodigosInternos([
            ['pedido' => self::U1, 'fornecedor' => 'Lubrimax', 'valor_total' => '2460.00'],
            ['pedido' => self::U2, 'fornecedor' => 'Moura', 'valor_total' => '3120.00'],
        ]);

        $this->assertSame(['fornecedor', 'valor_total'], array_keys($r[0]));
        $this->assertCount(2, $r);
        $this->assertSame('Moura', $r[1]['fornecedor']);
    }

    public function test_remove_colunas_id_e_terminadas_em_id_mesmo_sem_ser_uuid(): void
    {
        $r = Resultado::semCodigosInternos([['id' => 7, 'material_id' => 3, 'nome' => 'Filtro', 'ID_externo' => 'x']]);

        $this->assertSame(['nome', 'ID_externo'], array_keys($r[0]));
    }

    public function test_mantem_texto_que_nao_e_uuid_e_colunas_mistas(): void
    {
        $r = Resultado::semCodigosInternos([
            ['sku' => 'ELE-002', 'obs' => self::U1],
            ['sku' => 'ELE-003', 'obs' => 'texto livre'],
        ]);

        $this->assertSame(['sku', 'obs'], array_keys($r[0])); // obs mistura uuid e texto: fica
    }

    public function test_coluna_so_com_nulos_nao_e_removida(): void
    {
        $r = Resultado::semCodigosInternos([['fornecedor' => null, 'nome' => 'A'], ['fornecedor' => null, 'nome' => 'B']]);

        $this->assertSame(['fornecedor', 'nome'], array_keys($r[0]));
    }

    public function test_se_so_sobrar_codigo_devolve_como_veio(): void
    {
        $linhas = [['id' => self::U1], ['id' => self::U2]];

        $this->assertSame($linhas, Resultado::semCodigosInternos($linhas));
    }

    public function test_vazio_e_numeros_inalterados(): void
    {
        $this->assertSame([], Resultado::semCodigosInternos([]));

        $linhas = [['qtd' => 3, 'custo' => '10.50']];
        $this->assertSame($linhas, Resultado::semCodigosInternos($linhas));
    }
}
