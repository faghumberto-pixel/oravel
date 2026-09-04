<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'part_id',
        'current_quantity',
        'reserved_quantity',
    ];

    protected $casts = [
        'current_quantity' => 'decimal:2',
        'reserved_quantity' => 'decimal:2',
    ];

    // ========== RELACIONAMENTOS ==========

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    // ========== ACESSORES ==========

    public function getAvailableQuantityAttribute(): float
    {
        return max(0, (float)$this->current_quantity - (float)$this->reserved_quantity);
    }

    public function getStockPercentageAttribute(): float
    {
        if (!$this->part || !$this->part->maximum_stock) {
            return 0;
        }

        return min(100, ($this->current_quantity / $this->part->maximum_stock) * 100);
    }

    public function getIsCriticalAttribute(): bool
    {
        return $this->current_quantity < $this->part->minimum_stock;
    }
}
