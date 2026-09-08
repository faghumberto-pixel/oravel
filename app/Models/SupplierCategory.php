<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Categoria de fornecimento (pecas, combustivel, servicos terceirizados
 * etc.) -- mesmo padrao de MaterialCategory/PartCategory.
 */
class SupplierCategory extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_supplier_categories';

    protected static ?string $saasPermissionSlug = 'categoria_fornecedor';

    protected static ?string $saasModuleLabel = 'Categorias de Fornecedor';

    protected $fillable = [
        'tenant_id',
        'name',
    ];

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_category_supplier');
    }
}
