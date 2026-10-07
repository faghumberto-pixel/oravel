<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Motorista titular de um veículo, num período. Sem data de fim = titular atual. */
class FrotaVinculoMotorista extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_vinculos_motorista';

    protected static ?string $saasPermissionSlug = 'vinculo_motorista_frota';

    protected static ?string $saasModuleLabel = 'Motorista Titular da Frota';

    protected $table = 'frota_vinculos_motorista';

    protected $fillable = ['tenant_id', 'ativo_id', 'motorista_id', 'inicio', 'fim', 'observacoes', 'registrado_por'];

    protected $casts = ['inicio' => 'date', 'fim' => 'date'];

    public function vigente(): bool
    {
        return $this->fim === null;
    }

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(FleetDriver::class, 'motorista_id');
    }
}
