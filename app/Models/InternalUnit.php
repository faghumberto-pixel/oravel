<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalUnit extends Model
{
    // Não esqueça de garantir que o tenant_id está no fillable
    protected $fillable = ['name', 'tenant_id', 'description']; 

    /**
     * O vínculo que o Filament está cobrando
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}