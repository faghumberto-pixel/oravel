<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_suppliers";
    protected static ?string $saasPermissionSlug = "fornecedor";
    protected static ?string $saasModuleLabel = "Fornecedores";

    // Todos os Traits declarados de forma estrita e correta dentro do corpo do modelo
    use HasUuids;
    use \App\Models\Traits\BelongsToTenant;
}