<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'price',
        'billing_cycle',
        'features',
        'is_active',
    ];

    /**
     * O SEGREDO ESTÁ AQUI:
     * Isso resolve o erro de "Array to string conversion"
     */
    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }
}