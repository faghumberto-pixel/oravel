<?php

namespace App\Models;

class CatalogoOperador extends CatalogoModel
{
    protected $table = 'catalogo_operadores';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['obrigatorio' => 'boolean', 'validar_norma' => 'boolean']);
    }
}
