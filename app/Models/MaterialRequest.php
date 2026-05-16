<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class MaterialRequest extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'maintenance_order_id',
        'provider_name',
        'status',
        'requested_at',
        'delivered_at',
        'notes'
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    /**
     * MÉTRICA ISO 9001: Calcula o Lead Time (Tempo de Espera) em dias até a chegada do material
     */
    public function getLeadTimeInDaysAttribute(): ?int
    {
        if (!$this->delivered_at || !$this->requested_at) {
            return null;
        }

        return $this->requested_at->diffInDays($this->delivered_at);
    }

    /**
     * RELACIONAMENTO: O pedido pertence a um Tenant (Isolamento de dados)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * RELACIONAMENTO: Quem solicitou o pedido no pátio
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * RELACIONAMENTO: Se o pedido está vinculado a uma Ordem de Serviço (O.S.)
     */
    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    /**
     * RELACIONAMENTO: Um pedido possui vários itens cadastrados
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequestItem::class, 'material_request_id');
    }
}
