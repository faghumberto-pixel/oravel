<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialCategory extends Model
{
    use HasUuids;

    protected $fillable = [
        'name', 
        'tenant_id'
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'category_id');
    }
}