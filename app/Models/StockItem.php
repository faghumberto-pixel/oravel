<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItem extends Model
{
    // Traits de escopo e identificação
    use \App\Traits\BelongsToTenant;
    use HasUuids, SoftDeletes;

    /**
     * Os atributos que podem ser atribuídos em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 
        'sku', 
        'description', 
        'current_stock', 
        'min_stock', 
        'unit_price', 
        'internal_unit_id', 
        'tenant_id'
    ];

    /**
     * Relacionamento com o Tenant (Vínculo Multi-tenant)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}