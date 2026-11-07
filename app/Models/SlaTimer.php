<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
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

    protected $fillable = ['tenant_id', 'subject_type', 'subject_id', 'clock', 'policy_id', 'started_at', 'target_minutes', 'due_at', 'paused_at', 'paused_seconds', 'stopped_at', 'result'];

    protected $casts = [
        'started_at' => 'datetime',
        'due_at' => 'datetime',
        'paused_at' => 'datetime',
        'stopped_at' => 'datetime',
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

    /** Prazo efetivo: enquanto pausado, o prazo "anda" junto com a pausa. */
    public function prazoEfetivo(?CarbonInterface $agora = null): CarbonInterface
    {
        $agora ??= now();

        if ($this->aberto() && $this->paused_at) {
            return $this->due_at->copy()->addSeconds(max(0, (int) $this->paused_at->diffInSeconds($agora)));
        }

        return $this->due_at;
    }

    /** Minutos de relógio corrido já gastos, sem contar as pausas. */
    public function minutosGastos(?CarbonInterface $agora = null): float
    {
        $agora ??= now();
        $fim = $this->stopped_at ?? $agora;
        $segundos = max(0, (int) $this->started_at->diffInSeconds($fim)) - $this->paused_seconds;

        if ($this->aberto() && $this->paused_at) {
            $segundos -= max(0, (int) $this->paused_at->diffInSeconds($agora));
        }

        return max(0, $segundos) / 60;
    }

    /** Minutos que faltam para o prazo (negativo = já passou). Só faz sentido para relógio aberto e não pausado. */
    public function minutosRestantes(?CarbonInterface $agora = null): int
    {
        $agora ??= now();

        return (int) round(($this->prazoEfetivo($agora)->getTimestamp() - $agora->getTimestamp()) / 60);
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
