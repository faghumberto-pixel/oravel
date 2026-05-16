<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialRequestItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'material_request_id',
        'material_id',
        'quantity',
        'brand',
        'cost_price',
        'quality_rating',
        'quality_notes'
    ];

    /**
     * RELACIONAMENTO: O item pertence a um pedido pai
     */
    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

    /**
     * RELACIONAMENTO: Vincula o item ao cadastro básico de Materiais (SKU, NCM, Estoque)
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
