<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetMaintenanceHistory extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $table = 'fleet_maintenance_history';

    protected $fillable = [
        'tenant_id',
        'fleet_vehicle_id',
        'fleet_maintenance_plan_id',
        'tipo_servico',
        'km_na_execucao',
        'data_execucao',
        'custo',
        'fornecedor',
        'notes',
    ];

    protected $casts = [
        'km_na_execucao' => 'decimal:2',
        'data_execucao' => 'date',
        'custo' => 'decimal:2',
    ];

    public function fleetVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class);
    }

    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(FleetMaintenancePlan::class, 'fleet_maintenance_plan_id');
    }
}
