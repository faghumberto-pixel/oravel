<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ordem de Compra formal -- gerada a partir de uma MaterialRequestQuotation
 * selecionada (caminho normal), ou avulsa (material_request_id/
 * material_request_quotation_id nulos, compra direta sem requisicao
 * formal). Recebimento (GoodsReceipt, Fase seguinte) atualiza
 * quantity_received dos itens e o status aqui.
 */
class PurchaseOrder extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_purchase_orders';

    protected static ?string $saasPermissionSlug = 'ordem_compra';

    protected static ?string $saasModuleLabel = 'Ordens de Compra';

    public const STATUS_ABERTA = 'aberta';

    public const STATUS_PARCIALMENTE_RECEBIDA = 'parcialmente_recebida';

    public const STATUS_RECEBIDA = 'recebida';

    public const STATUS_CANCELADA = 'cancelada';

    protected $fillable = [
        'tenant_id',
        'material_request_id',
        'material_request_quotation_id',
        'supplier_id',
        'status',
        'total_value',
        'expected_delivery_date',
        'created_by_user_id',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'expected_delivery_date' => 'date',
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_ABERTA => 'Aberta',
            self::STATUS_PARCIALMENTE_RECEBIDA => 'Parcialmente Recebida',
            self::STATUS_RECEBIDA => 'Recebida',
            self::STATUS_CANCELADA => 'Cancelada',
        ];
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(MaterialRequestQuotation::class, 'material_request_quotation_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Recalcula o status conforme quantity_received de cada item --
     * chamado pelo Recebimento (Fase seguinte) apos gravar as
     * quantidades recebidas, nunca setado a mao pelo usuario.
     */
    public function recalculateStatus(): void
    {
        if ($this->status === self::STATUS_CANCELADA) {
            return;
        }

        $items = $this->items;
        $totalOrdered = $items->sum('quantity');
        $totalReceived = $items->sum('quantity_received');

        $status = match (true) {
            $totalReceived <= 0 => self::STATUS_ABERTA,
            $totalReceived < $totalOrdered => self::STATUS_PARCIALMENTE_RECEBIDA,
            default => self::STATUS_RECEBIDA,
        };

        $this->updateQuietly(['status' => $status]);
    }
}
