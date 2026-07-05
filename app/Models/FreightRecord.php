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
        'horas_motorista',
        'custo_motorista',
        'data',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'km_percorrido' => 'decimal:2',
        'horas_motorista' => 'decimal:2',
        'custo_motorista' => 'decimal:2',
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

    /**
     * Custo total do frete: valor do frete + custo do motorista + pedagios
     * ligados a essa viagem. E' o que alimenta o rollup de
     * EquipmentMovement::custo_transporte (ver FreightRecordObserver).
     */
    public function getCustoTotalAttribute(): float
    {
        return (float) $this->valor
            + (float) ($this->custo_motorista ?? 0)
            + (float) $this->tollRecords()->sum('valor');
    }
}
