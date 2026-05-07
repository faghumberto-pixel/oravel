<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    use HasUuids, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'name', 'sku', 'description', 'current_stock', 
        'min_stock', 'unit_price', 'internal_unit_id', 'tenant_id'
    ];

    /**
     * Relação com a Filial (Unidade Interna)
     */
    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    /**
     * Relação com movimentações (Entradas e Saídas)
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Método de conveniência: Verifica se precisa repor estoque
     */
    public function needsRestock(): bool
    {
        return $this->current_stock <= $this->min_stock;
    }
}