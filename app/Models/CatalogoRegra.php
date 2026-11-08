<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

class CatalogoRegra extends CatalogoModel
{
    use BelongsToTenant; // declarado aqui tambem: tenant:audit-models so enxerga trait direto (o escopo vem de CatalogoModel)

    protected $table = 'catalogo_regras';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['condicao' => 'array', 'validar_norma' => 'boolean']);
    }
}
