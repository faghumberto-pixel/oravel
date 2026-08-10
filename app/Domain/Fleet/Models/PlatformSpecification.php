<?php

namespace App\Domain\Fleet\Models;

use App\Models\Asset;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformSpecification extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected $table = 'asset_platform_specifications';

    protected static ?string $saasFeatureKey = 'tabela_asset_platform_specifications';

    protected static ?string $saasPermissionSlug = 'especificacao_plataforma';

    protected static ?string $saasModuleLabel = 'Especificações de Plataforma Elevatória';

    public const TYPE_TESOURA = 'tesoura';

    public const TYPE_ARTICULADA = 'articulada';

    public const TYPE_TELESCOPICA = 'telescopica';

    public const TYPE_MASTRO_VERTICAL = 'mastro_vertical';

    public const ENERGY_ELETRICA = 'eletrica';

    public const ENERGY_DIESEL = 'diesel';

    public const ENERGY_HIBRIDA = 'hibrida';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'platform_type',
        'energy_type',
        'working_height_m',
        'platform_height_m',
        'horizontal_outreach_m',
        'platform_capacity_kg',
        'operational_weight_kg',
    ];

    protected $casts = [
        'working_height_m' => 'decimal:2',
        'platform_height_m' => 'decimal:2',
        'horizontal_outreach_m' => 'decimal:2',
        'platform_capacity_kg' => 'decimal:2',
        'operational_weight_kg' => 'decimal:2',
    ];

    /**
     * @return array<string, string>
     */
    public static function platformTypeLabels(): array
    {
        return [
            self::TYPE_TESOURA => 'Tesoura (Scissor Lift)',
            self::TYPE_ARTICULADA => 'Articulada (Boom Lift)',
            self::TYPE_TELESCOPICA => 'Telescópica',
            self::TYPE_MASTRO_VERTICAL => 'Mastro Vertical',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function energyTypeLabels(): array
    {
        return [
            self::ENERGY_ELETRICA => 'Elétrica',
            self::ENERGY_DIESEL => 'Diesel',
            self::ENERGY_HIBRIDA => 'Híbrida',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
