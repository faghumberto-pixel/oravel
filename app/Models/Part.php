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

class Part extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;
    use HasSaaSMetadata;
    use LogsActivity;

    protected static ?string $saasFeatureKey = 'tabela_parts';
    protected static ?string $saasPermissionSlug = 'peca';
    protected static ?string $saasModuleLabel = 'Peças e Insumos';

    protected $fillable = [
        'tenant_id',
        'part_category_id',
        'sku',
        'barcode',
        'name',
        'description',
        'unit_of_measure',
        'cost_price',
        'minimum_stock',
        'maximum_stock',
        'location_shelf',
        'is_active',
    ];

    protected $casts = [
        'cost_price' => 'decimal:4',
        'minimum_stock' => 'decimal:2',
        'maximum_stock' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public const UNITS = [
        'UN' => 'Unidade',
        'PC' => 'Peça',
        'LT' => 'Litro',
        'KG' => 'Quilograma',
        'MT' => 'Metro',
        'JG' => 'Jogo',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(PartCategory::class, 'part_category_id');
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

    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('part_category_id', $categoryId);
    }

    public function scopeByBarcode($query, string $barcode)
    {
        return $query->where('barcode', $barcode);
    }

    public function scopeSearchable($query, string $term)
    {
        return $query->where('name', 'ilike', "%{$term}%")
            ->orWhere('sku', 'ilike', "%{$term}%")
            ->orWhere('barcode', 'ilike', "%{$term}%")
            ->orWhere('description', 'ilike', "%{$term}%");
    }

    // ========== ACESSORES ==========

    public function getTotalStockAttribute(): float
    {
        return $this->stocks()->sum('current_quantity');
    }

    public function getTotalReservedAttribute(): float
    {
        return $this->stocks()->sum('reserved_quantity');
    }

    public function getAvailableStockAttribute(): float
    {
        return $this->total_stock - $this->total_reserved;
    }

    public function getStockStatusAttribute(): string
    {
        $available = $this->available_stock;

        if ($available <= 0) {
            return 'critical';
        } elseif ($available <= $this->minimum_stock) {
            return 'warning';
        } elseif ($available >= $this->maximum_stock) {
            return 'excess';
        }

        return 'normal';
    }

    public static function getUnitLabel(string $unit): string
    {
        return self::UNITS[$unit] ?? $unit;
    }
}
