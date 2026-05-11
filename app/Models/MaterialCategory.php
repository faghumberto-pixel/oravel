<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialCategory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'description',
        'tenant_id', // Essencial estar aqui
    ];

    /**
     * Relação obrigatória para o Multi-tenancy do Filament
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}