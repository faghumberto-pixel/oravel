<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item de interesse do lead: produto/serviço do catálogo (ou item livre) com quantidade e preço. */
class CrmLeadItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'crm_lead_itens';

    protected $fillable = ['tenant_id', 'crm_lead_id', 'crm_produto_id', 'descricao', 'quantidade', 'preco_unitario', 'subtotal'];

    protected $casts = ['quantidade' => 'decimal:2', 'preco_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (CrmLeadItem $item) {
            if ($item->crm_produto_id && blank($item->descricao)) {
                $item->descricao = CrmProduto::withoutGlobalScopes()->whereKey($item->crm_produto_id)->value('nome');
            }

            if ($item->crm_produto_id && (float) $item->preco_unitario === 0.0) {
                $item->preco_unitario = (float) CrmProduto::withoutGlobalScopes()->whereKey($item->crm_produto_id)->value('preco');
            }

            $item->subtotal = round((float) $item->quantidade * (float) $item->preco_unitario, 2);
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(CrmProduto::class, 'crm_produto_id');
    }
}
