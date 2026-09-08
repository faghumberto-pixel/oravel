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
        'from_location_id',
        'to_location_id',
        'reason',
        'document_reference',
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

    public const TYPE_ENTRADA_COMPRA = 'entrada_compra';

    public const TYPE_SAIDA_CONSUMO = 'saida_consumo';

    public const TYPE_TRANSFERENCIA = 'transferencia';

    public const TYPE_AJUSTE_MANUAL = 'ajuste_manual';

    public const TYPES = [
        self::TYPE_ENTRADA_COMPRA => 'Entrada (Compra)',
        self::TYPE_SAIDA_CONSUMO => 'Saída (Consumo)',
        self::TYPE_TRANSFERENCIA => 'Transferência',
        self::TYPE_AJUSTE_MANUAL => 'Ajuste (Manual)',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class, 'to_location_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    /**
     * Fabrica unica de linhas do ledger -- chamada por
     * App\Services\MaterialStockService (receive/consume/transfer/adjust),
     * unico writer deste model. Nunca existiu antes de 2026-09-08: o
     * ledger de Material tinha tabela + Resource (App\Filament\Resources\
     * StockMovementResource) prontos desde 2026-07-14/16, mas nenhuma
     * linha nunca foi gravada porque MaterialStockService chamava por
     * engano App\Models\StockMovement::record() (ledger legado de
     * Part/Warehouse, classe errada, sem esse metodo) -- corrigido junto
     * com o modulo de Compras, que depende do recebimento fisico gravar
     * este ledger corretamente.
     */
    public static function record(
        Material $material,
        string $type,
        float $quantity,
        float $balanceAfter,
        $reference = null,
        ?string $userId = null,
        ?string $fromLocationId = null,
        ?string $toLocationId = null,
        ?string $reason = null,
        ?string $documentReference = null,
    ): self {
        return self::create([
            'tenant_id' => $material->tenant_id,
            'material_id' => $material->id,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'reason' => $reason,
            'document_reference' => $documentReference,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'created_by_user_id' => $userId,
        ]);
    }
}
