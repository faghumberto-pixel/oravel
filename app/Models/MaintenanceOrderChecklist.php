<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MaintenanceOrderChecklist extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

    protected $table = 'maintenance_order_checklists';

    protected $fillable = [
        'maintenance_order_id',
        'category',
        'item_name',
        'instructions',
        'is_completed',
        'status',
        'notes',
        'department_id',
        'code',
        'is_template',
        'checklist_group_id',
        'asset_id',
        'checklist_type',
        'section',
        'tenant_id',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_template' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && Auth::check()) {
                $model->tenant_id = Auth::user()->tenant_id;
            }

            if ($model->status && ! $model->is_template) {
                $model->is_completed = in_array($model->status, ['conforme', 'nao_aplicavel'], true);
            }
        });

        static::updating(function ($model) {
            if ($model->isDirty('status') && ! $model->is_template) {
                $model->is_completed = in_array($model->status, ['conforme', 'nao_aplicavel'], true);
            }
        });
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function checklistGroup(): BelongsTo
    {
        return $this->belongsTo(ChecklistGroup::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->fit(Fit::Contain, 300, 300)
            ->nonQueued();
    }
}
