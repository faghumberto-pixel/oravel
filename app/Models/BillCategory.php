<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillCategory extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_bill_categories";
    protected static ?string $saasPermissionSlug = "categoria_conta";
    protected static ?string $saasModuleLabel = "Categorias de Conta";

    use HasUuids;
    use \App\Models\Traits\BelongsToTenant;

    // Vincula à tabela real criada com sucesso no seu banco de dados
    protected $table = 'bill_categories';

    protected $fillable = [
        'id',
        'name',
        'tenant_id'
    ];

    /**
     * Isolamento Multi-tenant do Oravel
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}