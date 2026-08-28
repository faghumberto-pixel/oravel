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

    // Nem todo tecnico usa o app -- 'impressa' pula o passo de aceite
    // digital (o ato de imprimir ja conta como entregue). Ver
    // AlocacaoTecnicosPmp::printAllocation() e
    // TechnicianDailyTasks::getPendingAllocationsProperty().
    public const DELIVERY_DIGITAL = 'digital';

    public const DELIVERY_IMPRESSA = 'impressa';

    protected $fillable = [
        'tenant_id',
        'technician_id',
        'maintenance_order_id',
        'maintenance_due_alert_id',
        'starts_at',
        'ends_at',
        'status',
        'delivery_mode',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    // Espelha o default da coluna (migration) aqui tambem -- sem isso,
    // ->delivery_mode fica null EM MEMORIA logo apos create() quando o
    // campo nao e' passado explicitamente (Eloquent nao reconsulta o
    // banco pra pegar o default aplicado pelo Postgres).
    protected $attributes = [
        'status' => self::STATUS_PLANEJADO,
        'delivery_mode' => self::DELIVERY_DIGITAL,
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

    /**
     * Pedido do usuário 2026-08-28: card do Gantt precisa mostrar o
     * Patrimônio (PAT) e o tipo de manutenção, não só o nome genérico
     * "Alocação" -- funciona tanto pra alocação já vinculada a uma OS
     * quanto pra uma ainda só vinculada a um MaintenanceDueAlert (item
     * preventivo "A Fazer", sem OS criada ainda).
     */
    public function displayLabel(): string
    {
        if ($this->maintenanceOrder) {
            $pat = $this->maintenanceOrder->asset?->patrimonio ?? '—';
            $tipo = $this->maintenanceOrder->maintenance_type === MaintenanceOrder::TYPE_PREVENTIVE
                ? ($this->maintenanceOrder->maintenancePlan?->name ?? 'Preventiva')
                : (MaintenanceOrder::failureCategoryLabels()[$this->maintenanceOrder->failure_category] ?? 'Corretiva');

            return "{$pat} · {$tipo}";
        }

        if ($this->maintenanceDueAlert) {
            $pat = $this->maintenanceDueAlert->asset?->patrimonio ?? '—';
            $tipo = $this->maintenanceDueAlert->maintenancePlan?->name ?? 'Preventiva';

            return "{$pat} · {$tipo}";
        }

        return 'Alocação';
    }
}
