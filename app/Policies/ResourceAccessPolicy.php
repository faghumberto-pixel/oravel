<?php

namespace App\Policies;

use App\Services\FeatureDiscoveryService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ResourceAccessPolicy
{
    public static function canAccessResource(string $resourceClass): bool
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
        $featureSlug = $featureMapping[$resourceClass] ?? null;

        if (!$featureSlug) {
            return true;
        }

        return $tenant->hasFeature($featureSlug);
    }
}
