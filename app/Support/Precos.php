<?php

namespace App\Support;

/** Valor cheio × valor recorrente (desconto de config/precos.php). Toda a conta de preço passa por aqui. */
class Precos
{
    public static function descontoPercentual(): float
    {
        return (float) config('precos.desconto_recorrente_percentual', 40);
    }

    /** Valor cobrado de quem paga recorrente: cheio menos o desconto (centavos arredondados). */
    public static function recorrente(float $cheio): float
    {
        return round($cheio * (1 - static::descontoPercentual() / 100), 2);
    }

    /** Economia mensal de quem paga recorrente. */
    public static function economia(float $cheio): float
    {
        return round($cheio - static::recorrente($cheio), 2);
    }

    /** Valor mensal conforme a forma de pagamento: cartão recorrente leva o desconto; boleto/Pix todo mês paga o cheio. */
    public static function paraForma(float $cheio, string $forma): float
    {
        return $forma === 'cartao' ? static::recorrente($cheio) : round($cheio, 2);
    }

    public static function brl(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }
}
