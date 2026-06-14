<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class MaintenanceOrderChecklist extends Model
{
    use HasUuids;
    use \App\Models\Traits\BelongsToTenant;

    protected $table = 'maintenance_order_checklists';

    protected $fillable = [
        'maintenance_order_id',
        'category',
        'item_name',
        'instructions',
        'is_completed',
        'notes',
        'department_id',
        'code',
        'is_template',
        'checklist_group_id',
        'checklist_type',
        'section',
        'tenant_id',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_template'  => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }
}
