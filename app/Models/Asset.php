<?php

namespace App\Models;

// Note que não herdamos mais de 'Illuminate\Database\Eloquent\Model'
class Asset extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'nome',
        'codigo',
        // outros campos...
    ];
}