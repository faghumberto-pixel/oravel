<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;

class AbcMatrix extends Model
{
    use HasUuids, BelongsToTenant, HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_abc_matrix";
    protected static ?string $saasPermissionSlug = "matriz_abc";
    protected static ?string $saasModuleLabel = "Matriz ABC";

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'classification',
        'value_percentage',
        'notes',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
