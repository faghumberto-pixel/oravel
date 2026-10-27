<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\SolicitacaoLocacao;
use Illuminate\Support\Carbon;

class PedidosDeLocacao extends ComercialBlock
{
    public static function key(): string
    {
        return 'comercial.pedidos_locacao';
    }

    public static function title(): string
    {
        return 'Pedidos de locação';
    }

    public static function subtitle(): string
    {
        return 'Da solicitação até a saída do equipamento';
    }

    public static function size(): int
    {
        return 12;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_solicitacao_locacao';
    }

    public static function sourceModel(): string
    {
        return SolicitacaoLocacao::class;
    }

    public function data(array $config): array
    {
        $pedidos = SolicitacaoLocacao::query()
            ->with(['customer:id,name', 'category:id,name'])
            ->whereNotIn('status_comercial', ['cancelada'])
            ->whereNotNull('data_saida_prevista')
            ->where('data_saida_prevista', '>=', now()->subDays(7)->toDateString())
            ->orderBy('data_saida_prevista')
            ->limit(8)
            ->get();

        $linhas = $pedidos->map(fn ($p) => [
            $p->customer?->name ?? '—',
            $p->category?->name ?? '—',
            $p->purpose ?: '—',
            match ($p->status_comercial) {
                'contrato_fechado' => Payload::pill('ok', 'Contrato fechado'),
                'proposta_em_andamento' => Payload::pill('warn', 'Proposta em andamento'),
                default => Payload::pill('n', (string) str_replace('_', ' ', (string) $p->status_comercial)),
            },
            Carbon::parse($p->data_saida_prevista)->format('d/m'),
        ])->all();

        return Payload::table(['Cliente', 'Categoria', 'Finalidade', 'Situação', '>Saída prevista'], $linhas, 'Nenhum pedido de locação com saída prevista.');
    }
}
