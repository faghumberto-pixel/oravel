<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasSaaSMetadata;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de ponto do colaborador de campo. recorded_at e' sempre a hora
 * do SERVIDOR no momento em que o sync chega -- device_recorded_at (hora
 * local do aparelho) e' guardado so' como evidencia bruta, nunca usado pra
 * calculo de jornada, pra evitar fraude de relogio do dispositivo.
 */
class TimeClock extends Model
{
    use BelongsToTenant;
    use HasSaaSMetadata;
    use HasUuids;

    protected static ?string $saasFeatureKey = 'tabela_time_clocks';

    protected static ?string $saasPermissionSlug = 'ponto';

    protected static ?string $saasModuleLabel = 'Ponto Eletrônico';

    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_SAIDA = 'saida';

    public const TIPO_INICIO_INTERVALO = 'inicio_intervalo';

    public const TIPO_FIM_INTERVALO = 'fim_intervalo';

    public const SYNC_PENDING = 'pending';

    public const SYNC_SYNCED = 'synced';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'client_uuid',
        'tipo',
        'recorded_at',
        'device_recorded_at',
        'latitude',
        'longitude',
        'sync_status',
        'synced_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'device_recorded_at' => 'datetime',
        'synced_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public static function tipoLabels(): array
    {
        return [
            self::TIPO_ENTRADA => 'Entrada',
            self::TIPO_SAIDA => 'Saída',
            self::TIPO_INICIO_INTERVALO => 'Início do Intervalo',
            self::TIPO_FIM_INTERVALO => 'Fim do Intervalo',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
