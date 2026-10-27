<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaTrocaOleo;

class TrocasDeOleoAVencer extends FrotaBlock
{
    public static function key(): string
    {
        return 'frota.trocas_oleo_a_vencer';
    }

    public static function title(): string
    {
        return 'Trocas de óleo a vencer';
    }

    public static function subtitle(): string
    {
        return 'Próxima troca de cada veículo, em até 30 dias';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_frota_trocas_oleo';
    }

    public static function sourceModel(): string
    {
        return FrotaTrocaOleo::class;
    }

    public function data(array $config): array
    {
        // Vale a troca mais recente de cada veículo: ela é quem define a próxima.
        $ultimas = FrotaTrocaOleo::query()
            ->with('veiculo:id,name,placa')
            ->whereNotNull('proxima_troca_data')
            ->orderByDesc('realizado_em')
            ->get()
            ->unique('ativo_id')
            ->filter(fn ($t) => $t->proxima_troca_data->lte(now()->addDays(30)))
            ->sortBy('proxima_troca_data')
            ->take(6);

        $linhas = $ultimas->map(function ($t) {
            $dias = (int) now()->startOfDay()->diffInDays($t->proxima_troca_data, false);

            return [
                $t->veiculo?->name ?? '—',
                $t->veiculo?->placa ?? '—',
                $t->proxima_troca_data->format('d/m/Y'),
                $dias < 0 ? Payload::pill('crit', 'Vencida há '.abs($dias).' dias') : Payload::pill('warn', 'Em '.$dias.' dias'),
            ];
        })->values()->all();

        return Payload::table(['Veículo', 'Placa', 'Próxima troca', 'Situação'], $linhas, 'Nenhuma troca de óleo vencendo nos próximos 30 dias.');
    }
}
