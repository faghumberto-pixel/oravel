<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    // Adicionamos a Trait oficial do seu sistema, que substitui o escopo manual e evita erros duplos
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'sku',
        'name',
        'unit_cost',
        'current_stock',
        'min_stock',
        'max_stock',
        'ncm',
        'category_id', // CORRIGIDO: O banco de dados exige 'category_id'
        'price',
        'tenant_id' // CORRIGIDO: O padrão do seu banco de dados é tenant_id
    ];

    /**
     * Relação corrigida: Apontando para MaterialCategory
     */
    public function category(): BelongsTo
    {
        // O Laravel já deduz automaticamente que a coluna de ligação é category_id
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }
}