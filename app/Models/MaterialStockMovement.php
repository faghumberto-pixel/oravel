<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStockMovement extends Model
{
    use BelongsToTenant, HasUuids;
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = 'tabela_material_stock_movements';

    protected static ?string $saasPermissionSlug = 'movimento_estoque';

    protected static ?string $saasModuleLabel = 'Histórico de Movimentação';

    protected $fillable = [
        'tenant_id',
        'material_id',
        'type',
        'quantity',
        'balance_after',
        'reference_type',
        'reference_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const TYPE_ENTRADA = 'entrada';
    public const TYPE_SAIDA = 'saida';
    public const TYPE_AJUSTE = 'ajuste';

    public const TYPES = [
        self::TYPE_ENTRADA => 'Entrada',
        self::TYPE_SAIDA => 'Saída',
        self::TYPE_AJUSTE => 'Ajuste',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
