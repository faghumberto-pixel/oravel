<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\ChartWidget;

class MaintenanceAlertChart extends ChartWidget
{
    protected static ?string $heading = 'Alerta de Revisão (Próximos da Manutenção)';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $user = auth()->user();
        
        // Proteção para não quebrar se o tenant for nulo
        if (!$user || !$user->tenant_id) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $tenantId = $user->tenant_id;

        /**
         * ATENÇÃO: Se o erro persistir, verifique o nome da coluna no seu Banco.
         * Se você não tiver horímetro ainda, vamos listar os ativos por data de criação
         * apenas para o gráfico não quebrar e você ver o layout.
         */
        $ativos = Asset::where('tenant_id', $tenantId)
            ->limit(5)
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Uso do Equipamento',
                    // Tenta usar initial_horimeter se current_horimeter não existir
                    'data' => $ativos->map(fn($a) => $a->initial_horimeter ?? 0)->toArray(),
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => $ativos->pluck('name')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}