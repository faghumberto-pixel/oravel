<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * "Realizado" = OS preventivas concluídas (finished_at) no mês.
 * "Planejado" = OS preventivas com scheduled_at previsto pro mês (inclui as
 * ainda não concluídas) -- compara o que foi agendado com o que de fato saiu.
 */
class PmpEvolutionChart extends ChartWidget
{
    protected static ?string $heading = 'Evolução Mensal de O.S. (Realizado vs. Planejado)';

    protected static ?string $maxHeight = '260px';

    protected int|string|array $columnSpan = ['md' => 2];

    /**
     * Abreviações fixas em pt-BR -- Carbon::translatedFormat() depende do
     * locale global do Carbon, que não é sincronizado com app.locale neste
     * projeto (mesma classe de bug já documentada com o Faker), então
     * devolvia meses em inglês ("Feb","Mar"). Mapa fixo evita depender
     * desse estado.
     */
    private const MESES_ABREV = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    protected function getData(): array
    {
        $tenantId = Tenancy::current()?->id;

        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $labels = $months->map(fn (Carbon $m) => self::MESES_ABREV[$m->month])->all();

        $realizado = $months->map(function (Carbon $month) use ($tenantId) {
            return MaintenanceOrder::where('tenant_id', $tenantId)
                ->where('maintenance_type', MaintenanceOrder::TYPE_PREVENTIVE)
                ->where('internal_status', 'concluido')
                ->whereBetween('finished_at', [$month, $month->copy()->endOfMonth()])
                ->count();
        })->all();

        $planejado = $months->map(function (Carbon $month) use ($tenantId) {
            return MaintenanceOrder::where('tenant_id', $tenantId)
                ->where('maintenance_type', MaintenanceOrder::TYPE_PREVENTIVE)
                ->where('status', '!=', 'Cancelada')
                ->whereBetween('scheduled_at', [$month, $month->copy()->endOfMonth()])
                ->count();
        })->all();

        return [
            'datasets' => [
                [
                    'label' => 'Realizado',
                    'data' => $realizado,
                    'borderColor' => '#3987e5',
                    'backgroundColor' => 'rgba(57,135,229,.15)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => 'Planejado',
                    'data' => $planejado,
                    'borderColor' => '#c98500',
                    'backgroundColor' => 'rgba(201,133,0,.12)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'labels' => ['color' => '#94a3b8']],
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
