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

    public const TYPE_CENTRAL = 'central';

    public const TYPE_MOBILE = 'mobile';

    public const TYPE_JOB_SITE = 'job_site';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'type',
        'address',
        'city',
        'state',
        'is_active',
        'manager_id',
        'user_id',
        'vehicle_plate',
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

    /**
     * O técnico responsável por este Almoxarifado Volante (type=mobile) --
     * distinto de 'manager', que é quem administra o almoxarifado (pode
     * ser o supervisor, não necessariamente quem carrega o estoque no
     * veículo).
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

    /**
     * O Almoxarifado Volante vigente de um técnico -- assume no máximo 1
     * warehouse type=mobile ativo por user_id (não há constraint de banco
     * pra isso, mas é a premissa de design: um técnico usa um veículo por
     * vez). Usado por StockTransferService::consumePartInWorkOrder() pra
     * resolver automaticamente onde debitar.
     */
    public function scopeMobileForUser($query, string $userId)
    {
        return $query->where('type', self::TYPE_MOBILE)
            ->where('user_id', $userId)
            ->where('is_active', true);
    }

    // ========== HELPERS ==========

    public static function typeLabels(): array
    {
        return [
            self::TYPE_CENTRAL => 'Central',
            self::TYPE_MOBILE => 'Volante (Veículo/Técnico)',
            self::TYPE_JOB_SITE => 'Canteiro de Obra',
        ];
    }

    public function isMobile(): bool
    {
        return $this->type === self::TYPE_MOBILE;
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
