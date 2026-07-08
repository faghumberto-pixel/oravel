<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetDriver extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_fleet_drivers';

    protected static ?string $saasPermissionSlug = 'motorista';

    protected static ?string $saasModuleLabel = 'Motoristas';

    public const EMPLOYMENT_PROPRIO = 'proprio';

    public const EMPLOYMENT_TERCEIRO = 'terceiro';

    protected $fillable = [
        'tenant_id',
        'name',
        'cpf',
        'phone',
        'employment_type',
        'freight_carrier_id',
        'cnh_number',
        'cnh_category',
        'cnh_expiry_date',
        'active',
    ];

    protected $casts = [
        'cnh_expiry_date' => 'date',
        'active' => 'boolean',
    ];

    public function freightCarrier(): BelongsTo
    {
        return $this->belongsTo(FreightCarrier::class);
    }

    public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(FleetVehicle::class, 'fleet_driver_vehicle');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FleetDriverDocument::class);
    }

    public function equipmentMovements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class);
    }

    public function isCnhVencida(): bool
    {
        return $this->cnh_expiry_date && $this->cnh_expiry_date->isPast();
    }

    public function isCnhProximoVencimento(int $dias = 30): bool
    {
        return $this->cnh_expiry_date
            && ! $this->isCnhVencida()
            && $this->cnh_expiry_date->lessThanOrEqualTo(now()->addDays($dias));
    }
}
