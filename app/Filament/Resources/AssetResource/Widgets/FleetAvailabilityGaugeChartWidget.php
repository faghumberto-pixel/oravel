<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Filament\Widgets\FleetAvailabilityGaugeWidget;

/**
 * Subclasse fina só pra mudar isLazy sem afetar o uso original de
 * FleetAvailabilityGaugeWidget no Painel de Controle (App\Support\
 * SegmentDashboardWidgets) -- mesmo raciocínio de AssetStatusChartWidget.
 * columnSpan padrão (1) já serve: os 3 gráficos da página ficam lado a
 * lado, ver ListAssets::getHeaderWidgetsColumns().
 */
class FleetAvailabilityGaugeChartWidget extends FleetAvailabilityGaugeWidget
{
    /**
     * Todo Filament\Widgets\Widget é lazy por padrão (Filament\Support\
     * Concerns\CanBeLazy) -- ver mesma nota em AssetStatusChartWidget.
     */
    protected static bool $isLazy = false;
}
