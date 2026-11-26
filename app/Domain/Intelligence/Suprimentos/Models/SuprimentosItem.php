<?php

namespace App\Domain\Intelligence\Suprimentos\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Linha da planilha de Suprimentos (aba "Item"), guardada junto da fotografia (carga). Sem edição: nova planilha, nova carga. */
class SuprimentosItem extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'intelligence_suprimentos_itens';

    protected $fillable = ['tenant_id', 'carga_id', 'codigo', 'descricao', 'categoria', 'unidade', 'quantidade', 'estoque_minimo', 'custo_unitario', 'ultima_entrada', 'ultima_saida', 'local'];

    protected $casts = ['ultima_entrada' => 'date', 'ultima_saida' => 'date', 'quantidade' => 'float', 'estoque_minimo' => 'float', 'custo_unitario' => 'float'];
}
