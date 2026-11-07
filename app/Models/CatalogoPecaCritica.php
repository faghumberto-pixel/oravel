<?php

namespace App\Models;

class CatalogoPecaCritica extends CatalogoModel
{
    protected $table = 'catalogo_pecas_criticas';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['compatibilidade' => 'array']);
    }
}
