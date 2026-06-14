<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use App\Models\Traits\BelongsToTenant;

class MeasurementUnit extends Model
{
    use HasUuids, BelongsToTenant;

    protected $keyType = 'string';
    public $incrementing = false;
    
    // Isso é vital para o Laravel processar created_at e updated_at
    public $timestamps = true; 

    protected $fillable = [
        'tenant_id', 
        'name', 
        'abbreviation'
    ];

    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo 
    { 
        return $this->belongsTo(Tenant::class, 'tenant_id'); 
    }
}