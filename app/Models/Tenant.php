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

    /**
     * Override aditivo: o tenant pode ganhar features alem do plano contratado
     * (ex: cliente pagou o plano Basico mas ganhou um modulo do Premium).
     * Nao da pra bloquear via tenant algo que o plano permite -- so o plano
     * define o que e negado. Ver App\Policies\AbstractPolicy::check().
     */
    public function hasFeature(string $feature): bool
    {
        if (Auth::user()?->isSuperAdmin()) {
            return true;
        }

        $localFeatures = $this->features ?? [];

        if (is_array($localFeatures)) {
            if (array_key_exists($feature, $localFeatures)) {
                $value = $localFeatures[$feature];
                if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
                    return true;
                }
            } elseif (in_array($feature, $localFeatures, true)) {
                return true;
            }
        }

        return (bool) $this->plan?->hasFeature($feature);
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
