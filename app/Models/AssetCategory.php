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
        static::creating(function (self $model) {
            if (empty($model->slug) && ! empty($model->name)) {
                $model->slug = Str::slug($model->name);
            }
        });
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
