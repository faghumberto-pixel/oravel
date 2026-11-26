<?php

namespace App\Domain\Intelligence\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Linha da planilha de Suprimentos (aba "Compra"), guardada junto da fotografia (carga). Sem edição: nova planilha, nova carga. */
class SuprimentosCompra extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'intelligence_suprimentos_compras';

    protected $fillable = ['tenant_id', 'carga_id', 'pedido', 'data_pedido', 'codigo_item', 'fornecedor', 'quantidade', 'valor_total', 'prazo_prometido', 'data_chegada', 'emergencial'];

    protected $casts = ['data_pedido' => 'date', 'prazo_prometido' => 'date', 'data_chegada' => 'date', 'emergencial' => 'boolean', 'quantidade' => 'float', 'valor_total' => 'float'];
}
