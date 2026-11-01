<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProspectContato extends Model
{
    use HasUuids;

    protected $table = 'prospect_contatos';

    protected $guarded = [];

    protected $casts = ['data' => 'date'];

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }
}
