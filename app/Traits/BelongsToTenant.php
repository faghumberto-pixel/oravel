<?php

namespace App\Traits;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        // Ao criar um novo modelo, associa-o ao tenant_id do usuário logado
        static::creating(function ($model) {
            if (Auth::check() && Auth::user()->tenant_id) {
                $model->tenant_id = Auth::user()->tenant_id;
            }
        });

        // Aplica um escopo global para filtrar os dados pelo tenant_id do usuário logado
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check() && Auth::user()->tenant_id) {
                $builder->where('tenant_id', Auth::user()->tenant_id);
            }
        });
    }

    /**
     * Define a relação BelongsTo com o modelo Tenant.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}