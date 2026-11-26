<?php

namespace App\Domain\Intelligence\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Uma fotografia de suprimentos (uma importação da planilha de Suprimentos): indicadores, nota e listas de atenção já calculados. */
class SuprimentosCarga extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'intelligence_suprimentos_cargas';

    protected static ?string $saasFeatureKey = 'tabela_intelligence_suprimentos';

    protected static ?string $saasPermissionSlug = 'intelligence_suprimentos';

    protected static ?string $saasModuleLabel = 'Intelligence - Suprimentos';

    protected $fillable = ['tenant_id', 'user_id', 'origem', 'arquivo', 'data_referencia', 'periodo_dias', 'hash', 'total_itens', 'total_compras',
        'total_movimentos', 'indicadores', 'diagnostico', 'listas', 'avisos'];

    protected $casts = ['data_referencia' => 'date', 'indicadores' => 'array', 'diagnostico' => 'array', 'listas' => 'array', 'avisos' => 'array', 'created_at' => 'datetime'];

    public function itens(): HasMany
    {
        return $this->hasMany(SuprimentosItem::class, 'carga_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(SuprimentosCompra::class, 'carga_id');
    }

    public function movimentos(): HasMany
    {
        return $this->hasMany(SuprimentosMovimento::class, 'carga_id');
    }
}
