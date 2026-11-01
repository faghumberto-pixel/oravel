<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcompanhamentoPendencia extends Model
{
    use HasUuids;

    protected $table = 'acompanhamento_pendencias';

    protected $guarded = [];

    protected $casts = ['prazo' => 'date'];

    public function acompanhamento(): BelongsTo
    {
        return $this->belongsTo(Acompanhamento::class);
    }
}
