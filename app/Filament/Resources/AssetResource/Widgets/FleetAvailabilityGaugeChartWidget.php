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
     * Era 190px (herdado de GaugeChart) -- padronizado em 220px junto com
     * os outros 3 gráficos da linha, pra as 4 caixas ficarem do mesmo
     * tamanho (pedido do usuário).
     */
    protected static ?string $maxHeight = '220px';

    /**
     * Todo Filament\Widgets\Widget é lazy por padrão (Filament\Support\
     * Concerns\CanBeLazy) -- ver mesma nota em AssetStatusChartWidget.
     */
    protected static bool $isLazy = false;
}
