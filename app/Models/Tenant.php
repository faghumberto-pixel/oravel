<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

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
    ];

    protected $casts = [
        'id' => 'string',
        'plan_id' => 'string',
        'features' => 'array',
        'onboarding_completed' => 'boolean',
        'mrr_value' => 'decimal:2',
    ];

    public function hasModuleAccess(mixed $moduleId, string $moduleSlug): bool
    {
        return $this->hasFeature($moduleSlug);
    }

    public function hasFeature(string $feature): bool
    {
        $plan = $this->plan()->first();
        if (!$plan) {
            return false;
        }

        $planFeatures = $plan->features;

        if (is_string($planFeatures)) {
            $planFeatures = json_decode($planFeatures, true) ?? [];
        }

        if (!is_array($planFeatures)) {
            return false;
        }

        if (array_key_exists($feature, $planFeatures)) {
            return $planFeatures[$feature] === true || $planFeatures[$feature] === 1 || $planFeatures[$feature] === 'true' || $planFeatures[$feature] === '1';
        }

        return in_array($feature, $planFeatures, true);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id', 'id');
    }

    /**
     * Usuários vinculados a este tenant (pivot tenant_user).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user', 'tenant_id', 'user_id');
    }

    /**
     * O administrador da empresa (coluna-string role = 'admin').
     */
    public function adminUser(): HasOne
    {
        return $this->hasOne(User::class, 'tenant_id', 'id')->where('role', 'admin');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function assetCategories(): HasMany
    {
        return $this->hasMany(AssetCategory::class, 'tenant_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function materialCategories(): HasMany
    {
        return $this->hasMany(MaterialCategory::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function billCategories(): HasMany
    {
        return $this->hasMany(BillCategory::class, 'tenant_id');
    }

    public function accountPayables(): HasMany
    {
        return $this->hasMany(AccountPayable::class, 'tenant_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'tenant_id');
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class, 'tenant_id');
    }

    public function solicitacaoLocacaos(): HasMany
    {
        return $this->hasMany(SolicitacaoLocacao::class, 'tenant_id');
    }
}
