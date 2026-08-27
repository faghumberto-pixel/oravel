<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmpTemplateChecklistItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'pmp_equipment_family_id',
        'section',
        'item_name',
        'instructions',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(PmpEquipmentFamily::class, 'pmp_equipment_family_id');
    }
}
