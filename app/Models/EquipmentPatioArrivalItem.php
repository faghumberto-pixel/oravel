<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Item do checklist do Laudo de Recebimento -- copia estrutural de
 * EquipmentMovementItem, so' que pendurado em EquipmentPatioArrival.
 */
class EquipmentPatioArrivalItem extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasUuids;
    use InteractsWithMedia;

    public const RESULT_OK = 'ok';

    public const RESULT_NOK = 'nok';

    public const RESULT_NA = 'nao_aplicavel';

    protected $fillable = [
        'equipment_patio_arrival_id',
        'tenant_id',
        'section',
        'label',
        'sort_order',
        'requires_photo',
        'is_checked',
        'value',
        'result',
        'notes',
        'has_damage',
    ];

    /**
     * @return array<string, string>
     */
    public static function resultLabels(): array
    {
        return [
            self::RESULT_OK => 'OK',
            self::RESULT_NOK => 'NOK',
            self::RESULT_NA => 'N/A',
        ];
    }

    protected $casts = [
        'sort_order' => 'integer',
        'requires_photo' => 'boolean',
        'is_checked' => 'boolean',
        'has_damage' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function equipmentPatioArrival(): BelongsTo
    {
        return $this->belongsTo(EquipmentPatioArrival::class);
    }
}
