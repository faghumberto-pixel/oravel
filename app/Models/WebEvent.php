<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebEvent extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['occurred_at' => 'datetime'];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(WebVisit::class, 'web_visit_id');
    }
}
