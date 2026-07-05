<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_materials';

    protected static ?string $saasPermissionSlug = 'material';

    protected static ?string $saasModuleLabel = 'Materiais';

    protected $fillable = [
        'tenant_id',
        'sku',
        'name',
        'unit_cost',
        'current_stock',
        'min_stock',
        'max_stock',
        'ncm',
        'price',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
