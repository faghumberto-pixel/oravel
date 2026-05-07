<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Se estivermos em um ambiente de comando (CLI), não filtra
        if (app()->runningInConsole()) return;

        $user = Auth::user();
        
        // Se não houver usuário ou for admin, não aplica filtro
        if (!$user || $user->hasRole('admin')) return;

        // CORREÇÃO: Usando 'tenant_id' para corresponder ao seu banco de dados real
        $builder->where('tenant_id', $user->tenant_id);

        // Se for técnico, filtra por ele
        if ($user->hasRole('Técnico de Manutenção Mecânica')) {
            $builder->where('technician_id', $user->id);
        }
    }
}