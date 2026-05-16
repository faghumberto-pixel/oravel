<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PartsRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'maintenance_order_id',
        'user_id',
        'part_description',
        'quantity',
        'status',
    ];

    /**
     * Relacionamento com a empresa (Multi-tenancy)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relacionamento com a Ordem de Serviço
     */
    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    /**
     * Relacionamento com o Usuário (Técnico que solicitou)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}