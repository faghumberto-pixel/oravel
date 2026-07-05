<?php

namespace App\Filament\Widgets;

use App\Models\EquipmentDamage;
use Filament\Widgets\ChartWidget;

class DamagesBySeverityChart extends ChartWidget
{
    protected static ?string $heading = 'Avarias por Severidade';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['md' => 1];

    /**
     * Vocabulario real de EquipmentDamage::SEVERITY_* -- unico usado no
     * sistema (severity e' NOT NULL, sem outros valores em uso).
     */
    private const SEVERITIES = [
        EquipmentDamage::SEVERITY_LEVE => 'Leve',
        EquipmentDamage::SEVERITY_MODERADA => 'Moderada',
        EquipmentDamage::SEVERITY_GRAVE => 'Grave',
    ];

    /**
     * Severidade e' um indicador de estado (risco crescente), nao uma
     * categoria generica -- usa a paleta de status reservada da dataviz
     * skill (good/warning/critical), nao a paleta categorica.
     */
    private const COLORS = ['#0ca30c', '#fab219', '#d03b3b'];

    protected function getData(): array
    {
        $counts = [];
        foreach (self::SEVERITIES as $severity => $label) {
            $counts[] = EquipmentDamage::where('severity', $severity)->count();
        }

        return [
            'datasets' => [
                [
                    'data' => $counts,
                    'backgroundColor' => self::COLORS,
                    'borderWidth' => 2,
                    'borderColor' => '#0d1321',
                ],
            ],
            'labels' => array_values(self::SEVERITIES),
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
