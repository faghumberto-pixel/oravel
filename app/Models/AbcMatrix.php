<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Traits\BelongsToTenant;

class AbcMatrix extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'asset_id',
        'classification',
        'value_percentage',
        'notes',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }
}
