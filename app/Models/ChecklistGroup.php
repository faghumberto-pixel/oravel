<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChecklistGroup extends Model
{
    use \App\Models\Concerns\BelongsToTenant;
    // Removida a Trait ausente e corrigida a ordem das chaves { }

    protected $fillable = [
        'name', 
        'tenant_id', 
        'description'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            // Garante a injeção automática e segura do tenant_id em tempo real
            if (empty($model->tenant_id)) {
                $model->tenant_id = Auth::user()?->tenant_id 
                                    ?? filament()->getTenant()?->id 
                                    ?? session('tenant_id');
            }
        });
    }

    /**
     * RELAÇÃO OBRIGATÓRIA PARA O FILAMENT (Tenancy)
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
