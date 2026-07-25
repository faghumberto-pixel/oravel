<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Filament\Widgets\AssetsByStatusChart;

/**
 * Subclasse fina só pra herdar de AssetsByStatusChart sem acoplar
 * diretamente o widget do Painel de Controle a este contexto (columnSpan
 * de lá, ['md'=>1], já serve bem aqui também -- os 3 gráficos da página
 * ficam lado a lado, ver ListAssets::getHeaderWidgetsColumns()).
 */
class AssetStatusChartWidget extends AssetsByStatusChart
{
    /**
     * Todo Filament\Widgets\Widget é lazy por padrão (Filament\Support\
     * Concerns\CanBeLazy, $isLazy=true) -- getHeaderWidgets() carrega o
     * conteúdo real via uma requisição Livewire separada depois do
     * placeholder inicial. Desligado aqui: o gráfico é leve (1 query),
     * não precisa do adiamento, e lazy deixava impossível confirmar via
     * teste HTTP simples se o gráfico realmente aparece.
     */
    protected static bool $isLazy = false;
}
