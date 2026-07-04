<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EquipmentMovementItemTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'section',
        'label',
        'sort_order',
        'requires_photo',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'requires_photo' => 'boolean',
    ];
}
