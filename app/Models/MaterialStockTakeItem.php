<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma linha de contagem dentro de um MaterialStockTake: quanto o sistema
 * dizia que tinha (expected_quantity, congelado no momento em que a
 * contagem comecou) vs. quanto foi contado fisicamente
 * (counted_quantity, nulo ate alguem preencher).
 */
class MaterialStockTakeItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'material_stock_take_id',
        'material_id',
        'expected_quantity',
        'counted_quantity',
        'notes',
    ];

    protected $casts = [
        'expected_quantity' => 'decimal:2',
        'counted_quantity' => 'decimal:2',
    ];

    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(MaterialStockTake::class, 'material_stock_take_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function getDifferenceAttribute(): ?float
    {
        if ($this->counted_quantity === null) {
            return null;
        }

        return (float) $this->counted_quantity - (float) $this->expected_quantity;
    }
}
