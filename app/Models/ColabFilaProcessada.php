<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Registro das ações que o celular enviou pela fila offline do app do colaborador (idempotência + trilha). */
class ColabFilaProcessada extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $table = 'colab_fila_processada';

    protected $guarded = [];
}
