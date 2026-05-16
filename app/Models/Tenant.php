<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    ];

    /**
     * RELAÇÃO COM USUÁRIOS (ESSENCIAL PARA O LOGIN)
     * Resolve o erro 'Call to undefined method users()'
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Relação com Materiais (RESOLVE O ERRO NA LINHA 918 DO FILAMENT)
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    /**
     * Relação com o Plano (Central)
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Relação com os Clientes (Tenancy)
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Relação com Ordens de Serviço
     */
    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }

    /**
     * Outras Relações do Sistema (Ativos e PCM)
     */
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

    /**
     * Relação com Filiais/Branches
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}