<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Model;

class AsaasPaymentSync extends Model
{
    use HasSaaSMetadata, BelongsToTenant;

    protected static ?string $saasFeatureKey = 'tabela_asaas_sync';

    protected static ?string $saasPermissionSlug = 'asaas_sync';

    protected static ?string $saasModuleLabel = 'Sincronização Asaas';

    protected $fillable = [];
}
