<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Item da proposta do CRM: produto/serviço do catálogo (ou livre), com quantidade e preço. */
class CrmPropostaItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'crm_proposta_itens';

    protected $fillable = ['tenant_id', 'crm_proposta_id', 'crm_produto_id', 'descricao', 'quantidade', 'preco_unitario', 'subtotal'];

    protected $casts = ['quantidade' => 'decimal:2', 'preco_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (CrmPropostaItem $i) {
            if ($i->crm_produto_id && blank($i->descricao)) {
                $i->descricao = CrmProduto::withoutGlobalScopes()->whereKey($i->crm_produto_id)->value('nome');
            }

            if ($i->crm_produto_id && (float) $i->preco_unitario === 0.0) {
                $i->preco_unitario = (float) CrmProduto::withoutGlobalScopes()->whereKey($i->crm_produto_id)->value('preco');
            }

            $i->subtotal = round((float) $i->quantidade * (float) $i->preco_unitario, 2);
        });

        // O total da proposta acompanha qualquer mudança nos itens.
        static::saved(fn (CrmPropostaItem $i) => $i->proposta?->recalcularTotal());
        static::deleted(fn (CrmPropostaItem $i) => CrmProposta::withoutGlobalScopes()->find($i->crm_proposta_id)?->recalcularTotal());
    }

    public function proposta(): BelongsTo
    {
        return $this->belongsTo(CrmProposta::class, 'crm_proposta_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(CrmProduto::class, 'crm_produto_id');
    }
}
