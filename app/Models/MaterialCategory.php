<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class MaterialCategory extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_material_categories";
    protected static ?string $saasPermissionSlug = "categoria_material";
    protected static ?string $saasModuleLabel = "Categorias";

    use BelongsToTenant, SoftDeletes, HasUuids;

    protected $table = 'material_categories';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'tenant_id'
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Relacionamento com os Materiais vinculados a esta categoria
     * AJUSTADO: Chave estrangeira alterada para o padrão 'material_category_id'
     */
    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'material_category_id');
    }
}