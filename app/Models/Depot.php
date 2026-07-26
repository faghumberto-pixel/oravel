<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'internal_unit_id',
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

    /**
     * Unidade (matriz/filial) da qual este Depot foi gerado -- ver
     * InternalUnit::syncDepot(), chamado sempre que a unidade e' salva com
     * coordenadas. Nulo pra Depot avulso, cadastrado direto sem unidade.
     */
    public function internalUnit(): BelongsTo
    {
        return $this->belongsTo(InternalUnit::class);
    }

    /**
     * Cria ou atualiza o Depot vinculado a uma InternalUnit -- chamado por
     * InternalUnit::syncDepot() toda vez que a unidade e' salva com
     * latitude/longitude preenchidas (via CEP), pra virar automaticamente
     * uma origem valida pra RouteOptimizationService/LogisticsRouteAnalysisService
     * sem exigir um cadastro de Depot duplicado e manual.
     */
    public static function syncFromInternalUnit(InternalUnit $unit): ?self
    {
        if ($unit->latitude === null || $unit->longitude === null) {
            return null;
        }

        return static::updateOrCreate(
            ['internal_unit_id' => $unit->id],
            [
                'tenant_id' => $unit->tenant_id,
                'name' => $unit->name,
                'address' => $unit->address,
                'city' => $unit->city,
                'state' => $unit->state,
                'zip_code' => $unit->cep,
                'latitude' => $unit->latitude,
                'longitude' => $unit->longitude,
            ]
        );
    }
}
