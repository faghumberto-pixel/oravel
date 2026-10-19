<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportedProblem extends Model
{
    use \App\Traits\BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_reported_problems';

    protected static ?string $saasPermissionSlug = 'problema_comum';

    protected static ?string $saasModuleLabel = 'Problemas Comuns';

    protected $fillable = ['description', 'tenant_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }
}