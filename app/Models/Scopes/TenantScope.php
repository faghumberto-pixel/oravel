<?php

namespace App\Models\Scopes;

use App\Models\Contracts\FiltersByTechnician;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Se estivermos em um ambiente de comando (CLI), não filtra
        if (app()->runningInConsole()) {
            return;
        }

        $user = Auth::user();

        // Se não houver usuário ou for admin, não aplica filtro
        if (! $user || $user->hasRole('admin')) {
            return;
        }

        // Filtra sempre pelo tenant do usuário autenticado
        $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);

        // Filtro adicional por technician_id, APENAS para models
        // que explicitamente implementam o contrato FiltersByTechnician
        // (ex: Asset, MaintenanceRecord). ChatMessage não implementa.
        if ($user->hasRole('Técnico de Manutenção Mecânica')
            && $model instanceof FiltersByTechnician) {
            $builder->where($model->getTable() . '.technician_id', $user->id);
        }
    }
}
