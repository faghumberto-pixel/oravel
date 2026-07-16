<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Ledger formal de entrada/saida de estoque de Material -- toda mudanca
 * em Material.current_stock (fora do cadastro manual direto) devia
 * gravar uma linha aqui, pra ter um historico auditavel de "por que o
 * estoque mudou" (tabela `material_stock_movements`, ver migration
 * 2026_07_14_100000 -- nome novo pra nao colidir com o arquivo morto
 * "create_stock_movements_table" que nunca criava a tabela de verdade).
 */
class StockMovement extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_material_stock_movements';

    protected static ?string $saasPermissionSlug = 'movimentacao_estoque';

    protected static ?string $saasModuleLabel = 'Histórico de Estoque';

    protected $table = 'material_stock_movements';

    public const TYPE_ENTRADA_COMPRA = 'entrada_compra';

    public const TYPE_SAIDA_CONSUMO = 'saida_consumo';

    public const TYPE_AJUSTE_MANUAL = 'ajuste_manual';

    public const TYPE_TRANSFERENCIA = 'transferencia';

    protected $fillable = [
        'tenant_id',
        'material_id',
        'from_location_id',
        'to_location_id',
        'type',
        'reason',
        'document_reference',
        'quantity',
        'balance_after',
        'reference_type',
        'reference_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'balance_after' => 'decimal:2',
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

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Fonte unica de escrita no ledger -- sempre grava o saldo resultante
     * (nao so a variacao), pra permitir auditoria rapida sem precisar
     * somar tudo desde o inicio. Nao atualiza Material.current_stock
     * sozinho (quem chama decide o saldo antes de gravar aqui).
     *
     * $quantity e' sempre positivo pra entrada/saida (o "type" ja diz o
     * sentido) -- so' pra ajuste_manual ele carrega o sinal (pode ser
     * negativo, quando o inventario acha menos do que o sistema previa).
     *
     * from/to location, reason e document_reference sao opcionais --
     * as 3 chamadas antigas (compra/consumo/ajuste) nao precisam deles,
     * so' transferencia entre filiais (App\Services\MaterialStockService)
     * os usa de verdade.
     */
    public static function record(
        Material $material,
        string $type,
        float $quantity,
        float $balanceAfter,
        ?Model $reference = null,
        ?string $createdByUserId = null,
        ?string $fromLocationId = null,
        ?string $toLocationId = null,
        ?string $reason = null,
        ?string $documentReference = null,
    ): self {
        return self::create([
            'tenant_id' => $material->tenant_id,
            'material_id' => $material->id,
            'from_location_id' => $fromLocationId,
            'to_location_id' => $toLocationId,
            'type' => $type,
            'reason' => $reason,
            'document_reference' => $documentReference,
            'quantity' => $type === self::TYPE_AJUSTE_MANUAL ? $quantity : abs($quantity),
            'balance_after' => $balanceAfter,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'created_by_user_id' => $createdByUserId,
        ]);
    }
}
