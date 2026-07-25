<?php

namespace App\Filament\Resources\CrmLeadResource\Widgets;

use App\Models\CrmLead;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;

class LeadsBySourceChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Leads por Origem';

    protected static ?string $maxHeight = '260px';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    private const LABELS = [
        CrmLead::SOURCE_INDICACAO => 'Indicação',
        CrmLead::SOURCE_SITE => 'Site',
        CrmLead::SOURCE_CONTATO_FRIO => 'Contato Frio',
        CrmLead::SOURCE_EVENTO => 'Evento',
        CrmLead::SOURCE_OUTRO => 'Outro',
    ];

    protected function getData(): array
    {
        $counts = CrmLead::query()
            ->selectRaw('COALESCE(source, \'nao_informado\') as origem, count(*) as total')
            ->groupBy('origem')
            ->pluck('total', 'origem');

        $labels = $counts->keys()->map(fn ($key) => self::LABELS[$key] ?? 'Não Informado')->all();

        return [
            'datasets' => [[
                'data' => $counts->values()->all(),
                'backgroundColor' => ['#3987e5', '#199e70', '#c98500', '#e6534d', '#8b5cf6', '#64748b'],
                'borderWidth' => 2,
                'borderColor' => '#0d1321',
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => ['color' => '#94a3b8', 'padding' => 12],
                ],
            ],
        ];
    }
}
