<?php

namespace App\Policies;

use App\Services\FeatureDiscoveryService;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ResourcePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, string $resourceClass): bool
    {
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

    public function create(User $user, string $resourceClass): bool
    {
        return $this->viewAny($user, $resourceClass);
    }

    public function view(User $user, string $resourceClass): bool
    {
        return $this->viewAny($user, $resourceClass);
    }

    public function update(User $user, string $resourceClass): bool
    {
        return $this->viewAny($user, $resourceClass);
    }

    public function delete(User $user, string $resourceClass): bool
    {
        return $this->viewAny($user, $resourceClass);
    }
}
