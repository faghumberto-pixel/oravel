<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class EquipmentMovementItem extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasUuids;
    use InteractsWithMedia;

    protected $fillable = [
        'equipment_movement_id',
        'tenant_id',
        'section',
        'label',
        'sort_order',
        'requires_photo',
        'is_checked',
        'value',
        'notes',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'requires_photo' => 'boolean',
        'is_checked' => 'boolean',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function equipmentMovement(): BelongsTo
    {
        return $this->belongsTo(EquipmentMovement::class);
    }
}
