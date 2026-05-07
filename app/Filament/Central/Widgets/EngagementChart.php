<?php

namespace App\Filament\Central\Widgets;

use Filament\Widgets\ChartWidget;

class EngagementChart extends ChartWidget
{
    protected static ?string $heading = 'Engajamento Semanal (Acessos/Ações)';
    
    // Fica na terceira posição visual (Linha 2, Esquerda)
    protected static ?int $sort = 4;
    
    // Trava a altura para manter a simetria com os de cima
    protected static ?string $maxHeight = '275px';

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Interações Ativas',
                    // Dados simulados. No futuro, ligaremos isso a um log de atividades dos usuários.
                    'data' => [120, 180, 210, 160, 250, 60, 40],
                    'backgroundColor' => '#8b5cf6', // Roxo sóbrio para contrastar com o azul/verde
                    'borderRadius' => 4, // Deixa as bordas das barras levemente arredondadas
                ],
            ],
            'labels' => ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}