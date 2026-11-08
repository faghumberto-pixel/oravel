<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Feriados nacionais do Brasil para importar no calendário de SLA. Estaduais e municipais a empresa cadastra à mão. */
class FeriadosBr
{
    /**
     * @return array<string, string> data (Y-m-d) => nome
     */
    public static function nacionais(int $ano, bool $pontosFacultativos = false): array
    {
        $pascoa = self::pascoa($ano);

        $lista = [
            "{$ano}-01-01" => 'Confraternização Universal',
            $pascoa->subDays(2)->toDateString() => 'Sexta-feira Santa',
            "{$ano}-04-21" => 'Tiradentes',
            "{$ano}-05-01" => 'Dia do Trabalho',
            "{$ano}-09-07" => 'Independência do Brasil',
            "{$ano}-10-12" => 'Nossa Senhora Aparecida',
            "{$ano}-11-02" => 'Finados',
            "{$ano}-11-15" => 'Proclamação da República',
            "{$ano}-12-25" => 'Natal',
        ];

        if ($ano >= 2024) {
            $lista["{$ano}-11-20"] = 'Consciência Negra';
        }

        if ($pontosFacultativos) {
            $lista[$pascoa->subDays(48)->toDateString()] = 'Carnaval (segunda-feira)';
            $lista[$pascoa->subDays(47)->toDateString()] = 'Carnaval (terça-feira)';
            $lista[$pascoa->addDays(60)->toDateString()] = 'Corpus Christi';
        }

        ksort($lista);

        return $lista;
    }

    /** Domingo de Páscoa (algoritmo de Meeus/Jones/Butcher: não depende da extensão calendar do PHP). */
    public static function pascoa(int $ano): CarbonImmutable
    {
        $a = $ano % 19;
        $b = intdiv($ano, 100);
        $c = $ano % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($ano, $mes, $dia, 0, 0, 0, 'UTC');
    }
}
