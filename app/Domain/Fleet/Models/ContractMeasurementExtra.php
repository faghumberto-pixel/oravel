<?php

namespace App\Domain\Fleet\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractMeasurementExtra extends Model
{
    use HasUuids;

    public const TYPE_MOBILIZACAO = 'mobilizacao';

    public const TYPE_DESMOBILIZACAO = 'desmobilizacao';

    public const TYPE_OUTRO = 'outro';

    protected $fillable = [
        'contract_measurement_id',
        'type',
        'description',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * @return array<string, string>
     */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_MOBILIZACAO => 'Mobilização',
            self::TYPE_DESMOBILIZACAO => 'Desmobilização',
            self::TYPE_OUTRO => 'Outro',
        ];
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(ContractMeasurement::class, 'contract_measurement_id');
    }
}
