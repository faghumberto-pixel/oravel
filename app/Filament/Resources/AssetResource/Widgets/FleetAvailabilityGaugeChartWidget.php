<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Filament\Widgets\FleetAvailabilityGaugeWidget;

/**
 * Subclasse fina só pra mudar columnSpan/isLazy sem afetar o uso original
 * de FleetAvailabilityGaugeWidget no Painel de Controle (App\Support\
 * SegmentDashboardWidgets) -- mesmo raciocínio de AssetStatusChartWidget.
 */
class FleetAvailabilityGaugeChartWidget extends FleetAvailabilityGaugeWidget
{
    protected int|string|array $columnSpan = 'full';

    /**
     * Todo Filament\Widgets\Widget é lazy por padrão (Filament\Support\
     * Concerns\CanBeLazy) -- ver mesma nota em AssetStatusChartWidget.
     */
    protected static bool $isLazy = false;
}
