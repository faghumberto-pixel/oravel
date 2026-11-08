<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Controle por tenant: qual versao do template ja foi instalada (a fase 6 usa para detectar "atualizacao disponivel"). */
class CatalogoInstalacao extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'catalogo_instalacoes';

    protected $guarded = [];

    protected $casts = ['instalado_em' => 'datetime', 'versao_instalada' => 'integer'];
}
