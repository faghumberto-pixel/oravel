<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Modelo padronizado de ativo (catálogo do tenant). O ativo guarda o NOME em assets.modelo. */
class AssetModel extends Model
{
    use BelongsToTenant;
    use HasUuids;

    protected $fillable = ['tenant_id', 'name', 'fabricante'];

    /** Normaliza espaços ("  Actros   2651 " -> "Actros 2651"). */
    public static function clean(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', $name));
    }
}
