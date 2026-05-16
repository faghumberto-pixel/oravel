<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'sku',
        'name',
        'unit_cost',
        'current_stock',
        'min_stock',
        'max_stock',
        'ncm',
        'category_id', 
        'price',
        'tenant_id' 
    ];

    /**
     * Relação exigida pelo Filament para o isolamento de dados (Multi-tenancy).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Relação com a categoria de materiais.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }
}