<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetTollRecord extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'fleet_vehicle_id',
        'freight_record_id',
        'valor',
        'data',
        'praca_pedagio',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data' => 'date',
    ];

    public function fleetVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class);
    }

    public function freightRecord(): BelongsTo
    {
        return $this->belongsTo(FreightRecord::class);
    }
}
