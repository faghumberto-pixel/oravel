<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Cabecalho do recebimento fisico de uma PurchaseOrder -- pode ser
 * parcial, por isso varios GoodsReceipt cabem numa mesma Ordem de Compra.
 */
class GoodsReceipt extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_goods_receipts';

    protected static ?string $saasPermissionSlug = 'recebimento_compra';

    protected static ?string $saasModuleLabel = 'Recebimento de Compras';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'internal_unit_id',
        'received_by_user_id',
        'received_at',
        'invoice_number',
        'notes',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function accountPayable(): HasOne
    {
        return $this->hasOne(AccountPayable::class);
    }

    /**
     * Gera a Conta a Pagar deste recebimento (mesmo padrao de
     * Quote::forwardToFinanceiro(), so' que pro lado de Contas a Pagar).
     * Valor = soma de quantity_received x unit_price dos itens recebidos
     * (nao o total_value da PO inteira, que pode cobrir mais de um
     * recebimento parcial). due_date usa o payment_terms da cotacao
     * selecionada quando disponivel (ex.: "30 dias"), senao 30 dias fixo.
     */
    public function generateAccountPayable(): AccountPayable
    {
        if ($this->accountPayable()->exists()) {
            throw new \RuntimeException('Este recebimento já gerou uma Conta a Pagar.');
        }

        $amount = $this->items->sum(fn (GoodsReceiptItem $item) => (float) $item->quantity_received * (float) $item->purchaseOrderItem->unit_price);

        return $this->accountPayable()->create([
            'tenant_id' => $this->tenant_id,
            'supplier_id' => $this->purchaseOrder->supplier_id,
            'description' => 'Recebimento de compra — '.($this->purchaseOrder->supplier?->name ?? 'Fornecedor').' — NF '.($this->invoice_number ?? 's/n'),
            'amount' => $amount,
            'due_date' => now()->addDays(30),
        ]);
    }
}
