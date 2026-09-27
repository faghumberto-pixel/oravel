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

    // Legado (antes de 2026-09-27) -- generico demais (nao distinguia pausa
    // de almoco). Mantido só pra exibir registros antigos, não usado no
    // app novo, que grava inicio/fim de pausa ou almoco separadamente.
    public const TIPO_INICIO_INTERVALO = 'inicio_intervalo';

    public const TIPO_FIM_INTERVALO = 'fim_intervalo';

    // Pausa pode ser batida mais de uma vez no dia (ex: manhã e tarde) --
    // por isso não existe "pausa 1"/"pausa 2" como tipos distintos, cada
    // par inicio/fim_pausa e' so' mais uma ocorrencia na linha do tempo.
    public const TIPO_INICIO_PAUSA = 'inicio_pausa';

    public const TIPO_FIM_PAUSA = 'fim_pausa';

    public const TIPO_INICIO_ALMOCO = 'inicio_almoco';

    public const TIPO_FIM_ALMOCO = 'fim_almoco';

    // Tipos que reduzem a jornada trabalhada (usados por
    // App\Services\TimeClockHoursCalculator).
    public const TIPOS_PAUSA = [
        self::TIPO_INICIO_INTERVALO, self::TIPO_FIM_INTERVALO,
        self::TIPO_INICIO_PAUSA, self::TIPO_FIM_PAUSA,
        self::TIPO_INICIO_ALMOCO, self::TIPO_FIM_ALMOCO,
    ];

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
            self::TIPO_INICIO_PAUSA => 'Início da Pausa',
            self::TIPO_FIM_PAUSA => 'Fim da Pausa',
            self::TIPO_INICIO_ALMOCO => 'Início do Almoço',
            self::TIPO_FIM_ALMOCO => 'Fim do Almoço',
            self::TIPO_SAIDA => 'Saída',
            // Legado, ver comentário nas constantes acima.
            self::TIPO_INICIO_INTERVALO => 'Início do Intervalo',
            self::TIPO_FIM_INTERVALO => 'Fim do Intervalo',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
