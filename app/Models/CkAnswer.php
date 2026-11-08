<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Resposta de uma execucao, com snapshot do item (texto, tipo, criticidade, referencia). Nao e modulo SaaS proprio. */
class CkAnswer extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'ck_execution_answers';

    protected $guarded = [];

    protected $casts = ['critico' => 'boolean', 'valor_numerico' => 'decimal:3', 'fotos' => 'array'];

    public function execucao(): BelongsTo
    {
        return $this->belongsTo(CkExecution::class, 'execution_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CkItem::class, 'item_id');
    }
}
