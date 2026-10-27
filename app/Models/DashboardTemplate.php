<?php

namespace App\Models;

use App\Models\Scopes\DashboardTemplateScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de dashboard personalizado: da plataforma (tenant_id nulo) ou exclusivo de uma empresa.
 * Não usa BelongsToTenant de propósito (ver DashboardTemplateScope e a isenção em TenantIsolationAudit).
 */
class DashboardTemplate extends Model
{
    use HasUuids;

    public const PROFILES = [
        'direcao' => 'Direção',
        'tecnico' => 'Técnico',
        'estoque' => 'Estoque',
        'comercial' => 'Comercial',
        'outro' => 'Outro',
    ];

    protected $fillable = ['tenant_id', 'source_template_id', 'name', 'profile', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope(new DashboardTemplateScope);
    }

    public function isPlatform(): bool
    {
        return $this->tenant_id === null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_template_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(DashboardTemplateBlock::class)->orderBy('position');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DashboardAssignment::class);
    }
}
