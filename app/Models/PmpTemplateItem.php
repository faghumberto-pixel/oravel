<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmpTemplateItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'pmp_equipment_family_id',
        'name',
        'periodicity_label',
        'interval_hours',
        'interval_days',
        'is_critical',
        'notes',
    ];

    protected $casts = [
        'is_critical' => 'boolean',
        'interval_hours' => 'integer',
        'interval_days' => 'integer',
    ];

    public function family(): BelongsTo
    {
        return $this->belongsTo(PmpEquipmentFamily::class, 'pmp_equipment_family_id');
    }
}
