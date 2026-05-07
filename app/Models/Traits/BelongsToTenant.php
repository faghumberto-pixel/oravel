<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant()
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            // 1. Tabelas que NUNCA devem ser filtradas pelo Tenant
            $excluded = [
                'roles', 
                'permissions', 
                'model_has_roles', 
                'model_has_permissions', 
                'role_has_permissions', 
                'users'
            ];

            if (in_array($builder->getModel()->getTable(), $excluded)) {
                return;
            }

            // 2. Proteção contra execução no terminal ou inicialização precoce
            if (app()->runningInConsole()) {
                return;
            }

            // 3. Aplicação do filtro com segurança
            if (Auth::check() && Auth::user()->tenant_id) {
                $builder->where('tenant_id', Auth::user()->tenant_id);
            }
        });
    }
}