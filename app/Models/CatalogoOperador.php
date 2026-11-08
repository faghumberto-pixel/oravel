<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;

class CatalogoOperador extends CatalogoModel
{
    use BelongsToTenant; // declarado aqui tambem: tenant:audit-models so enxerga trait direto (o escopo vem de CatalogoModel)

    protected $table = 'catalogo_operadores';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['obrigatorio' => 'boolean', 'validar_norma' => 'boolean']);
    }
}
