<?php

namespace App\Relacionamento\Models;

use App\Models\Prospect;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Interesse de uma empresa (Prospect ou SalesLead) por um tema. Global. */
class RelInteresse extends Model
{
    use HasUuids;

    public const TEMAS = ['Financeiro', 'Fiscal', 'Gestão', 'Operação', 'IA', 'Tributário', 'Manutenção', 'Comercial', 'Locação'];

    protected $table = 'rel_interesses';

    protected $guarded = [];

    public static function coluna(Model $dono): string
    {
        return $dono instanceof Prospect ? 'prospect_id' : 'sales_lead_id';
    }

    /** @return list<string> */
    public static function marcados(Model $dono): array
    {
        return static::where(static::coluna($dono), $dono->getKey())->orderBy('interesse')->pluck('interesse')->all();
    }

    /** Define o conjunto de interesses da empresa (so' temas validos). */
    public static function definir(Model $dono, array $temas): void
    {
        $col = static::coluna($dono);
        $temas = array_values(array_intersect(self::TEMAS, $temas));

        static::where($col, $dono->getKey())->whereNotIn('interesse', $temas)->delete();
        foreach ($temas as $tema) {
            static::firstOrCreate([$col => $dono->getKey(), 'interesse' => $tema]);
        }
    }
}
