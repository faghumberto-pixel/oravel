<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Sla\SlaCalendar;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Cronômetro genérico de SLA: um por (registro, relógio). Em 24x7 o prazo é started_at + target + pausas.
 * Parado, guarda o resultado (met/breached/cancelled); aberto, o estado é calculado na hora.
 */
class SlaTimer extends Model
{
    use BelongsToTenant;
    use HasUuids;

    public const RESPONSE = 'response';

    public const SERVICE = 'service';

    public const RESOLUTION = 'resolution';

    public const CLOCKS = [self::RESPONSE => 'Resposta', self::SERVICE => 'Atendimento', self::RESOLUTION => 'Resolução'];

    protected $fillable = ['tenant_id', 'subject_type', 'subject_id', 'clock', 'policy_id', 'started_at', 'target_minutes', 'due_at', 'paused_at', 'paused_seconds', 'stopped_at', 'result', 'warned_at', 'breached_at', 'escalated_at', 'calendar_mode', 'spent_minutes'];

    protected $casts = [
        'started_at' => 'datetime',
        'due_at' => 'datetime',
        'paused_at' => 'datetime',
        'stopped_at' => 'datetime',
        'warned_at' => 'datetime',
        'breached_at' => 'datetime',
        'escalated_at' => 'datetime',
        'spent_minutes' => 'integer',
        'target_minutes' => 'integer',
        'paused_seconds' => 'integer',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function label(): string
    {
        return self::CLOCKS[$this->clock] ?? $this->clock;
    }

    public function aberto(): bool
    {
        return $this->stopped_at === null;
    }

    /** Calendário com que este cronômetro nasceu (mudar o calendário da empresa depois não mexe nele). */
    public function calendario(): SlaCalendar
    {
        return $this->calendar_mode === SlaCalendar::MODO_COMERCIAL
            ? SlaCalendar::doTenant((string) $this->tenant_id)
            : SlaCalendar::vinte4x7();
    }

    /** Prazo efetivo: enquanto pausado, o prazo "anda" junto com a pausa (no calendário do cronômetro). */
    public function prazoEfetivo(?CarbonInterface $agora = null): CarbonInterface
    {
        $agora ??= now();

        if ($this->aberto() && $this->paused_at) {
            $cal = $this->calendario();

            return $cal->somar($this->due_at, $cal->entre($this->paused_at, $agora));
        }

        return $this->due_at;
    }

    /** Minutos de calendário já gastos, sem contar as pausas. Parado, vale o valor gravado ao parar. */
    public function minutosGastos(?CarbonInterface $agora = null): float
    {
        if (! $this->aberto() && $this->spent_minutes !== null) {
            return (float) $this->spent_minutes;
        }

        $agora ??= now();
        $cal = $this->calendario();
        $minutos = $cal->entre($this->started_at, $this->stopped_at ?? $agora) - $this->paused_seconds / 60;

        if ($this->aberto() && $this->paused_at) {
            $minutos -= $cal->entre($this->paused_at, $agora);
        }

        return max(0, $minutos);
    }

    /** Minutos de calendário que faltam para o prazo (negativo = já passou). Só faz sentido para relógio aberto e não pausado. */
    public function minutosRestantes(?CarbonInterface $agora = null): int
    {
        $agora ??= now();
        $prazo = $this->prazoEfetivo($agora);
        $cal = $this->calendario();

        return (int) round($agora->lessThan($prazo) ? $cal->entre($agora, $prazo) : -$cal->entre($prazo, $agora));
    }

    /** Instante em que o cronômetro passa a "em risco" (no calendário dele): início + (meta × % de alerta + pausas). */
    public function inicioDoRisco(int $avisarEm): CarbonInterface
    {
        return $this->calendario()->somar($this->started_at, $this->target_minutes * $avisarEm / 100 + $this->paused_seconds / 60);
    }

    /** cumprido | estourado | cancelado | pausado | vencido | em_risco | no_prazo */
    public function estado(int $avisarEm = 70, ?CarbonInterface $agora = null): string
    {
        $agora ??= now();

        if (! $this->aberto()) {
            return match ($this->result) {
                'met' => 'cumprido',
                'cancelled' => 'cancelado',
                default => 'estourado',
            };
        }

        if ($this->paused_at) {
            return 'pausado';
        }

        if ($agora->greaterThanOrEqualTo($this->due_at)) {
            return 'vencido';
        }

        return $this->minutosGastos($agora) >= $this->target_minutes * $avisarEm / 100 ? 'em_risco' : 'no_prazo';
    }

    public static function corDoEstado(string $estado): string
    {
        return match ($estado) {
            'vencido', 'estourado' => 'danger',
            'em_risco' => 'warning',
            'no_prazo', 'cumprido' => 'success',
            default => 'gray',
        };
    }

    public static function rotuloDoEstado(string $estado): string
    {
        return [
            'no_prazo' => 'No prazo', 'em_risco' => 'Em risco', 'vencido' => 'Vencido', 'pausado' => 'Pausado',
            'cumprido' => 'Cumprido', 'estourado' => 'Estourado', 'cancelado' => 'Cancelado',
        ][$estado] ?? $estado;
    }
}
