<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Modelo de dashboard tem tenant_id NULO quando é da plataforma, então o escopo comum de tenant (tenant_id = X) o
 * esconderia de todo mundo. Aqui o usuário de uma empresa enxerga os modelos da plataforma e os exclusivos da própria
 * empresa, nunca os exclusivos de outra. Super admin (Central) e console não são filtrados.
 */
class DashboardTemplateScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())) {
            return;
        }

        $coluna = $model->qualifyColumn('tenant_id');

        $builder->where(function (Builder $q) use ($coluna, $user) {
            $q->whereNull($coluna);
            if (filled($user->tenant_id)) {
                $q->orWhere($coluna, $user->tenant_id);
            }
        });
    }
}
