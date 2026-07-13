<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sem BelongsToTenant de proposito: tabela nao tem tenant_id, o
 * isolamento acontece por tabela pai (maintenance_order_id ->
 * MaintenanceOrder, que ja usa BelongsToTenant) -- ver
 * MaintenanceStatusHistoryResource::getEloquentQuery(), que usa
 * whereHas('maintenanceOrder') e ganha o escopo de graca.
 */
class MaintenanceStatusHistory extends Model
{
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_maintenance_status_histories';

    protected static ?string $saasPermissionSlug = 'auditoria_status_os';

    protected static ?string $saasModuleLabel = 'Auditoria de Status de OS';

    protected $table = 'maintenance_status_histories';

    public $timestamps = false;

    protected $fillable = [
        'maintenance_order_id',
        'old_status',
        'new_status',
        'observation',
        'user_id',
        'old_technician_id',
        'new_technician_id',
        'segment_seconds',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'segment_seconds' => 'integer',
    ];

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function oldTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'old_technician_id');
    }

    public function newTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_technician_id');
    }
}
