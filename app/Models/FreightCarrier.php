<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FreightCarrier extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_freight_carriers';

    protected static ?string $saasPermissionSlug = 'transportadora';

    protected static ?string $saasModuleLabel = 'Transportadoras';

    public const VEHICLE_PRANCHA = 'prancha';

    public const VEHICLE_MUNCK = 'munck';

    public const VEHICLE_GUINCHO = 'guincho';

    public const VEHICLE_CARRETA = 'carreta';

    public const VEHICLE_OUTRO = 'outro';

    protected $fillable = [
        'tenant_id',
        'nome',
        'documento',
        'contato_nome',
        'contato_telefone',
        'vehicle_types',
        'insurance_policy_number',
        'insurance_coverage_value',
    ];

    protected $casts = [
        'vehicle_types' => 'array',
        'insurance_coverage_value' => 'decimal:2',
    ];

    /**
     * @return array<string, string>
     */
    public static function vehicleTypeLabels(): array
    {
        return [
            self::VEHICLE_PRANCHA => 'Prancha',
            self::VEHICLE_MUNCK => 'Munck',
            self::VEHICLE_GUINCHO => 'Guincho',
            self::VEHICLE_CARRETA => 'Carreta',
            self::VEHICLE_OUTRO => 'Outro',
        ];
    }

    public function freightRecords(): HasMany
    {
        return $this->hasMany(FreightRecord::class);
    }
}
