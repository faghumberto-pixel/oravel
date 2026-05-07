<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 
        'name', 
        'address', 
        'city', 
        'state', 
        'zip_code', 
        'tenant_id'
    ];

    // Relacionamento com Tenant
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}