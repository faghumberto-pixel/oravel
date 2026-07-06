<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aviso/mensagem da operacao SaaS pros tenants, criado no painel Central.
 * NAO usa BelongsToTenant de proposito -- nao e dado de um tenant, e um
 * recurso do sistema (igual Plan/Tenant). target_tenant_id nulo = todos os
 * tenants; preenchido = so aquele tenant.
 */
class Announcement extends Model
{
    use HasUuids;

    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_CRITICAL = 'critical';

    protected $fillable = [
        'title',
        'message',
        'level',
        'target_tenant_id',
        'is_active',
        'starts_at',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function targetTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Ativos agora para um tenant especifico (ou globais, target nulo).
     * $tenantId nulo (super admin sem tenant atuante) so ve os globais.
     */
    public static function activeFor(?string $tenantId)
    {
        return static::query()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('target_tenant_id');
                if ($tenantId) {
                    $q->orWhere('target_tenant_id', $tenantId);
                }
            })
            ->orderByDesc('created_at')
            ->get();
    }
}
