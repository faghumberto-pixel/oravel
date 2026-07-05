<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartsRequest extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_parts_requests';

    protected static ?string $saasPermissionSlug = 'solicitacao_pecas';

    protected static ?string $saasModuleLabel = 'Solicitacao de Pecas';

    protected $fillable = [
        'tenant_id',
        'maintenance_order_id',
        'material_id',
        'quantity',
        'status',
        'cost_at_time',
    ];

    protected $casts = [
        'cost_at_time' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
