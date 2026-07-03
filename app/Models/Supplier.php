<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Supplier extends Model
{
    use HasUuids, BelongsToTenant, HasSaaSMetadata;

    protected static ?string $saasFeatureKey = "tabela_suppliers";
    protected static ?string $saasPermissionSlug = "fornecedor";
    protected static ?string $saasModuleLabel = "Fornecedores";

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'address',
        'city',
        'state',
    ];
}
