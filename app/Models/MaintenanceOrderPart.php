<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Peça do catálogo Part aplicada numa OS, baixada do Almoxarifado Volante
 * (Warehouse type=mobile) do técnico -- ponte entre Part/Warehouse e
 * MaintenanceOrder que não existia (o consumo em OS até aqui só cobria o
 * catálogo Material, ver MaintenanceOrderMaterial). unit_price e
 * warehouse_id são preenchidos por
 * StockTransferService::consumePartInWorkOrder(), nunca digitados à mão.
 */
class MaintenanceOrderPart extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'maintenance_order_id',
        'part_id',
        'warehouse_id',
        'quantity',
        'unit_price',
        'stock_movement_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:4',
    ];

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function getTotalCostAttribute(): float
    {
        return (float) $this->quantity * (float) $this->unit_price;
    }
}
