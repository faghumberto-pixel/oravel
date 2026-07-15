<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Models\Asset;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

/**
 * Revive a view orfa resources/views/filament/widgets/asset-map-widget.blade.php
 * (Leaflet + OpenStreetMap, sem pacote pago) -- a classe original foi
 * removida numa limpeza de models mortos (dff1f51), a view ficou sem
 * dono. getAssets() so' retorna equipamentos com CEP ja geocodificado
 * (AssetResource::form() + AssetObserver::saving() alimentam latitude/
 * longitude a partir do campo cep).
 */
class AssetMapWidget extends Widget
{
    protected static string $view = 'filament.widgets.asset-map-widget';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    public function getAssets(): array
    {
        return Asset::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['name', 'patrimonio', 'latitude', 'longitude'])
            ->toArray();
    }
}
