<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrderMaterial;
use App\Models\Quote;
use App\Models\QuoteItem;

class AplicadasEOrcadas extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.aplicadas_x_orcadas';
    }

    public static function title(): string
    {
        return 'Peças aplicadas x orçadas';
    }

    public static function subtitle(): string
    {
        return 'Mês, R$ mil';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $inicio = now()->startOfMonth()->subMonths(5);

        $aplicadas = MaintenanceOrderMaterial::query()->where('created_at', '>=', $inicio)->get(['quantity', 'unit_price', 'created_at']);
        $orcadas = QuoteItem::query()
            ->whereNotNull('material_id')
            ->where('created_at', '>=', $inicio)
            ->whereHas('quote', fn ($q) => $q->whereIn('status', [Quote::STATUS_ENVIADO, Quote::STATUS_APROVADO, Quote::STATUS_CONCLUIDO]))
            ->get(['subtotal', 'created_at']);

        $labels = [];
        $a = [];
        $b = [];
        for ($i = 0; $i < 6; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $labels[] = ucfirst($mes->translatedFormat('M'));
            $a[] = round($aplicadas->filter(fn ($x) => $x->created_at->isSameMonth($mes))->sum(fn ($x) => $x->quantity * $x->unit_price) / 1000, 1);
            $b[] = round($orcadas->filter(fn ($x) => $x->created_at->isSameMonth($mes))->sum('subtotal') / 1000, 1);
        }

        return Payload::cols($labels, $a, $b, 'Aplicadas em atendimento', 'Orçadas ao cliente');
    }
}
