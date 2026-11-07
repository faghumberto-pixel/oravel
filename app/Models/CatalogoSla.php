<?php

namespace App\Models;

class CatalogoSla extends CatalogoModel
{
    protected $table = 'catalogo_sla';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['pausa_por' => 'array']);
    }
}
