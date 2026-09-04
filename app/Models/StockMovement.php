<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToTenant, HasFactory;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_stock_movements';
    protected static ?string $saasPermissionSlug = 'movimento_estoque';
    protected static ?string $saasModuleLabel = 'Movimentações de Estoque';

    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'part_id',
        'warehouse_id',
        'movement_type',
        'quantity',
        'balance_before',
        'balance_after',
        'unit_cost',
        'total_cost',
        'reference_document',
        'notes',
        'created_by',
        'created_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public const MOVEMENT_TYPES = [
        'entry_purchase' => 'Entrada - Compra',
        'entry_adjustment' => 'Entrada - Ajuste',
        'entry_return' => 'Entrada - Devolução',
        'exit_work_order' => 'Saída - Ordem de Serviço',
        'exit_adjustment' => 'Saída - Ajuste',
        'exit_loss' => 'Saída - Perda/Quebra',
        'transfer_out' => 'Transferência - Saída',
        'transfer_in' => 'Transferência - Entrada',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->tenant_id) && auth()->check()) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
            if (empty($model->created_by) && auth()->check()) {
                $model->created_by = auth()->id();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }

    public function scopeByPart($query, int $partId)
    {
        return $query->where('part_id', $partId);
    }

    public function scopeByWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function getMovementTypeNameAttribute(): string
    {
        return self::MOVEMENT_TYPES[$this->movement_type] ?? $this->movement_type;
    }

    public function getIsEntryAttribute(): bool
    {
        return str_starts_with($this->movement_type, 'entry_');
    }

    public function getIsExitAttribute(): bool
    {
        return str_starts_with($this->movement_type, 'exit_');
    }

    public function getIsTransferAttribute(): bool
    {
        return str_starts_with($this->movement_type, 'transfer_');
    }

    public static function getTypeLabel(string $type): string
    {
        return self::MOVEMENT_TYPES[$type] ?? $type;
    }
}
