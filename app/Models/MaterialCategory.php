<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Importante
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialCategory extends Model
{
    use HasUuids, BelongsToTenant, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'tenant_id'
    ];

    /**
     * RELACIONAMENTO COM TENANT (Empresa)
     * Isso resolve o erro "does not have a relationship named [tenant]"
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'category_id');
    }
}