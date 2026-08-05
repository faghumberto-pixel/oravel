<?php

namespace App\Domain\Fleet\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalHourFranchise extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    public const PERIOD_MENSAL = 'mensal';

    public const PERIOD_SEMANAL = 'semanal';

    protected static ?string $saasFeatureKey = 'tabela_rental_hour_franchises';

    protected static ?string $saasPermissionSlug = 'franquia_horas_locacao';

    protected static ?string $saasModuleLabel = 'Franquias de Horas de Locação';

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'included_hours_per_period',
        'period_type',
        'overage_rate_per_hour',
        'effective_from',
    ];

    protected $casts = [
        'included_hours_per_period' => 'decimal:2',
        'overage_rate_per_hour' => 'decimal:2',
        'effective_from' => 'date',
    ];

    /**
     * @return array<string, string>
     */
    public static function periodTypeLabels(): array
    {
        return [
            self::PERIOD_MENSAL => 'Mensal',
            self::PERIOD_SEMANAL => 'Semanal',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }
}
