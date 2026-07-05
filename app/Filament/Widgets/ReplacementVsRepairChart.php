<?php

namespace App\Filament\Widgets;

use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement;
use Filament\Widgets\ChartWidget;

class ReplacementVsRepairChart extends ChartWidget
{
    protected static ?string $heading = 'Desmobilizações com Troca vs Reparo';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['md' => 1];

    protected function getData(): array
    {
        $base = EquipmentDamage::whereHas(
            'equipmentMovement',
            fn ($q) => $q->where('type', EquipmentMovement::TYPE_DESMOBILIZACAO)
        );

        $troca = (clone $base)->where('requires_replacement', true)->count();
        $reparo = (clone $base)->where('requires_replacement', false)->count();

        return [
            'datasets' => [
                [
                    'data' => [$troca, $reparo],
                    'backgroundColor' => ['#3987e5', '#199e70'],
                    'borderWidth' => 2,
                    'borderColor' => '#0d1321',
                ],
            ],
            'labels' => ['Troca de Equipamento', 'Apenas Reparo'],
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
