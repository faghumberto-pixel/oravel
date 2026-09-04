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
            str_starts_with($routeName, 'quotes.public-') => self::querySafely(
                fn() => Quote::where('approval_token', $request->route('token'))->value('tenant_id')
            ),

            $routeName === 'portaria.verificar' => self::querySafely(
                fn() => EquipmentMovement::where('qr_token', $request->route('token'))->value('tenant_id')
            ),

            $routeName === 'patio.ativo-status' => self::resolveAssetTenantId($request->route('asset')),

            str_starts_with($routeName, 'hour-meter.public.') => self::querySafely(
                fn() => Asset::where('hour_meter_public_token', $request->route('token'))->value('tenant_id')
            ),

            default => null,
        };
    }

    private static function resolveAssetTenantId(Asset|string|null $asset): ?string
    {
        if ($asset instanceof Asset) {
            return $asset->tenant_id;
        }

        if (! $asset || ! self::isValidUuid($asset)) {
            return null;
        }

        return Asset::where('id', $asset)->value('tenant_id');
    }

    private static function isValidUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    private static function querySafely(callable $query): ?string
    {
        try {
            return $query();
        } catch (\Exception) {
            return null;
        }
    }
}
