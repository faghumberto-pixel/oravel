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

class Warehouse extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;
    use HasSaaSMetadata;
    use LogsActivity;

    protected static ?string $saasFeatureKey = 'tabela_warehouses';
    protected static ?string $saasPermissionSlug = 'almoxarifado';
    protected static ?string $saasModuleLabel = 'Almoxarifados';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'city',
        'state',
        'is_active',
        'manager_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id;
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

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // ========== SCOPES ==========

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ========== ACESSORES ==========

    public function getTotalStockValueAttribute(): float
    {
        return $this->stocks()
            ->with('part')
            ->get()
            ->sum(fn ($stock) => $stock->current_quantity * $stock->part->cost_price);
    }

    public function getCriticalItemsCountAttribute(): int
    {
        return $this->stocks()
            ->with('part')
            ->get()
            ->filter(fn ($stock) => $stock->current_quantity < $stock->part->minimum_stock)
            ->count();
    }
}
