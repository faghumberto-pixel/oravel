<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class FleetMaintenancePlan extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_fleet_maintenance_plans';

    protected static ?string $saasPermissionSlug = 'plano_manutencao_veiculo';

    protected static ?string $saasModuleLabel = 'Planos de Manutenção de Veículos';

    /**
     * Vocabulario real de tipo de servico -- usado no form do
     * FleetMaintenancePlanResource.
     */
    public const TIPO_OLEO = 'oleo';

    public const TIPO_PNEU = 'pneu';

    public const TIPO_LAVAGEM = 'lavagem';

    public const TIPO_REVISAO = 'revisao';

    public const TIPO_OUTRO = 'outro';

    protected $fillable = [
        'tenant_id',
        'fleet_vehicle_id',
        'tipo_servico',
        'intervalo_km',
        'intervalo_dias',
        'ultima_execucao_km',
        'ultima_execucao_data',
    ];

    protected $casts = [
        'ultima_execucao_km' => 'decimal:2',
        'ultima_execucao_data' => 'date',
    ];

    public function fleetVehicle(): BelongsTo
    {
        return $this->belongsTo(FleetVehicle::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(FleetMaintenanceHistory::class);
    }

    /**
     * Proxima execucao por tempo, calculada (nao armazenada) a partir da
     * ultima execucao + intervalo_dias. Null se o plano nao usa gatilho
     * por tempo ou nunca foi executado.
     */
    public function getProximaExecucaoDataAttribute(): ?Carbon
    {
        if (! $this->intervalo_dias || ! $this->ultima_execucao_data) {
            return null;
        }

        return $this->ultima_execucao_data->copy()->addDays($this->intervalo_dias);
    }

    /**
     * Proxima execucao por quilometragem, calculada a partir da ultima
     * execucao + intervalo_km. Null se o plano nao usa gatilho por km ou
     * nunca foi executado.
     */
    public function getProximaExecucaoKmAttribute(): ?float
    {
        if (! $this->intervalo_km || $this->ultima_execucao_km === null) {
            return null;
        }

        return (float) $this->ultima_execucao_km + $this->intervalo_km;
    }

    /**
     * Vencido por tempo (passou da data calculada) ou por km (o km_atual
     * do veiculo ja alcancou/ultrapassou a proxima execucao prevista).
     */
    public function isVencido(): bool
    {
        if ($this->proxima_execucao_data && $this->proxima_execucao_data->isPast()) {
            return true;
        }

        if ($this->proxima_execucao_km !== null && $this->fleetVehicle) {
            return (float) $this->fleetVehicle->km_atual >= $this->proxima_execucao_km;
        }

        return false;
    }

    public function isProximoVencimento(int $diasAntecedencia = 7, float $kmAntecedencia = 500): bool
    {
        if ($this->isVencido()) {
            return false;
        }

        if ($this->proxima_execucao_data
            && $this->proxima_execucao_data->lessThanOrEqualTo(now()->addDays($diasAntecedencia))) {
            return true;
        }

        if ($this->proxima_execucao_km !== null && $this->fleetVehicle) {
            $faltam = $this->proxima_execucao_km - (float) $this->fleetVehicle->km_atual;

            return $faltam <= $kmAntecedencia;
        }

        return false;
    }
}
