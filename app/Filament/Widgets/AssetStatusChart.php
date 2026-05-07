<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Facades\DB;
use App\Models\Asset;
use Filament\Widgets\ChartWidget;

class AssetStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Status da Frota (Disponibilidade)';
    protected int | string | array $columnSpan = 1;
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $tenantId = auth()->user()->tenant_id;
        
        // Lê os status reais diretamente do seu banco de dados
        $statusCounts = Asset::where('tenant_id', $tenantId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        // Se o banco estiver vazio, exibe dados fictícios para o layout não quebrar
        if (empty($statusCounts)) {
            return [
                'datasets' => [
                    [
                        'label' => 'Ativos',
                        'data' => [15, 3, 7], // Números fictícios
                        'backgroundColor' => ['#10b981', '#ef4444', '#f59e0b'],
                    ],
                ],
                'labels' => ['Operação (Exemplo)', 'Manutenção (Ex)', 'Disponível (Ex)'],
            ];
        }

        // Se houver dados reais, monta o gráfico com as palavras exatas do seu banco
        $labels = array_keys($statusCounts);
        $dados = array_values($statusCounts);
        
        // Gera cores dinâmicas caso você tenha muitos status diferentes
        $cores = ['#10b981', '#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6'];

        return [
            'datasets' => [
                [
                    'label' => 'Quantidade',
                    'data' => $dados,
                    'backgroundColor' => array_slice($cores, 0, count($dados)),
                ],
            ],
            'labels' => array_map('ucfirst', $labels), // Coloca a primeira letra maiúscula
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}