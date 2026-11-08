<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogoRequisito extends CatalogoModel
{
    use BelongsToTenant; // declarado aqui tambem: tenant:audit-models so enxerga trait direto (o escopo vem de CatalogoModel)

    protected $table = 'catalogo_requisitos';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['gera_os' => 'boolean', 'validar_norma' => 'boolean', 'parametros' => 'array']);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(CatalogoItem::class, 'item_id');
    }
}
