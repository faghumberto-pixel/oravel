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
 * formal). Recebimento (GoodsReceipt) atualiza quantity_received dos
 * itens e o status aqui.
 *
 * Pipeline de status: rascunho -> aguardando_aprovacao -> aprovada ->
 * enviada_fornecedor -> parcialmente_recebida -> recebida (ou cancelada
 * a partir de qualquer estado nao-terminal). Uma PO nascida do caminho
 * normal (requisicao ja aprovada + cotacao selecionada, ver
 * EditMaterialRequest::gerar_ordem_compra) nasce direto em "aprovada" --
 * o gasto ja foi autorizado la' atras, na requisicao -- e so' passa pela
 * aprovacao propria quando e' avulsa (sem material_request_id), caso em
 * que nada foi autorizado antes.
 */
class PurchaseOrder extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_purchase_orders';

    protected static ?string $saasPermissionSlug = 'ordem_compra';

    protected static ?string $saasModuleLabel = 'Ordens de Compra';

    public const STATUS_RASCUNHO = 'rascunho';

    public const STATUS_AGUARDANDO_APROVACAO = 'aguardando_aprovacao';

    public const STATUS_APROVADA = 'aprovada';

    public const STATUS_ENVIADA_FORNECEDOR = 'enviada_fornecedor';

    public const STATUS_PARCIALMENTE_RECEBIDA = 'parcialmente_recebida';

    public const STATUS_RECEBIDA = 'recebida';

    public const STATUS_CANCELADA = 'cancelada';

    /**
     * Papel que aprova PO avulsa -- mesmo papel/pessoas que ja aprovam
     * MaterialRequest (ver Material::ROLE_GESTOR_SUPRIMENTOS).
     */
    public const ROLE_GESTOR_SUPRIMENTOS = Material::ROLE_GESTOR_SUPRIMENTOS;

    protected $fillable = [
        'tenant_id',
        'material_request_id',
        'material_request_quotation_id',
        'supplier_id',
        'status',
        'total_value',
        'expected_delivery_date',
        'created_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'sent_at',
        'rejection_reason',
    ];

    protected $casts = [
        'total_value' => 'decimal:2',
        'expected_delivery_date' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_RASCUNHO,
    ];

    public static function statusOptions(): array
    {
        return [
            self::STATUS_RASCUNHO => 'Rascunho',
            self::STATUS_AGUARDANDO_APROVACAO => 'Aguardando Aprovação',
            self::STATUS_APROVADA => 'Aprovada',
            self::STATUS_ENVIADA_FORNECEDOR => 'Enviada ao Fornecedor',
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

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class);
    }

    public function isFromApprovedRequest(): bool
    {
        return $this->material_request_id !== null;
    }

    public function submitForApproval(): void
    {
        if ($this->status !== self::STATUS_RASCUNHO) {
            throw new \RuntimeException('Só é possível enviar para aprovação uma Ordem de Compra em rascunho.');
        }

        $this->update(['status' => self::STATUS_AGUARDANDO_APROVACAO]);
    }

    public function approve(User $approver): void
    {
        if ($this->status !== self::STATUS_AGUARDANDO_APROVACAO) {
            throw new \RuntimeException('Só é possível aprovar uma Ordem de Compra aguardando aprovação.');
        }

        $this->update([
            'status' => self::STATUS_APROVADA,
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    public function reject(User $approver, string $reason): void
    {
        if ($this->status !== self::STATUS_AGUARDANDO_APROVACAO) {
            throw new \RuntimeException('Só é possível recusar uma Ordem de Compra aguardando aprovação.');
        }

        $this->update([
            'status' => self::STATUS_CANCELADA,
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public function sendToSupplier(): void
    {
        if ($this->status !== self::STATUS_APROVADA) {
            throw new \RuntimeException('Só é possível enviar ao fornecedor uma Ordem de Compra aprovada.');
        }

        $this->update([
            'status' => self::STATUS_ENVIADA_FORNECEDOR,
            'sent_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        if (in_array($this->status, [self::STATUS_RECEBIDA, self::STATUS_CANCELADA], true)) {
            throw new \RuntimeException('Esta Ordem de Compra não pode mais ser cancelada.');
        }

        $this->update(['status' => self::STATUS_CANCELADA]);
    }

    /**
     * Recalcula o status conforme quantity_received de cada item --
     * chamado pelo Recebimento apos gravar as quantidades recebidas,
     * nunca setado a mao pelo usuario. So' atua a partir de
     * "enviada_fornecedor" em diante -- rascunho/aguardando_aprovacao/
     * aprovada/cancelada nunca sao sobrescritos por aqui (recebimento so'
     * e' possivel depois de enviada, ver GoodsReceiptResource).
     */
    public function recalculateStatus(): void
    {
        if (! in_array($this->status, [
            self::STATUS_ENVIADA_FORNECEDOR,
            self::STATUS_PARCIALMENTE_RECEBIDA,
            self::STATUS_RECEBIDA,
        ], true)) {
            return;
        }

        $items = $this->items;
        $totalOrdered = $items->sum('quantity');
        $totalReceived = $items->sum('quantity_received');

        $status = match (true) {
            $totalReceived <= 0 => self::STATUS_ENVIADA_FORNECEDOR,
            $totalReceived < $totalOrdered => self::STATUS_PARCIALMENTE_RECEBIDA,
            default => self::STATUS_RECEBIDA,
        };

        $this->updateQuietly(['status' => $status]);
    }
}
