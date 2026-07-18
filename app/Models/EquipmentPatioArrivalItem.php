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

    protected $fillable = [
        'equipment_patio_arrival_id',
        'tenant_id',
        'section',
        'label',
        'sort_order',
        'requires_photo',
        'is_checked',
        'value',
        'notes',
        'has_damage',
    ];

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
