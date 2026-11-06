<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaAbastecimento;

class CombustivelPorPeriodo extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.combustivel_periodo';
    }

    public static function title(): string
    {
        return 'Combustível por mês';
    }

    public static function subtitle(): string
    {
        return 'Gasto (R$ mil) e litros abastecidos, últimos 6 meses';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_frota_abastecimentos';
    }

    public static function sourceModel(): string
    {
        return FrotaAbastecimento::class;
    }

    public function data(array $config): array
    {
        $meses = 6;
        $inicio = now()->startOfMonth()->subMonths($meses - 1);
        $abast = FrotaAbastecimento::query()->where('abastecido_em', '>=', $inicio)->get(['abastecido_em', 'valor_total', 'litros']);
        $rotulos = [];
        $valor = [];
        $litros = [];
        for ($i = 0; $i < $meses; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $doMes = $abast->filter(fn ($a) => $a->abastecido_em->isSameMonth($mes));
            $rotulos[] = ucfirst($mes->translatedFormat('M'));
            $valor[] = round($doMes->sum('valor_total') / 1000, 1);
            $litros[] = round($doMes->sum('litros') / 100, 1);
        }

        return Payload::cols($rotulos, $valor, $litros, 'Gasto (R$ mil)', 'Litros (centenas)');
    }
}
