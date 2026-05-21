<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;

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
    ];

    /**
     * MÉTODO DE VERIFICAÇÃO DE ASSINATURA (FEATURE GATE)
     * Ajuste: Adicionado verificação de status ativo (Segurança extra para SaaS)
     */
    public function hasAccess(string $featureSlug): bool
    {
        // Se a conta estiver bloqueada ou cancelada, bloqueia acesso a features
        if ($this->status !== 'active') {
            return false;
        }

        if (!$this->plan) {
            return false;
        }

        return $this->plan->hasFeature($featureSlug);
    }

    /**
     * SCOPE PARA FACILITAR CONSULTAS ATIVAS
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    // --- RELAÇÕES ---

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function assetCategories(): HasMany
    {
        return $this->hasMany(AssetCategory::class);
    }

    public function checklistTemplates(): HasMany
    {
        return $this->hasMany(ChecklistTemplate::class);
    }

    public function criticalityLevels(): HasMany
    {
        return $this->hasMany(CriticalityLevel::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}