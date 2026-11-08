<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AssetCategory extends Model
{
    // Isolamento multi-tenant canonico: global scope de leitura + carimbo de tenant_id na escrita (Auth-based).
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;
    // G1: grupo arquivado (soft delete) some das listas mas continua valendo para o historico dos ativos.
    use SoftDeletes;

    protected static ?string $saasFeatureKey = "tabela_asset_categories";
    protected static ?string $saasPermissionSlug = "categoria_ativo";
    protected static ?string $saasModuleLabel = "Categorias de Ativo";

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        // G1 -- perfil do grupo e metadados do catalogo (is_active = "ativo")
        'description', 'is_active', 'medidor', 'criticidade_padrao', 'exige_operador',
        'origem', 'slug_template', 'versao_template', 'personalizado', 'ordem',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'exige_operador' => 'boolean',
        'personalizado' => 'boolean',
    ];

    protected static function booted(): void
    {
        // O tenant_id e carimbado pela trait BelongsToTenant (no creating). Aqui apenas geramos o slug.
        // G1: editar um grupo vindo do template o marca como personalizado (nunca mais alterado automaticamente).
        static::updating(function (self $model) {
            if ($model->origem === 'sistema' && $model->isDirty(['name', 'description', 'medidor', 'criticidade_padrao', 'exige_operador', 'is_active', 'deleted_at'])) {
                $model->personalizado = true;
            }
        });

        static::creating(function (self $model) {
            if (empty($model->slug) && ! empty($model->name)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function atributos(): HasMany
    {
        return $this->hasMany(CatalogoAtributo::class, 'grupo_id')->orderBy('ordem');
    }

    public function requisitos(): HasMany
    {
        return $this->hasMany(CatalogoRequisito::class, 'grupo_id')->orderBy('ordem');
    }

    public function preventivas(): HasMany
    {
        return $this->hasMany(CatalogoPreventiva::class, 'grupo_id')->orderBy('ordem');
    }

    public function operadores(): HasMany
    {
        return $this->hasMany(CatalogoOperador::class, 'grupo_id')->orderBy('ordem');
    }

    public function pecasCriticas(): HasMany
    {
        return $this->hasMany(CatalogoPecaCritica::class, 'grupo_id')->orderBy('ordem');
    }

    public function slaPoliticas(): HasMany
    {
        return $this->hasMany(CatalogoSla::class, 'grupo_id')->orderBy('ordem');
    }

    public function regras(): HasMany
    {
        return $this->hasMany(CatalogoRegra::class, 'grupo_id')->orderBy('ordem');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'asset_category_id');
    }

    public function nrRequirements(): HasMany
    {
        return $this->hasMany(NrRequirementByCategory::class, 'asset_category_id');
    }
}
