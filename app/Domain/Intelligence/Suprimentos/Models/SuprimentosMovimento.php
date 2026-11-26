<?php

namespace App\Domain\Intelligence\Suprimentos\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Linha da planilha de Suprimentos (aba "Movimento"), guardada junto da fotografia (carga). Sem edição: nova planilha, nova carga. */
class SuprimentosMovimento extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public $timestamps = false;

    protected $table = 'intelligence_suprimentos_movimentos';

    protected $fillable = ['tenant_id', 'carga_id', 'data', 'codigo_item', 'tipo', 'quantidade', 'destino', 'documento'];

    protected $casts = ['data' => 'date', 'quantidade' => 'float'];
}
