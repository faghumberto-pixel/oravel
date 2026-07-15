<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Models\Asset;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

/**
 * Revive a view orfa resources/views/filament/widgets/asset-map-widget.blade.php
 * (Leaflet + OpenStreetMap, sem pacote pago) -- a classe original foi
 * removida numa limpeza de models mortos (dff1f51), a view ficou sem
 * dono. getAssets() resolve a localizacao em cascata: latitude/longitude
 * proprios do Ativo (AssetResource::form() + AssetObserver::saving(), a
 * partir do campo cep) -> do Cliente vinculado (ClientResource form +
 * ClientObserver, a partir de zip_code/address) -> da Unidade Interna
 * vinculada (InternalUnitResource, ja tinha geocoding pronto). A maioria
 * dos Ativos nao tem CEP proprio, mas ja esta vinculada a um Cliente com
 * endereco cadastrado -- sem essa cascata o mapa fica vazio.
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
            ->with(['client', 'internalUnit'])
            ->where(function ($query) {
                $query->whereNotNull('latitude')
                    ->orWhereHas('client', fn ($q) => $q->whereNotNull('latitude'))
                    ->orWhereHas('internalUnit', fn ($q) => $q->whereNotNull('latitude'));
            })
            ->get(['id', 'name', 'patrimonio', 'latitude', 'longitude', 'client_id', 'internal_unit_id'])
            ->map(function (Asset $asset) {
                $latitude = $asset->latitude ?? $asset->client?->latitude ?? $asset->internalUnit?->latitude;
                $longitude = $asset->longitude ?? $asset->client?->longitude ?? $asset->internalUnit?->longitude;

                return [
                    'name' => $asset->name,
                    'patrimonio' => $asset->patrimonio,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ];
            })
            ->filter(fn (array $asset) => $asset['latitude'] && $asset['longitude'])
            ->values()
            ->toArray();
    }
}
