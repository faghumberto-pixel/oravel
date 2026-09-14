<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Avaliacao/scoring de um fornecedor (prazo de entrega, qualidade, preco)
 * -- normalmente criada a partir da acao "Avaliar Fornecedor" em
 * GoodsReceiptResource, depois de um recebimento. Sem Resource proprio,
 * gerenciada via RelationManager dentro do SupplierResource;
 * App\Observers\SupplierEvaluationObserver recalcula score_medio e o
 * cache Supplier.rating_avg.
 */
class SupplierEvaluation extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'purchase_order_id',
        'evaluated_by_user_id',
        'score_prazo_entrega',
        'score_qualidade',
        'score_preco',
        'score_medio',
        'notes',
        'evaluated_at',
    ];

    protected $casts = [
        'score_prazo_entrega' => 'integer',
        'score_qualidade' => 'integer',
        'score_preco' => 'integer',
        'score_medio' => 'decimal:2',
        'evaluated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (SupplierEvaluation $evaluation) {
            $evaluation->score_medio = round((
                $evaluation->score_prazo_entrega
                + $evaluation->score_qualidade
                + $evaluation->score_preco
            ) / 3, 2);
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function evaluatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by_user_id');
    }
}
