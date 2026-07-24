<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Charts\GaugeChart;
use App\Models\Asset;

/**
 * Ponte entre o GaugeChart genérico (App\Filament\Widgets\Charts\GaugeChart,
 * parametrizado via mount() -- sem query interna, por design) e
 * App\Support\SegmentDashboardWidgets, que só registra widgets por
 * class-string (@livewire($widget, [], $widget), sem props). Esta classe é
 * quem faz a query real e repassa pro genérico via parent::mount().
 *
 * Sem filtro manual de tenant_id -- Asset já tem o global scope
 * BelongsToTenant, e filtrar de novo aqui por Tenancy::current() quebraria
 * a visão de super admin (mesma ressalva já documentada em
 * RadarUrgencia::getStats()).
 *
 * A assinatura do mount() abaixo repete a de GaugeChart::mount() de
 * propósito (parâmetros nunca usados) -- PHP não deixa uma subclasse
 * declarar mount() com MENOS parâmetros que o pai, mesma trava de LSP que
 * já bloqueia adicionar parâmetro obrigatório a mais (ver
 * App\Filament\Widgets\Charts\GaugeChart::mount()). Os valores recebidos
 * são ignorados -- esta classe sempre calcula os dela.
 */
class FleetAvailabilityGaugeWidget extends GaugeChart
{
    public function mount(
        float $value = 0,
        ?float $target = null,
        ?string $chartTitle = null,
        ?array $bands = null,
        ?string $needleColor = null,
    ): void {
        $total = Asset::count();
        $disponiveis = Asset::where('status', Asset::STATUS_DISPONIVEL)->count();

        parent::mount(
            value: $total > 0 ? round(($disponiveis / $total) * 100, 1) : 0.0,
            target: 70,
            chartTitle: 'Taxa de Disponibilidade da Frota',
        );
    }
}
