<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\CepGeocodingService;

/**
 * Cobre criacao/edicao fora do form do Filament (seeders, tinker, API) --
 * o form do ClientResource ja geocodifica ao vivo via afterStateUpdated
 * no campo zip_code, entao aqui so' completa latitude/longitude quando
 * quem salvou nao fez isso sozinho. Mesmo papel de AssetObserver::saving().
 */
class ClientObserver
{
    public function saving(Client $client): void
    {
        if (! ($client->isDirty('zip_code') || $client->isDirty('address')) || $client->latitude || $client->longitude) {
            return;
        }

        $fullAddress = trim(implode(', ', array_filter([
            $client->address,
            $client->city,
            $client->state,
        ])));

        if (! $fullAddress) {
            return;
        }

        $coords = app(CepGeocodingService::class)->geocodeAddress($fullAddress);

        if ($coords) {
            $client->latitude = $coords['latitude'];
            $client->longitude = $coords['longitude'];
        }
    }
}
