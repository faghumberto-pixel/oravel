<?php

namespace App\Models;

class CatalogoAtributo extends CatalogoModel
{
    protected $table = 'catalogo_atributos';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['obrigatorio' => 'boolean', 'opcoes' => 'array']);
    }
}
