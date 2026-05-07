<?php

namespace App\Filament\Central\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class ArrChart extends ChartWidget
{
    protected static ?string $heading = 'ARR (Receita Recorrente Anual)';
    
    // Fica na quarta posição visual (Linha 2, Direita)
    protected static ?int $sort = 5;
    
    // Trava a altura para manter a simetria com o gráfico de engajamento
    protected static ?string $maxHeight = '275px';

    protected function getData(): array
    {
        // O ARR atual é basicamente o MRR ativo multiplicado por 12.
        $mrrTotal = Tenant::where('status', 'active')->sum('mrr_value');
        $arrAtual = $mrrTotal * 12;

        return [
            'datasets' => [
                [
                    'label' => 'ARR Projetado (R$)',
                    // Simulando o crescimento trimestre a trimestre (Q1 a Q4)
                    'data' => [
                        $arrAtual * 0.4, 
                        $arrAtual * 0.6, 
                        $arrAtual * 0.8, 
                        $arrAtual // O trimestre atual chega no ARR total calculado
                    ],
                    'backgroundColor' => '#0ea5e9', // Azul claro (Cyan) corporativo
                    'borderRadius' => 4,
                ],
            ],
            'labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}