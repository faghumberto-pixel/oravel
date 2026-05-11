<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Company extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'cnpj',
        'address',
        'tenant_id', // Essencial para o vínculo
    ];

    /**
     * Relação obrigatória para o Multi-tenancy do Filament
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}