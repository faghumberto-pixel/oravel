<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alocacao de tecnico com periodo, usada pelo Gantt de Alocacao de
 * Tecnicos (PMP). Ver migration para o porque de nao reaproveitar
 * Appointment/MaintenanceOrder.scheduled_at (sem duracao).
 */
class TechnicianAllocation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const STATUS_PLANEJADO = 'planejado';

    public const STATUS_CONFIRMADO = 'confirmado';

    public const STATUS_CONCLUIDO = 'concluido';

    public const STATUS_CANCELADO = 'cancelado';

    protected $fillable = [
        'tenant_id',
        'technician_id',
        'maintenance_order_id',
        'maintenance_due_alert_id',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }

    public function maintenanceDueAlert(): BelongsTo
    {
        return $this->belongsTo(MaintenanceDueAlert::class);
    }
}
