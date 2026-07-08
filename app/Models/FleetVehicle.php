<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FleetVehicle extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_fleet_vehicles';

    protected static ?string $saasPermissionSlug = 'veiculo_frota';

    protected static ?string $saasModuleLabel = 'Veículos da Frota';

    /**
     * Vocabulario real de status -- usado no form/tabela do FleetVehicleResource.
     */
    public const STATUS_DISPONIVEL = 'disponivel';

    public const STATUS_EM_ROTA = 'em_rota';

    public const STATUS_MANUTENCAO = 'manutencao';

    public const STATUS_INATIVO = 'inativo';

    protected $fillable = [
        'tenant_id',
        'placa',
        'modelo',
        'tipo',
        'capacidade_carga',
        'status',
        'km_atual',
        'tag_sem_parar',
    ];

    protected $casts = [
        'capacidade_carga' => 'decimal:2',
        'km_atual' => 'decimal:2',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(FleetVehicleDocument::class);
    }

    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(FleetMaintenancePlan::class);
    }

    public function maintenanceHistory(): HasMany
    {
        return $this->hasMany(FleetMaintenanceHistory::class);
    }

    public function freightRecords(): HasMany
    {
        return $this->hasMany(FreightRecord::class);
    }

    public function tollRecords(): HasMany
    {
        return $this->hasMany(FleetTollRecord::class);
    }

    public function fuelRecords(): HasMany
    {
        return $this->hasMany(FleetFuelRecord::class);
    }

    public function drivers(): BelongsToMany
    {
        return $this->belongsToMany(FleetDriver::class, 'fleet_driver_vehicle');
    }

    public function equipmentMovements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class);
    }

    /**
     * Fonte de verdade pra "disponivel agora" e' o proprio campo status,
     * mantido automaticamente por EquipmentMovementObserver (disponivel <->
     * em_rota) conforme movimentacoes atribuidas comecam/terminam.
     * Checagem de conflito por JANELA de data/horario fica pra quando
     * equipment_movements tiver um campo formal de "periodo necessario"
     * (ver diagnostico do modulo de Logistica) -- nao inventado aqui.
     */
    public function isCurrentlyAvailable(): bool
    {
        return $this->status === self::STATUS_DISPONIVEL;
    }
}
