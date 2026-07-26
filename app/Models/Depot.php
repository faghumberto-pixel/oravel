<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Patio/deposito de onde a frota sai pra mobilizacoes -- ponto de origem
 * pro calculo de rota (App\Services\RouteOptimizationService). Um tenant
 * pode ter mais de um (matriz + filiais); is_default marca o usado quando
 * a mobilizacao nao tem um patio explicito.
 */
class Depot extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_depots';

    protected static ?string $saasPermissionSlug = 'deposito';

    protected static ?string $saasModuleLabel = 'Pátios/Depósitos';

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'city',
        'state',
        'zip_code',
        'latitude',
        'longitude',
        'is_default',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_default' => 'boolean',
    ];

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
