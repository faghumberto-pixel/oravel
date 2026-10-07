<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rastreador GPS (Traccar) de um veículo: a distância percorrida é somada ao odômetro. */
class FrotaVinculoGps extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_frota_vinculos_gps';

    protected static ?string $saasPermissionSlug = 'vinculo_gps_frota';

    protected static ?string $saasModuleLabel = 'GPS dos Veículos da Frota';

    /** Sem sincronizar há mais de N horas = pendência (atenção). */
    public const ATRASO_HORAS = 48;

    /** Salto absurdo numa só sincronização: não é aplicado (provável erro do rastreador ou do servidor). */
    public const LIMITE_KM_POR_SINCRONIZACAO = 2000;

    protected $table = 'frota_vinculos_gps';

    protected $attributes = ['ativo' => true, 'km_acumulado' => 0];

    protected $fillable = ['tenant_id', 'ativo_id', 'traccar_device_id', 'identificador', 'ultima_sincronizacao', 'km_acumulado', 'ultimo_resultado', 'ativo', 'registrado_por'];

    protected $casts = ['ativo' => 'boolean', 'traccar_device_id' => 'integer', 'ultima_sincronizacao' => 'datetime', 'km_acumulado' => 'decimal:3'];

    public function veiculo(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'ativo_id');
    }
}
