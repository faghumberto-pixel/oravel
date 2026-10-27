<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\PartsRequest;

class AguardandoPeca extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.aguardando_peca';
    }

    public static function title(): string
    {
        return 'Aguardando peça';
    }

    public static function subtitle(): string
    {
        return 'Atendimentos parados por falta de item';
    }

    public static function size(): int
    {
        return 12;
    }

    public function data(array $config): array
    {
        $ordens = $this->abertas()
            ->with(['asset:id,name,client_id', 'asset.client:id,name', 'client:id,name'])
            ->where('internal_status', 'aguardando_peca')
            ->orderBy('updated_at')
            ->limit(10)
            ->get();

        $pedidos = PartsRequest::query()
            ->with('material:id,name')
            ->whereIn('maintenance_order_id', $ordens->pluck('id'))
            ->whereIn('status', ['pendente', 'pedida'])
            ->get()
            ->groupBy('maintenance_order_id');

        $linhas = $ordens->map(function ($os) use ($pedidos) {
            $pedido = $pedidos->get($os->id)?->first();

            return [
                $os->os_number ?: '#'.substr($os->id, 0, 6),
                $os->asset?->name ?? '—',
                $os->client?->name ?? $os->asset?->client?->name ?? '—',
                $pedido?->material?->name ?? '—',
                match ($pedido?->status) {
                    'pedida' => Payload::pill('warn', 'Pedida'),
                    'pendente' => Payload::pill('crit', 'Sem compra'),
                    default => Payload::pill('n', 'Sem pedido'),
                },
                (string) max(0, (int) $os->updated_at->diffInDays(now())),
            ];
        })->all();

        return Payload::table(['Atendimento', 'Máquina', 'Cliente', 'Peça', 'Situação', '>Dias'], $linhas, 'Nenhum atendimento esperando peça.');
    }
}
