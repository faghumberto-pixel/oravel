<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Vencimentos dos veículos (ativos do grupo "veículo"): seguro, IPVA, licenciamento (emplacamento) e
 * tacógrafo (só veículo pesado). Concentra rótulos, situação e filtros usados pela tela
 * "Vencimento de veículos" e pelos filtros da lista de ativos.
 */
class VehicleExpirations
{
    /** @return array<string, string> coluna => rótulo */
    public static function documents(): array
    {
        return [
            'seguro_vencimento' => 'Seguro',
            'ipva_vencimento' => 'IPVA',
            'licenciamento_vencimento' => 'Licenciamento',
            'tacografo_vencimento' => 'Tacógrafo',
        ];
    }

    /** @return array<string, string> */
    public static function situations(): array
    {
        return [
            'vencido' => 'Vencidos',
            'ate_30' => 'Vencem em até 30 dias',
            'ate_60' => 'Vencem em até 60 dias',
            'ate_90' => 'Vencem em até 90 dias',
            'sem_data' => 'Sem data cadastrada',
        ];
    }

    /** [texto, cor, descrição] de uma data de vencimento. */
    public static function status(?Carbon $date): array
    {
        if (! $date) {
            return ['—', 'gray', 'sem data'];
        }

        $days = (int) now()->startOfDay()->diffInDays($date->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 => [$date->format('d/m/Y'), 'danger', 'venceu há '.abs($days).' dia(s)'],
            $days === 0 => [$date->format('d/m/Y'), 'danger', 'vence hoje'],
            $days <= 30 => [$date->format('d/m/Y'), 'warning', "vence em {$days} dia(s)"],
            $days <= 90 => [$date->format('d/m/Y'), 'info', "vence em {$days} dia(s)"],
            default => [$date->format('d/m/Y'), 'success', "vence em {$days} dia(s)"],
        };
    }

    /**
     * Restringe a veículos com algum vencimento na situação pedida. $document = uma coluna de documents()
     * ou null (qualquer um). O tacógrafo só conta para veículo pesado.
     */
    public static function apply(Builder $query, ?string $document, ?string $situation): Builder
    {
        if (! $situation) {
            return filled($document) ? $query->whereNotNull($document) : $query;
        }

        $columns = filled($document) && array_key_exists($document, self::documents())
            ? [$document]
            : array_keys(self::documents());

        return $query->where(function (Builder $w) use ($columns, $situation) {
            foreach ($columns as $column) {
                $w->orWhere(function (Builder $c) use ($column, $situation) {
                    if ($column === 'tacografo_vencimento') {
                        $c->where('veiculo_pesado', true);
                    }

                    $today = now()->toDateString();

                    match ($situation) {
                        'vencido' => $c->where($column, '<', $today),
                        'ate_30' => $c->whereBetween($column, [$today, now()->addDays(30)->toDateString()]),
                        'ate_60' => $c->whereBetween($column, [$today, now()->addDays(60)->toDateString()]),
                        'ate_90' => $c->whereBetween($column, [$today, now()->addDays(90)->toDateString()]),
                        default => $c->whereNull($column),
                    };
                });
            }
        });
    }
}
