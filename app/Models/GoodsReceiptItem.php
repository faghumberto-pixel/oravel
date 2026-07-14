<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Uma linha de recebimento -- toda a logica de efeito colateral (baixa
 * de estoque, ledger, status da Ordem de Compra) vive em
 * App\Observers\GoodsReceiptItemObserver, nao aqui.
 */
class GoodsReceiptItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'goods_receipt_id',
        'purchase_order_item_id',
        'quantity_received',
        'serial_number',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:2',
    ];

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function material(): HasOneThrough
    {
        return $this->hasOneThrough(
            Material::class,
            PurchaseOrderItem::class,
            'id',
            'id',
            'purchase_order_item_id',
            'material_id'
        );
    }
}
