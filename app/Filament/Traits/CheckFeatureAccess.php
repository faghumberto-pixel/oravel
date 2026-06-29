<?php

namespace App\Filament\Traits;

use App\Services\FeatureDiscoveryService;
use Illuminate\Support\Facades\Auth;

trait CheckFeatureAccess
{
    public static function canAccess(): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = $user->tenant;
        if (!$tenant) {
            return false;
        }

        $featureMapping = FeatureDiscoveryService::getAllFeatureMapping();
        $resourceClass = static::class;
        $featureSlug = $featureMapping[$resourceClass] ?? null;

        if (!$featureSlug) {
            return true;
        }

        return $tenant->hasFeature($featureSlug);
    }
}
