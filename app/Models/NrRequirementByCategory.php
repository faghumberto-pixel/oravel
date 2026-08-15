<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * De-para configuravel: qual norma (NR) uma categoria de ativo exige do
 * operador. Fonte real que a trigger de equipment_allocations consulta --
 * ver migration create_equipment_allocations_table.
 */
class NrRequirementByCategory extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'nr_requirements_by_category';

    protected $fillable = [
        'tenant_id',
        'asset_category_id',
        'norma',
    ];

    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }
}
