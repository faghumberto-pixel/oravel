<?php

namespace App\Models;

class CatalogoPreventiva extends CatalogoModel
{
    protected $table = 'catalogo_preventivas';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['parametros' => 'array']);
    }
}
