<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Linha de preco de uma MaterialRequestQuotation para um material
 * especifico -- permite comparar preco por item entre fornecedores.
 * total_value da cotacao e' a soma destes itens (ver
 * App\Observers\MaterialRequestQuotationItemObserver), mesmo padrao ja
 * usado em Quote/QuoteItem. Sem Resource proprio, gerenciada via
 * RelationManager aninhado em MaterialRequestResource.
 */
class MaterialRequestQuotationItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'material_request_quotation_id',
        'material_request_item_id',
        'material_id',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (MaterialRequestQuotationItem $item) {
            $item->subtotal = round($item->quantity * $item->unit_price, 2);
        });
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(MaterialRequestQuotation::class, 'material_request_quotation_id');
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(MaterialRequestItem::class, 'material_request_item_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
