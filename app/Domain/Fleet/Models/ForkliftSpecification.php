<?php

namespace App\Domain\Fleet\Models;

use App\Models\Asset;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForkliftSpecification extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected $table = 'asset_forklift_specifications';

    protected static ?string $saasFeatureKey = 'tabela_asset_forklift_specifications';

    protected static ?string $saasPermissionSlug = 'especificacao_empilhadeira';

    protected static ?string $saasModuleLabel = 'Especificações de Empilhadeira';

    public const ENERGY_ELETRICA = 'eletrica';

    public const ENERGY_GLP = 'glp';

    public const ENERGY_DIESEL = 'diesel';

    public const ENERGY_GASOLINA = 'gasolina';

    public const ENERGY_MANUAL = 'manual';

    public const MAST_DUPLA = 'dupla';

    public const MAST_TRIPLA = 'tripla';

    public const MAST_DUPLA_DUPLEX = 'dupla_duplex';

    public const MAST_RETRATIL = 'retratil';

    public const TIRE_SUPER_ELASTICO = 'super_elastico';

    public const TIRE_PNEUMATICO = 'pneumatico';

    public const TIRE_CUSHION = 'cushion';

    public const TIRE_NON_MARKING = 'non_marking';

    public const TIRE_POLIURETANO = 'poliuretano';

    public const CLASS_II = 'classe_ii';

    public const CLASS_III = 'classe_iii';

    public const TYPE_CONTRABALANCADA_ELETRICA = 'contrabalancada_eletrica';

    public const TYPE_SELECIONADORA_VERTICAL = 'selecionadora_vertical';

    public const TYPE_RETRATIL = 'retratil';

    public const TYPE_TRILATERAL = 'trilateral';

    public const TYPE_TRANSPALETEIRA_ELETRICA = 'transpaleteira_eletrica';

    public const TYPE_TRANSPALETEIRA_PATOLADA = 'transpaleteira_patolada';

    public const TYPE_TRANSPALETEIRA_SELECIONADORA = 'transpaleteira_selecionadora';

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'forklift_type',
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
            self::ENERGY_MANUAL => 'Manual (sem motor)',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function forkliftTypeLabels(): array
    {
        return [
            self::TYPE_CONTRABALANCADA_ELETRICA => 'Contrabalançada Elétrica (Classe II)',
            self::TYPE_SELECIONADORA_VERTICAL => 'Selecionadora Vertical (Classe II)',
            self::TYPE_RETRATIL => 'Retrátil (Classe II)',
            self::TYPE_TRILATERAL => 'Trilateral (Classe II)',
            self::TYPE_TRANSPALETEIRA_ELETRICA => 'Transpaleteira Elétrica (Classe III)',
            self::TYPE_TRANSPALETEIRA_PATOLADA => 'Transpaleteira Patolada (Classe III)',
            self::TYPE_TRANSPALETEIRA_SELECIONADORA => 'Transpaleteira Selecionadora Horizontal (Classe III)',
        ];
    }

    public static function classFor(?string $forkliftType): ?string
    {
        return match ($forkliftType) {
            self::TYPE_TRANSPALETEIRA_ELETRICA, self::TYPE_TRANSPALETEIRA_PATOLADA, self::TYPE_TRANSPALETEIRA_SELECIONADORA => self::CLASS_III,
            self::TYPE_CONTRABALANCADA_ELETRICA, self::TYPE_SELECIONADORA_VERTICAL, self::TYPE_RETRATIL, self::TYPE_TRILATERAL => self::CLASS_II,
            default => null,
        };
    }

    /**
     * Transpaleteira comum/patolada nao tem torre/mastro elevatorio no
     * mesmo sentido de uma Classe II -- so' a Selecionadora Horizontal
     * chega perto. Usado pra ocultar lift_height_m/mast_type no form.
     */
    public function hasMastLikeElevation(): bool
    {
        return $this->forklift_type !== self::TYPE_TRANSPALETEIRA_ELETRICA
            && $this->forklift_type !== self::TYPE_TRANSPALETEIRA_PATOLADA;
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
