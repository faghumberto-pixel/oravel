<?php

namespace App\Services;

use App\Models\User;
use App\Models\Plan;
use Filament\Facades\Filament;

class GovernanceService
{
    protected array $resolvedGates = [];

    /**
     * Valida a dupla checagem: Funcionalidade do Plano + Permissão do Usuário.
     */
    public function canUserExecute(User $user, string $permission, string $featureSlug): bool
    {
        $cacheKey = "{$user->id}:{$permission}:{$featureSlug}";

        if (isset($this->resolvedGates[$cacheKey])) {
            return $this->resolvedGates[$cacheKey];
        }

        $tenant = Filament::getTenant();
        
        if (!$tenant || !$tenant->relationLoaded('plan')) {
            $tenant?->load(['plan' => fn($q) => $q->withoutGlobalScopes()]);
        }

        /** @var Plan|null $plan */
        $plan = $tenant?->plan;

        if (!$plan || !$plan->hasFeature($featureSlug)) {
            return $this->resolvedGates[$cacheKey] = false;
        }

        if (!$user->hasPermissionTo($permission)) {
            return $this->resolvedGates[$cacheKey] = false;
        }

        return $this->resolvedGates[$cacheKey] = true;
    }
}
