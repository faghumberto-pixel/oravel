<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\Quote;
use Illuminate\Http\Request;

/**
 * Resolve tenant_id de uma visita anonima em rota publica por token --
 * so' chamado na CRIACAO de uma sessao nova (TrackSiteVisit), nunca em
 * hits seguintes. Isolado do middleware pra nao acoplar TrackSiteVisit ao
 * conhecimento de todas as rotas publicas do app; novo caso de rota
 * publica = so' adicionar um branch aqui.
 */
class SiteVisitTenantResolver
{
    public static function resolve(Request $request): ?string
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return null;
        }

        return match (true) {
            str_starts_with($routeName, 'quotes.public-') => Quote::where(
                'approval_token',
                $request->route('token')
            )->value('tenant_id'),

            $routeName === 'portaria.verificar' => EquipmentMovement::where(
                'qr_token',
                $request->route('token')
            )->value('tenant_id'),

            $routeName === 'patio.ativo-status' => self::resolveAssetTenantId($request->route('asset')),

            str_starts_with($routeName, 'hour-meter.public.') => Asset::where(
                'hour_meter_public_token',
                $request->route('token')
            )->value('tenant_id'),

            default => null,
        };
    }

    private static function resolveAssetTenantId(Asset|string|null $asset): ?string
    {
        if ($asset instanceof Asset) {
            return $asset->tenant_id;
        }

        return $asset ? Asset::where('id', $asset)->value('tenant_id') : null;
    }
}
