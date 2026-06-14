<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_materials";
    protected static ?string $saasPermissionSlug = "material";
    protected static ?string $saasModuleLabel = "Materiais";

    // Todos os Traits declarados de forma estrita e correta dentro do corpo do modelo
    use HasUuids;
    use \App\Models\Traits\BelongsToTenant;

    protected $fillable = [
        'sku',
        'name',
        'unit_cost',
        'current_stock',
        'min_stock',
        'max_stock',
        'ncm',
        'price',
        'tenant_id',
        
        // 🚀 ATRIBUTOS GERENCIAIS E DE INFRAESTRUTURA INTEGRADOS:
        'material_category_id', // Chave unificada com a migration anterior
        'supplier_id',          // Vínculo com o Fornecedor Homologado
        'brand_name',           // Marca do Componente (Análise de quebras)
        'supplier_type'         // Tipo/Segmentação do Fornecedor (Fabricante, Distribuidor, etc)
    ];

    /**
     * Relação exigida pelo Filament para o isolamento de dados (Multi-tenancy).
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Relação com a categoria de materiais.
     * AJUSTADO: Aponta de forma explícita para 'material_category_id' conforme o banco físico.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    /**
     * 🚀 NOVO VÍNCULO GERENCIAL: Relação com o Fornecedor Homologado.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}