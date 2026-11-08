<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Base dos itens do catalogo de equipamentos (G1). Fonte da verdade e o banco, por tenant; os YAML em
 * database/seeders/catalog sao so o template inicial. Metadados padrao em todo item: origem (sistema|tenant),
 * slug/slug_template, versao_template, personalizado, ativo, ordem. Filhos nao sao modulos SaaS proprios:
 * herdam a chave/permissao de CatalogoItem ('tabela_catalogo_equipamentos').
 */
abstract class CatalogoModel extends Model
{
    use BelongsToTenant;
    use HasUuids;
    use SoftDeletes;

    public const ORIGEM_SISTEMA = 'sistema';

    public const ORIGEM_TENANT = 'tenant';

    protected $guarded = [];

    protected $casts = [
        'ativo' => 'boolean',
        'personalizado' => 'boolean',
        'ordem' => 'integer',
        'versao_template' => 'integer',
    ];

    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where($query->getModel()->getTable().'.ativo', true);
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'grupo_id');
    }

    /** Item usado por algum ativo/checklist/OS? Nesse caso so pode ser arquivado (regra do Bloco 1); a fase 2 amplia a checagem. */
    public function temHistoricoDeUso(): bool
    {
        return false;
    }
}
