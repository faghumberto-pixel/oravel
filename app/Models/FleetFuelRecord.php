<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetFuelRecord extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'fleet_vehicle_id',
        'data',
        'litros',
        'valor',
        'km_atual',
    ];

    protected $casts = [
        'data' => 'date',
        'litros' => 'decimal:2',
        'valor' => 'decimal:2',
        'km_atual' => 'decimal:2',
    ];

    public function fleetVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class);
    }
}
