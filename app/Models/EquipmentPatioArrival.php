<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * Valor de EquipmentMovementItemTemplate.type usado pro Laudo de
     * Recebimento -- reaproveita a mesma tabela de templates ja usada por
     * mobilizacao/desmobilizacao (generica o bastante: type/section/label/
     * sort_order/requires_photo), so' com um terceiro valor de type.
     */
    public const TEMPLATE_TYPE = 'recebimento';

    protected $fillable = [
        'tenant_id',
        'equipment_movement_id',
        'arrived_at',
        'confirmed_by_user_id',
        'initial_condition_notes',
        'completed_at',
        'quarantine_released_at',
        'quarantine_released_by_user_id',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'quarantine_released_at' => 'datetime',
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

    public function quarantineReleasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quarantine_released_by_user_id');
    }

    /** Itens do Laudo de Recebimento (checklist item a item). */
    public function items(): HasMany
    {
        return $this->hasMany(EquipmentPatioArrivalItem::class);
    }
}
