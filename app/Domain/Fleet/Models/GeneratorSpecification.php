<?php

namespace App\Domain\Fleet\Models;

use App\Models\Asset;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratorSpecification extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected $table = 'asset_generator_specifications';

    protected static ?string $saasFeatureKey = 'tabela_asset_generator_specifications';

    protected static ?string $saasPermissionSlug = 'especificacao_gerador';

    protected static ?string $saasModuleLabel = 'Especificações de Gerador';

    public const VOLTAGE_MONOFASICO = 'monofasico';

    public const VOLTAGE_TRIFASICO = 'trifasico';

    public const STARTER_ELETRICA = 'eletrica';

    public const STARTER_MANUAL = 'manual';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'voltage_type',
        'voltage',
        'fuel_tank_capacity_l',
        'starter_type',
    ];

    protected $casts = [
        'fuel_tank_capacity_l' => 'decimal:2',
    ];

    /**
     * @return array<string, string>
     */
    public static function voltageTypeLabels(): array
    {
        return [
            self::VOLTAGE_MONOFASICO => 'Monofásico',
            self::VOLTAGE_TRIFASICO => 'Trifásico',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function starterTypeLabels(): array
    {
        return [
            self::STARTER_ELETRICA => 'Elétrica',
            self::STARTER_MANUAL => 'Manual',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
