<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistGroup extends Model
{
    // Adicione o tenant_id no fillable para permitir o salvamento
    protected $fillable = ['name', 'tenant_id', 'description']; 

    /**
     * ESSA É A RELAÇÃO QUE O FILAMENT ESTÁ COBRANDO:
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}