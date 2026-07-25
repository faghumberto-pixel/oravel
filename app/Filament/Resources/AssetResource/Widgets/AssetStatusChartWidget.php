<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Filament\Widgets\AssetsByStatusChart;

/**
 * Subclasse fina só pra mudar o columnSpan sem afetar o uso original de
 * AssetsByStatusChart no Painel de Controle (App\Support\
 * SegmentDashboardWidgets, columnSpan=['md'=>1], carga real lá -- não dá
 * pra mudar a classe original sem quebrar aquele contexto). Aqui, no
 * cabeçalho da listagem (ListAssets::getHeaderWidgets(), grid real do
 * Filament), o gráfico é o único item da linha, então ocupa a largura
 * inteira.
 */
class AssetStatusChartWidget extends AssetsByStatusChart
{
    protected int|string|array $columnSpan = 'full';

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
