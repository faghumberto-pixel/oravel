<?php

namespace App\Models;

class CatalogoRegra extends CatalogoModel
{
    protected $table = 'catalogo_regras';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['condicao' => 'array', 'validar_norma' => 'boolean']);
    }
}
