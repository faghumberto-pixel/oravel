<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Evento formal de "chegada no patio", um por movimentacao (unique em
 * equipment_movement_id). Registro filho de EquipmentMovement -- sem
 * HasSaaSMetadata/Policy propria, mesmo padrao de EquipmentMovementItem.
 */
class EquipmentPatioArrival extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasUuids;
    use InteractsWithMedia;

    protected $fillable = [
        'tenant_id',
        'equipment_movement_id',
        'arrived_at',
        'confirmed_by_user_id',
        'initial_condition_notes',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('initial_condition_photos');
    }

    public function equipmentMovement(): BelongsTo
    {
        return $this->belongsTo(EquipmentMovement::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }
}
