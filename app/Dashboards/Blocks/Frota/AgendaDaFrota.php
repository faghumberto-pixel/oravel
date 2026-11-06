<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\FrotaMulta;
use App\Models\FrotaTrocaOleo;
use Illuminate\Support\Carbon;

class AgendaDaFrota extends FrotaBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'frota.agenda_frota';
    }

    public static function title(): string
    {
        return 'Agenda da frota';
    }

    public static function subtitle(): string
    {
        return 'Trocas de óleo e multas que vencem nos próximos 30 dias, em ordem de data';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $eventos = [];
        $trocas = FrotaTrocaOleo::query()->with('veiculo:id,name,placa')->whereNotNull('proxima_troca_data')->orderByDesc('realizado_em')->get()->unique('ativo_id')
            ->filter(fn ($t) => $t->proxima_troca_data->lte(now()->addDays(30)));
        foreach ($trocas as $t) {
            $dias = (int) now()->startOfDay()->diffInDays($t->proxima_troca_data, false);
            $eventos[] = [$t->proxima_troca_data, 'Troca de óleo — '.($t->veiculo?->name ?? 'veículo'), ($t->veiculo?->placa ?? 'sem placa').' · '.($dias < 0 ? 'vencida há '.abs($dias).' dias' : 'em '.$dias.' dias'), $dias < 0 ? 'crit' : 'warn', 'óleo'];
        }
        $multas = FrotaMulta::query()->with('veiculo:id,name,placa')->whereNotIn('situacao', [FrotaMulta::PAGA, FrotaMulta::CANCELADA])->whereNotNull('vencimento')
            ->where('vencimento', '<=', now()->addDays(30)->toDateString())->orderBy('vencimento')->limit(8)->get();
        foreach ($multas as $m) {
            $venc = Carbon::parse($m->vencimento);
            $eventos[] = [$venc, 'Multa — '.($m->veiculo?->name ?? 'veículo'), 'R$ '.number_format((float) $m->valor, 2, ',', '.').' · '.($m->veiculo?->placa ?? 'sem placa'), $venc->isPast() ? 'crit' : 'warn', 'multa'];
        }
        usort($eventos, fn ($a, $b) => $a[0] <=> $b[0]);

        return Payload::timeline(array_map(fn ($e) => [$e[0]->format('d/m'), $e[1], $e[2], $e[3], $e[4]], array_slice($eventos, 0, 8)), 'Nada vencendo nos próximos 30 dias.');
    }
}
