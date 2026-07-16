<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cabecalho do recebimento fisico de uma PurchaseOrder -- pode ser
 * parcial, por isso varios GoodsReceipt cabem numa mesma Ordem de Compra.
 */
class GoodsReceipt extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_goods_receipts';

    protected static ?string $saasPermissionSlug = 'recebimento_compra';

    protected static ?string $saasModuleLabel = 'Recebimento de Compras';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'internal_unit_id',
        'received_by_user_id',
        'received_at',
        'invoice_number',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
