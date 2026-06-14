<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CancellationReason extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    use HasUuids;

    protected $table = 'cancellation_reasons';

    protected $fillable = [
        'name',
        'tenant_id',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
