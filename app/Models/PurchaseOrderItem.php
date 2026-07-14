<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de uma PurchaseOrder -- sem Resource proprio, gerenciado via
 * RelationManager dentro de PurchaseOrderResource.
 */
class PurchaseOrderItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'material_id',
        'quantity',
        'unit_price',
        'quantity_received',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'quantity_received' => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function getPendingQuantityAttribute(): float
    {
        return (float) $this->quantity - (float) $this->quantity_received;
    }
}
