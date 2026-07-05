<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreightRecord extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_freight_records';

    protected static ?string $saasPermissionSlug = 'frete';

    protected static ?string $saasModuleLabel = 'Fretes';

    public const TIPO_PROPRIO = 'proprio';

    public const TIPO_TERCEIRIZADO = 'terceirizado';

    protected $fillable = [
        'tenant_id',
        'equipment_movement_id',
        'tipo',
        'fleet_vehicle_id',
        'freight_carrier_id',
        'valor',
        'origem',
        'destino',
        'km_percorrido',
        'data',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'km_percorrido' => 'decimal:2',
        'data' => 'date',
    ];

    public function equipmentMovement(): BelongsTo
    {
        return $this->belongsTo(EquipmentMovement::class);
    }

    public function fleetVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class);
    }

    public function freightCarrier(): BelongsTo
    {
        return $this->belongsTo(FreightCarrier::class);
    }

    public function tollRecords(): HasMany
    {
        return $this->hasMany(FleetTollRecord::class);
    }
}
