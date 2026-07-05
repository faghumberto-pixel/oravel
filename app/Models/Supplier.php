<?php

namespace App\Models;

use App\Models\Concerns\HasSaaSMetadata;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use BelongsToTenant, HasFactory, HasSaaSMetadata, HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_suppliers';

    protected static ?string $saasPermissionSlug = 'fornecedor';

    protected static ?string $saasModuleLabel = 'Fornecedores';

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
