<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma cotacao de fornecedor pra uma MaterialRequest -- sem Resource
 * proprio, gerenciada via RelationManager dentro de
 * MaterialRequestResource (mesmo padrao de MaterialRequestItem).
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
}
