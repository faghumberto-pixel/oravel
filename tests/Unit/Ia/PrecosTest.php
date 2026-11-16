<?php

namespace Tests\Unit\Ia;

use App\Support\Ia\Precos;
use PHPUnit\Framework\TestCase;

/** Puro (sem app/banco): vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/Ia */
class PrecosTest extends TestCase
{
    public function test_custo_do_haiku(): void
    {
        // 4.000 entrada x US$0,10/mi + 1.500 saida x US$0,50/mi = 0,0004 + 0,00075
        $this->assertEqualsWithDelta(0.00115, Precos::custoUsd('claude-haiku-5-5', 4000, 1500), 1e-9);
    }

    public function test_custo_do_sonnet(): void
    {
        // 1 mi entrada x US$2 + 1 mi saida x US$10
        $this->assertEqualsWithDelta(12.0, Precos::custoUsd('claude-sonnet-5', 1_000_000, 1_000_000), 1e-9);
    }

    public function test_cache_custa_menos_na_leitura_e_mais_na_escrita(): void
    {
        $leitura = Precos::custoUsd('claude-sonnet-5', 0, 0, 1_000_000, 0);   // 10% de US$2
        $escrita = Precos::custoUsd('claude-sonnet-5', 0, 0, 0, 1_000_000);   // 125% de US$2

        $this->assertEqualsWithDelta(0.2, $leitura, 1e-9);
        $this->assertEqualsWithDelta(2.5, $escrita, 1e-9);
    }

    public function test_modelo_desconhecido_usa_preco_do_sonnet_e_nunca_subestima(): void
    {
        $this->assertFalse(Precos::conhecido('claude-modelo-novo'));
        $this->assertEqualsWithDelta(
            Precos::custoUsd('claude-sonnet-5', 5000, 2000),
            Precos::custoUsd('claude-modelo-novo', 5000, 2000),
            1e-9
        );
    }

    public function test_zero_tokens_custa_zero(): void
    {
        $this->assertSame(0.0, Precos::custoUsd('claude-haiku-5-5', 0, 0));
    }

    public function test_o_haiku_e_bem_mais_barato_que_o_sonnet(): void
    {
        $this->assertGreaterThan(15, Precos::custoUsd('claude-sonnet-5', 7000, 1000) / Precos::custoUsd('claude-haiku-5-5', 7000, 1000));
    }
}
