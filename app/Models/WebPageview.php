<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebPageview extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = ['entered_at' => 'datetime', 'active_seconds' => 'integer', 'max_scroll' => 'integer'];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(WebVisit::class, 'web_visit_id');
    }
}
