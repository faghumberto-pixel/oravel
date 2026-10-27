<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\Contract;

class ContratosARenovar extends ComercialBlock
{
    public static function key(): string
    {
        return 'comercial.contratos_a_renovar';
    }

    public static function title(): string
    {
        return 'Contratos a renovar';
    }

    public static function subtitle(): string
    {
        return 'Por cliente, vencendo em 90 dias';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $contratos = Contract::query()
            ->with('client:id,name')
            ->where('status', 'Ativo')
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(90)->toDateString()])
            ->get(['client_id', 'end_date']);

        $linhas = $contratos->groupBy('client_id')
            ->map(fn ($grupo) => ['cliente' => $grupo->first()->client?->name ?? '—', 'n' => $grupo->count(), 'dias' => (int) now()->startOfDay()->diffInDays($grupo->min('end_date'))])
            ->sortBy('dias')
            ->take(6)
            ->map(fn ($r) => [$r['cliente'], (string) $r['n'], $r['dias'] <= 30 ? Payload::pill('warn', $r['dias'].' dias') : $r['dias'].' dias'])
            ->values()
            ->all();

        return Payload::table(['Cliente', '>Contratos', '>Vence'], $linhas, 'Nenhum contrato vence nos próximos 90 dias.');
    }
}
