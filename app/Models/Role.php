<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Database\Eloquent\Builder;

class Role extends SpatieRole
{
    /**
     * O Spatie Role usa IDs padrão do pacote.
     * Desabilitamos o filtro de tenant para este modelo específico,
     * pois a tabela 'roles' não possui a coluna 'tenant_id'.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('ignore_tenant', function (Builder $builder) {
            // Este escopo vazio garante que o sistema não tente
            // injetar filtros de tenant automaticamente nesta tabela.
        });
    }
}