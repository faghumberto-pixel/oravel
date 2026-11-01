<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcompanhamentoNota extends Model
{
    use HasUuids;

    protected $table = 'acompanhamento_notas';

    protected $guarded = [];

    protected $casts = ['data' => 'date'];

    public function acompanhamento(): BelongsTo
    {
        return $this->belongsTo(Acompanhamento::class);
    }
}
