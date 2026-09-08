<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma cotacao de fornecedor pra uma MaterialRequest -- sem Resource
 * proprio, gerenciada via RelationManager dentro de
 * MaterialRequestResource (mesmo padrao de MaterialRequestItem).
 * total_value e' recalculado a partir de items() (ver
 * MaterialRequestQuotationItem + seu Observer) desde que a cotacao passou
 * a ser itemizada -- nao confia mais em valor digitado direto no form.
 */
class MaterialRequestQuotation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'material_request_id',
        'supplier_id',
        'total_value',
        'delivery_days',
        'payment_terms',
        'is_selected',
        'notes',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'delivery_days' => 'integer',
        'is_selected' => 'boolean',
    ];

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequestQuotationItem::class, 'material_request_quotation_id');
    }

    /**
     * Chamada por MaterialRequestQuotationItemObserver toda vez que um
     * item e' criado/editado/removido -- mesmo padrao de
     * Quote::recalculateTotal().
     */
    public function recalculateTotal(): void
    {
        $this->update(['total_value' => $this->items()->sum('subtotal')]);
    }
}
