<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Widgets;

use App\Models\SalesLeadInteraction;
use Filament\Widgets\ChartWidget;

/**
 * "Quantos e quais leads eu contatei por canal" (pedido do usuario
 * 2026-08-10) -- conta INTERACOES (nao leads distintos, um lead pode ter
 * varias) por canal (telefone/email/whatsapp/reuniao presencial/reuniao
 * online/visita/outro). Clicar na barra nao navega direto (ChartWidget nao
 * suporta link nativo por segmento) -- o filtro 'interaction_channel' na
 * tabela (mesmo nome usado aqui) resolve o "quais foram" quando o usuario
 * seleciona o canal na listagem.
 */
class InteractionChannelChart extends ChartWidget
{
    protected static ?string $heading = 'Contatos Feitos por Canal';

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $labels = SalesLeadInteraction::channelLabels();

        $counts = SalesLeadInteraction::query()
            ->selectRaw('channel, count(*) as total')
            ->groupBy('channel')
            ->pluck('total', 'channel');

        $rows = $counts->keys()->map(fn (string $channel) => $labels[$channel] ?? $channel)->all();

        return [
            'datasets' => [
                [
                    'label' => 'Contatos',
                    'data' => $counts->values()->all(),
                    'backgroundColor' => '#0ea5e9',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $rows,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
