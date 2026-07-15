<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pendencia aberta a partir de uma OS -- separada do status "pendencia"
 * do Kanban (workflow_status da propria MaintenanceOrder, outro
 * conceito). Sem Resource proprio (sem HasSaaSMetadata, mesmo caso de
 * MaintenanceDueAlert): criada via RelationManager dentro da OS,
 * consultada/resolvida via App\Filament\Pages\EventosEFalhas.
 */
class MaintenanceOrderPendencia extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const STATUS_ABERTA = 'aberta';

    public const STATUS_RESOLVIDA = 'resolvida';

    protected $fillable = [
        'tenant_id',
        'maintenance_order_id',
        'description',
        'created_by_user_id',
        'status',
        'resolved_at',
        'resolved_by_user_id',
        'resolution_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
