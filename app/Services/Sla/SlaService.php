<?php

namespace App\Services\Sla;

use App\Models\MaintenanceOrder;
use App\Models\SlaPolicy;
use App\Models\SlaSetting;
use App\Models\SlaTimer;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * F2: cronometros de SLA da O.S. (Resposta, Atendimento, Resolucao). 24x7 no MVP (horario comercial/feriados: fase seguinte).
 *
 * - iniciar(): ao abrir a OS, escolhe a politica (prioridade + tipo, o tipo especifico vence o geral) e cria os cronometros.
 *   Sem politica, o legado sla_target_minutes vira o relogio "Atendimento".
 * - avancar(): para os cronometros pelos eventos reais da OS e pausa/retoma pelo status interno (ex.: aguardando peca).
 * - recalcular(): mudou a prioridade -> refaz os prazos dos cronometros ABERTOS a partir do inicio de cada um.
 */
class SlaService
{
    public const WARN_PADRAO = 70;

    public const ESCALONAR_PADRAO = 150;

    public const PAUSA_PADRAO = ['aguardando_peca', 'aguardando_peca_canibalizado'];

    public const STATUS_PAUSA_OPCOES = [
        'aguardando_diagnostico' => 'Aguardando diagnóstico',
        'aguardando_peca' => 'Aguardando peça',
        'aguardando_peca_canibalizado' => 'Aguardando peças / canibalizado',
        'pendencia' => 'Pendência',
    ];

    /** @var array<string, SlaSetting> */
    private static array $settings = [];

    public static function esquecer(): void
    {
        self::$settings = [];
        SlaCalendar::esquecer();
    }

    public static function settings(string $tenantId): SlaSetting
    {
        return self::$settings[$tenantId] ??= SlaSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->first()
            ?? new SlaSetting(['tenant_id' => $tenantId, 'pause_statuses' => self::PAUSA_PADRAO, 'warn_pct' => self::WARN_PADRAO, 'escalate_pct' => self::ESCALONAR_PADRAO, 'calendar_mode' => SlaCalendar::MODO_24X7, 'timezone' => 'America/Sao_Paulo']);
    }

    public static function salvarSettings(string $tenantId, array $statusQuePausam, int $avisarEm, int $escalonarEm = self::ESCALONAR_PADRAO, ?array $calendario = null): void
    {
        $dados = ['pause_statuses' => array_values($statusQuePausam), 'warn_pct' => $avisarEm, 'escalate_pct' => $escalonarEm];

        if ($calendario !== null) {
            $dados += ['calendar_mode' => $calendario['modo'] ?? SlaCalendar::MODO_24X7, 'timezone' => $calendario['timezone'] ?? 'America/Sao_Paulo', 'business_hours' => $calendario['horarios'] ?? null];
        }

        SlaSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $tenantId], $dados);
        self::esquecer();
    }

    public static function politicaPara(MaintenanceOrder $os): ?SlaPolicy
    {
        if (blank($os->priority) || blank($os->tenant_id)) {
            return null;
        }

        return SlaPolicy::withoutGlobalScopes()
            ->where('tenant_id', $os->tenant_id)->where('is_active', true)->where('priority_code', $os->priority)
            ->where(fn ($q) => $q->where('maintenance_type', $os->maintenance_type)->orWhereNull('maintenance_type'))
            ->orderByRaw('maintenance_type is null')
            ->first();
    }

    /** Cria os cronometros de uma OS recem-aberta e ja aplica o que ela tem de fato (OS criada ja iniciada/concluida). */
    public static function iniciar(MaintenanceOrder $os): void
    {
        $politica = self::politicaPara($os);
        $minutos = $politica?->minutosPorRelogio() ?? [];

        $legado = false;
        if (! isset($minutos[SlaTimer::SERVICE]) && $os->sla_target_minutes) {
            $minutos[SlaTimer::SERVICE] = (int) $os->sla_target_minutes; // legado digitado a mao
            $legado = true;
        }

        foreach ($minutos as $relogio => $min) {
            self::criar($os, $relogio, $min, ($legado && $relogio === SlaTimer::SERVICE) ? null : $politica);
        }

        self::avancar($os);
    }

    /** sla_target_minutes mudou (assistente de Troca, formulario de Emergencia): o relogio Atendimento acompanha. */
    public static function sincronizarLegado(MaintenanceOrder $os): void
    {
        if (! $os->sla_target_minutes) {
            return;
        }

        $timer = self::timers($os)->get(SlaTimer::SERVICE);

        if (! $timer) {
            self::criar($os, SlaTimer::SERVICE, (int) $os->sla_target_minutes, null);

            return;
        }

        if ($timer->aberto() && $timer->policy_id === null && $timer->target_minutes !== (int) $os->sla_target_minutes) {
            $timer->update(['target_minutes' => (int) $os->sla_target_minutes, 'due_at' => self::prazo($timer->started_at, (int) $os->sla_target_minutes, $timer->paused_seconds, $timer)]);
        }
    }

    /** Para/pausa/retoma os cronometros abertos conforme o estado atual da OS. */
    public static function avancar(MaintenanceOrder $os): void
    {
        $abertos = self::timers($os)->filter(fn (SlaTimer $t) => $t->aberto());

        if ($abertos->isEmpty()) {
            return;
        }

        $cancelada = in_array($os->status, ['Cancelada', 'Cancelado'], true);
        $pausar = in_array($os->internal_status, self::settings((string) $os->tenant_id)->pause_statuses ?? [], true);

        foreach ($abertos as $t) {
            if ($cancelada) {
                self::parar($t, now(), 'cancelled');

                continue;
            }

            $quando = match ($t->clock) {
                SlaTimer::RESPONSE => $os->first_response_at ?? $os->started_at ?? $os->finished_at,
                SlaTimer::SERVICE => $os->started_at ?? $os->finished_at,
                default => $os->finished_at,
            };

            if ($quando) {
                self::parar($t, $quando);
            } elseif ($pausar && ! $t->paused_at) {
                $t->update(['paused_at' => now()]);
            } elseif (! $pausar && $t->paused_at) {
                self::retomar($t, now());
            }
        }
    }

    /** Prioridade mudou: refaz os prazos dos cronometros abertos pela politica da nova prioridade. */
    public static function recalcular(MaintenanceOrder $os): void
    {
        $politica = self::politicaPara($os);
        $minutos = $politica?->minutosPorRelogio() ?? [];
        $timers = self::timers($os);

        foreach ($timers as $relogio => $t) {
            if (! $t->aberto() || $t->policy_id === null) {
                continue; // fechado nao muda; legado digitado a mao segue o campo sla_target_minutes
            }

            if (isset($minutos[$relogio])) {
                $t->update(['policy_id' => $politica->id, 'target_minutes' => $minutos[$relogio], 'due_at' => self::prazo($t->started_at, $minutos[$relogio], $t->paused_seconds, $t), 'warned_at' => null, 'breached_at' => null, 'escalated_at' => null]);
            } else {
                $t->delete();
            }
        }

        foreach ($minutos as $relogio => $min) {
            if (! $timers->has($relogio)) {
                self::criar($os, $relogio, $min, $politica);
            }
        }

        self::avancar($os->refresh());
    }

    /**
     * Pior cronometro aberto da OS (vencido > em risco > no prazo > pausado) para telas e apps; nulo se nao ha cronometro aberto.
     *
     * @return array{texto: string, cor: string, estado: string, relogio: string, minutos: int, prazo: CarbonInterface}|null
     */
    public static function resumoDaOs(MaintenanceOrder $os): ?array
    {
        $abertos = $os->slaTimers->filter(fn (SlaTimer $t) => $t->aberto());

        if ($abertos->isEmpty()) {
            return null;
        }

        $avisarEm = self::settings((string) $os->tenant_id)->warn_pct;
        $ordem = ['vencido' => 0, 'em_risco' => 1, 'no_prazo' => 2, 'pausado' => 3];
        $pior = $abertos->sortBy(fn (SlaTimer $t) => [$ordem[$t->estado($avisarEm)] ?? 9, $t->prazoEfetivo()->getTimestamp()])->first();
        $estado = $pior->estado($avisarEm);
        $restante = $pior->minutosRestantes();

        $texto = match ($estado) {
            'vencido' => $pior->label().' · vencido há '.self::formatarMinutos(abs($restante)),
            'pausado' => $pior->label().' · pausado',
            default => $pior->label().' · '.self::formatarMinutos($restante).' restantes',
        };

        return ['texto' => $texto, 'cor' => SlaTimer::corDoEstado($estado), 'estado' => $estado, 'relogio' => $pior->label(), 'minutos' => $restante, 'prazo' => $pior->prazoEfetivo()];
    }

    /** @return Collection<string, SlaTimer> indexado pelo relogio */
    public static function timers(MaintenanceOrder $os): Collection
    {
        return SlaTimer::withoutGlobalScopes()->where('subject_type', MaintenanceOrder::class)->where('subject_id', $os->id)->get()->keyBy('clock');
    }

    /**
     * Indicadores dos ultimos N dias: % cumprido por relogio (cronometros fechados, sem cancelados) e abertos por estado.
     *
     * @return array{relogios: array<string, array{total: int, cumpridos: int, pct: ?int}>, abertos: array<string, int>}
     */
    public static function resumo(string $tenantId, int $dias = 30): array
    {
        $avisarEm = self::settings($tenantId)->warn_pct;
        $base = SlaTimer::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('subject_type', MaintenanceOrder::class);

        $relogios = [];
        foreach (array_keys(SlaTimer::CLOCKS) as $relogio) {
            $fechados = (clone $base)->where('clock', $relogio)->whereNotNull('stopped_at')->where('result', '!=', 'cancelled')->where('stopped_at', '>=', now()->subDays($dias))->pluck('result');
            $total = $fechados->count();
            $cumpridos = $fechados->filter(fn ($r) => $r === 'met')->count();
            $relogios[$relogio] = ['total' => $total, 'cumpridos' => $cumpridos, 'pct' => $total > 0 ? (int) round($cumpridos / $total * 100) : null];
        }

        $abertos = ['no_prazo' => 0, 'em_risco' => 0, 'vencido' => 0, 'pausado' => 0];
        foreach ((clone $base)->whereNull('stopped_at')->get() as $t) {
            $abertos[$t->estado($avisarEm)]++;
        }

        return ['relogios' => $relogios, 'abertos' => $abertos];
    }

    public static function formatarMinutos(?int $minutos): string
    {
        if ($minutos === null) {
            return '—';
        }

        $sinal = $minutos < 0 ? '-' : '';
        $m = abs($minutos);
        $h = intdiv($m, 60);
        $r = $m % 60;

        return $sinal.($h > 0 ? "{$h}h".($r > 0 ? " {$r}min" : '') : "{$r}min");
    }

    private static function criar(MaintenanceOrder $os, string $relogio, int $minutos, ?SlaPolicy $politica): void
    {
        $inicio = $os->created_at ?? now();
        $comercial = SlaCalendar::doTenant((string) $os->tenant_id)->ehComercial();
        $modo = $comercial ? SlaCalendar::MODO_COMERCIAL : SlaCalendar::MODO_24X7;
        $calendario = $comercial ? SlaCalendar::doTenant((string) $os->tenant_id) : SlaCalendar::vinte4x7();

        SlaTimer::withoutGlobalScopes()->firstOrCreate(
            ['subject_type' => MaintenanceOrder::class, 'subject_id' => $os->id, 'clock' => $relogio],
            ['tenant_id' => $os->tenant_id, 'policy_id' => $politica?->id, 'started_at' => $inicio, 'target_minutes' => $minutos, 'due_at' => $calendario->somar($inicio, $minutos), 'calendar_mode' => $modo],
        );
    }

    /** Prazo = inicio + meta + o que ficou pausado, tudo no calendario do cronometro. */
    private static function prazo(CarbonInterface $inicio, int $minutos, int $pausadoSegundos, SlaTimer $t): CarbonInterface
    {
        return $t->calendario()->somar($inicio, $minutos + $pausadoSegundos / 60);
    }

    private static function retomar(SlaTimer $t, CarbonInterface $quando): void
    {
        $cal = $t->calendario();
        $pausaMinutos = $cal->entre($t->paused_at, $quando);

        $t->update(['paused_at' => null, 'paused_seconds' => $t->paused_seconds + (int) round($pausaMinutos * 60), 'due_at' => $cal->somar($t->due_at, $pausaMinutos)]);
    }

    private static function parar(SlaTimer $t, CarbonInterface $quando, ?string $resultado = null): void
    {
        if ($t->paused_at) {
            self::retomar($t, $quando);
            $t->refresh();
        }

        $gasto = (int) round($t->minutosGastos($quando));

        $t->update(['stopped_at' => $quando, 'spent_minutes' => $gasto, 'result' => $resultado ?? ($quando->lessThanOrEqualTo($t->due_at) ? 'met' : 'breached')]);
    }
}
