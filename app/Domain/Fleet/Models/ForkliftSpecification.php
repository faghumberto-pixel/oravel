<?php

namespace App\Domain\Fleet\Models;

use App\Models\Asset;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForkliftSpecification extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;

    protected $table = 'asset_forklift_specifications';

    public const ENERGY_ELETRICA = 'eletrica';

    public const ENERGY_GLP = 'glp';

    public const ENERGY_DIESEL = 'diesel';

    public const ENERGY_GASOLINA = 'gasolina';

    public const MAST_DUPLA = 'dupla';

    public const MAST_TRIPLA = 'tripla';

    public const MAST_DUPLA_DUPLEX = 'dupla_duplex';

    public const MAST_RETRATIL = 'retratil';

    public const TIRE_SUPER_ELASTICO = 'super_elastico';

    public const TIRE_PNEUMATICO = 'pneumatico';

    public const TIRE_CUSHION = 'cushion';

    public const TIRE_NON_MARKING = 'non_marking';

    public const TIRE_POLIURETANO = 'poliuretano';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'load_capacity_kg',
        'lift_height_m',
        'energy_type',
        'mast_type',
        'tire_type',
        'battery_cycles',
        'battery_voltage',
        'battery_amperage_ah',
        'battery_serial_number',
        'charger_model',
    ];

    protected $casts = [
        'load_capacity_kg' => 'decimal:2',
        'lift_height_m' => 'decimal:2',
        'battery_cycles' => 'integer',
        'battery_amperage_ah' => 'decimal:2',
    ];

    /**
     * @return array<string, string>
     */
    public static function energyTypeLabels(): array
    {
        return [
            self::ENERGY_ELETRICA => 'Elétrica',
            self::ENERGY_GLP => 'GLP',
            self::ENERGY_DIESEL => 'Diesel',
            self::ENERGY_GASOLINA => 'Gasolina',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mastTypeLabels(): array
    {
        return [
            self::MAST_DUPLA => 'Dupla',
            self::MAST_TRIPLA => 'Tripla',
            self::MAST_DUPLA_DUPLEX => 'Dupla com Duplex',
            self::MAST_RETRATIL => 'Retrátil',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tireTypeLabels(): array
    {
        return [
            self::TIRE_SUPER_ELASTICO => 'Super Elástico',
            self::TIRE_PNEUMATICO => 'Pneumático',
            self::TIRE_CUSHION => 'Cushion',
            self::TIRE_NON_MARKING => 'Non-marking',
            self::TIRE_POLIURETANO => 'Poliuretano',
        ];
    }

    public function isElectric(): bool
    {
        return $this->energy_type === self::ENERGY_ELETRICA;
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
