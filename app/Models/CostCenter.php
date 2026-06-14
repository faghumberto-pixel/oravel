<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Traits\BelongsToTenant;

class CostCenter extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_cost_centers";
    protected static ?string $saasPermissionSlug = "centro_custo";
    protected static ?string $saasModuleLabel = "Centro de Custo";

    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'code',
        'tenant_id'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function accountPayables(): HasMany
    {
        return $this->hasMany(AccountPayable::class, 'cost_center_id');
    }
}