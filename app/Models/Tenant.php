<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\Auth;

class Tenant extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'mrr_value',
        'plan_id',
        'onboarding_completed',
        'features',
        'asaas_customer_id',
        'asaas_subscription_id',
        'asaas_status',
        'asaas_synced_at',
    ];

    protected $casts = [
        'id' => 'string',
        'plan_id' => 'string',
        'features' => 'array',
        'onboarding_completed' => 'boolean',
        'mrr_value' => 'decimal:2',
    ];

    public function hasFeature(string $feature): bool
    {
        if (Auth::user()?->isSuperAdmin()) {
            return true;
        }

        $localFeatures = $this->features ?? [];
        
        if (is_array($localFeatures)) {
            if (array_key_exists($feature, $localFeatures)) {
                if ($localFeatures[$feature] === true || $localFeatures[$feature] === 1) {
                    return true;
                }
            } elseif (in_array($feature, $localFeatures, true)) {
                return true;
            }
        }

        $plan = $this->plan()->first();
        if ($plan) {
            $planFeatures = $plan->features;

            if (is_string($planFeatures)) {
                $planFeatures = json_decode($planFeatures, true) ?? [];
            }

            if (is_array($planFeatures)) {
                if (array_key_exists($feature, $planFeatures)) {
                    return $planFeatures[$feature] === true || $planFeatures[$feature] === 1;
                } elseif (in_array($feature, $planFeatures, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasModuleAccess(mixed $moduleId, string $moduleSlug): bool
    {
        return $this->hasFeature($moduleSlug);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user', 'tenant_id', 'user_id');
    }

    public function adminUser(): HasOne
    {
        return $this->hasOne(User::class, 'tenant_id', 'id')->where('role', 'admin');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
