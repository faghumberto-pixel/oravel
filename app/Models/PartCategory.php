<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class PartCategory extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;
    use HasSaaSMetadata;
    use LogsActivity;

    protected static ?string $saasFeatureKey = 'tabela_parts';
    protected static ?string $saasPermissionSlug = 'peca';
    protected static ?string $saasModuleLabel = 'Peças e Insumos';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
            if (empty($model->slug)) {
                $model->slug = \Str::slug($model->name);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty();
    }

    // ========== RELACIONAMENTOS ==========

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(Part::class);
    }
}
