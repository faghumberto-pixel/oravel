<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\MaintenanceKanban;
use App\Models\MaintenanceOrder;
use Filament\Widgets\ChartWidget;

class MaintenanceByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Manutenções por Status';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['md' => 1];

    /**
     * Paleta categorica validada (dataviz skill, dark mode) -- as cores
     * hexadecimais cruas do Kanban (Tailwind amber/orange/slate) nao
     * passam no validador de acessibilidade para esta superficie escura,
     * entao aqui usamos a paleta validada. Mapeada por chave de status (nao
     * um array indexado) porque as colunas de MaintenanceKanban::statusMap()
     * podem vir com 6 ou 8 entradas, dependendo de
     * enabled_modules['kanban_oficina_extra'] do tenant -- indexar por
     * posicao desalinharia as cores quando colunas do meio somem.
     */
    private const COLOR_MAP = [
        'aguardando_diagnostico' => '#3987e5',
        'em_manutencao' => '#199e70',
        'aguardando_peca' => '#c98500',
        'aguardando_peca_canibalizado' => '#a15c00',
        'teste_qualidade' => '#008300',
        'pronto_giro' => '#0d9488',
        'pendencia' => '#9085e9',
        'concluido' => '#e66767',
    ];

    /**
     * Mesmas colunas reais do Kanban (MaintenanceKanban::statusMap('oficina')),
     * ja' filtradas pelo toggle de tenant -- elimina a duplicacao que existia
     * antes (lista hardcoded independente, sujeita a ficar dessincronizada).
     */
    private function statuses(): array
    {
        return array_map(fn ($column) => $column['title'], MaintenanceKanban::statusMap('oficina'));
    }

    protected function getData(): array
    {
        $statuses = $this->statuses();

        $counts = [];
        $colors = [];
        foreach ($statuses as $status => $label) {
            $counts[] = MaintenanceOrder::where('internal_status', $status)->count();
            $colors[] = self::COLOR_MAP[$status] ?? '#64748b';
        }

        return [
            'datasets' => [
                [
                    'label' => 'OS',
                    'data' => $counts,
                    'backgroundColor' => $colors,
                    'borderRadius' => 6,
                ],
            ],
            'labels' => array_values($statuses),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => '#334155'],
                    'ticks' => ['precision' => 0, 'color' => '#94a3b8'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#94a3b8'],
                ],
            ],
        ];
    }
}
