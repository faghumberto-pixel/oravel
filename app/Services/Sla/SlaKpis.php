<?php

namespace App\Services\Sla;

use App\Models\CriticalityLevel;
use App\Models\MaintenanceOrder;
use App\Models\PriorityLevel;
use App\Models\SlaTimer;
use App\Services\Prioridade\PrioridadeDaOs;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * F4: indicadores de SLA. Tudo calculado em SQL sobre `sla_timers` (cronometros FECHADOS e nao cancelados no periodo):
 * % cumprido, tempo medio gasto (sem as pausas) e, para os abertos, quantos estao vencidos agora.
 * Dimensoes: prioridade, criticidade do ativo (Matriz ABC), tipo de OS, tecnico e cliente.
 */
class SlaKpis
{
    public const DIMENSOES = ['prioridade', 'criticidade', 'tipo', 'tecnico', 'cliente'];

    /**
     * Visao geral: por relogio, % cumprido e tempo medio.
     *
     * @return array<string, array{total: int, cumpridos: int, pct: ?int, tempo_medio: ?int}>
     */
    public static function geral(string $tenantId, int $dias = 30): array
    {
        $linhas = self::fechados($tenantId, $dias)->selectRaw('t.clock, '.self::AGREGADOS)->groupBy('t.clock')->get()->keyBy('clock');
        $saida = [];

        foreach (array_keys(SlaTimer::CLOCKS) as $relogio) {
            $saida[$relogio] = self::linha($linhas->get($relogio));
        }

        return $saida;
    }

    /**
     * Quebra por dimensao para UM relogio. Ordenada do pior % para o melhor (quem mais precisa de atencao primeiro).
     *
     * @return array<int, array{chave: string, rotulo: string, total: int, cumpridos: int, pct: ?int, tempo_medio: ?int, vencidos: int}>
     */
    public static function porDimensao(string $tenantId, string $dimensao, int $dias = 30, string $relogio = SlaTimer::RESOLUTION): array
    {
        abort_unless(in_array($dimensao, self::DIMENSOES, true), 422);

        [$chave, $rotulo] = self::colunas($dimensao);

        $fechados = self::fechados($tenantId, $dias)->where('t.clock', $relogio)
            ->selectRaw("{$chave} as chave, max({$rotulo}) as rotulo, ".self::AGREGADOS)->groupByRaw($chave)->get();

        $vencidos = self::base($tenantId)->whereNull('t.stopped_at')->whereNull('t.paused_at')->where('t.clock', $relogio)->where('t.due_at', '<', now())
            ->selectRaw("{$chave} as chave, max({$rotulo}) as rotulo, count(*) as vencidos")->groupByRaw($chave)->get()->keyBy('chave');

        $linhas = [];
        foreach ($fechados as $f) {
            $linhas[(string) $f->chave] = self::linha($f) + ['chave' => (string) $f->chave, 'rotulo' => self::nome($dimensao, $tenantId, $f->chave, $f->rotulo), 'vencidos' => (int) ($vencidos->get($f->chave)->vencidos ?? 0)];
        }

        foreach ($vencidos as $chaveVencida => $v) {
            $linhas[(string) $chaveVencida] ??= self::linha(null) + ['chave' => (string) $chaveVencida, 'rotulo' => self::nome($dimensao, $tenantId, $chaveVencida, $v->rotulo), 'vencidos' => (int) $v->vencidos];
        }

        usort($linhas, fn ($a, $b) => [$a['pct'] ?? 101, -$a['vencidos'], $a['rotulo']] <=> [$b['pct'] ?? 101, -$b['vencidos'], $b['rotulo']]);

        return array_values($linhas);
    }

    /**
     * Linhas detalhadas para exportar (fechados no periodo + abertos agora), uma por cronometro.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function detalhe(string $tenantId, int $dias = 30)
    {
        PrioridadeDaOs::garantirPadrao($tenantId);
        PrioridadeDaOs::garantirPadrao($tenantId);
        $avisarEm = SlaService::settings($tenantId)->warn_pct;
        $prioridades = PriorityLevel::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('name', 'code');

        return self::base($tenantId)
            ->leftJoin('assets as a', 'a.id', '=', 'o.asset_id')
            ->where(fn ($q) => $q->whereNull('t.stopped_at')->orWhere('t.stopped_at', '>=', now()->subDays($dias)))
            ->orderBy('t.started_at')
            ->get(['o.os_number', 'o.maintenance_type', 'o.priority', 'u.name as tecnico', 'c.name as cliente', 'a.name as ativo', 't.clock', 't.target_minutes', 't.started_at', 't.stopped_at', 't.paused_at', 't.paused_seconds', 't.due_at', 't.result'])
            ->map(function ($r) use ($avisarEm, $prioridades) {
                $t = new SlaTimer((array) $r);
                $t->exists = false;
                $estado = $t->estado($avisarEm);

                return [
                    'os' => $r->os_number, 'tipo' => $r->maintenance_type, 'prioridade' => $prioridades[$r->priority] ?? $r->priority, 'tecnico' => $r->tecnico, 'cliente' => $r->cliente,
                    'ativo' => $r->ativo, 'relogio' => SlaTimer::CLOCKS[$r->clock] ?? $r->clock, 'meta_min' => $r->target_minutes, 'inicio' => $r->started_at,
                    'fim' => $r->stopped_at, 'gasto_min' => (int) round($t->minutosGastos()), 'situacao' => SlaTimer::rotuloDoEstado($estado),
                ];
            });
    }

    private const AGREGADOS = "count(*) as total, sum(case when t.result = 'met' then 1 else 0 end) as cumpridos,
        avg((extract(epoch from (t.stopped_at - t.started_at)) - t.paused_seconds) / 60.0) as tempo_medio";

    private static function base(string $tenantId): Builder
    {
        return DB::table('sla_timers as t')
            ->join('maintenance_orders as o', function ($j) {
                $j->on('o.id', '=', 't.subject_id')->where('t.subject_type', '=', MaintenanceOrder::class);
            })
            ->leftJoin('users as u', 'u.id', '=', 'o.technician_id')
            ->leftJoin('clients as c', 'c.id', '=', 'o.client_id')
            ->leftJoin('assets as ax', 'ax.id', '=', 'o.asset_id')
            ->leftJoin('abc_matrices as am', 'am.asset_id', '=', 'ax.id')
            ->where('t.tenant_id', $tenantId)->whereNull('o.deleted_at');
    }

    private static function fechados(string $tenantId, int $dias): Builder
    {
        return self::base($tenantId)->whereNotNull('t.stopped_at')->whereIn('t.result', ['met', 'breached'])->where('t.stopped_at', '>=', now()->subDays($dias));
    }

    /** @return array{0: string, 1: string} expressao da chave e do rotulo */
    private static function colunas(string $dimensao): array
    {
        return match ($dimensao) {
            'prioridade' => ['coalesce(o.priority, \'\')', 'o.priority'],
            'criticidade' => ['coalesce(am.nivel, \'\')', 'am.nivel'],
            'tipo' => ['coalesce(o.maintenance_type, \'\')', 'o.maintenance_type'],
            'tecnico' => ['coalesce(cast(o.technician_id as text), \'\')', 'u.name'],
            'cliente' => ['coalesce(cast(o.client_id as text), \'\')', 'c.name'],
        };
    }

    private static function nome(string $dimensao, string $tenantId, mixed $chave, mixed $rotulo): string
    {
        if ($chave === '' || $chave === null) {
            return match ($dimensao) {
                'tecnico' => 'Sem técnico', 'cliente' => 'Sem cliente', 'criticidade' => 'Sem classificação', default => 'Não informado',
            };
        }

        if ($dimensao === 'prioridade') {
            PrioridadeDaOs::garantirPadrao($tenantId);
        }

        if ($dimensao === 'prioridade') {
            PrioridadeDaOs::garantirPadrao($tenantId);
        }

        return match ($dimensao) {
            'prioridade' => PriorityLevel::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $chave)->value('name') ?? ucfirst((string) $chave),
            'criticidade' => CriticalityLevel::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $chave)->value('name') ?? (string) $chave,
            default => (string) ($rotulo ?? $chave),
        };
    }

    /** @return array{total: int, cumpridos: int, pct: ?int, tempo_medio: ?int} */
    private static function linha(?object $f): array
    {
        $total = (int) ($f->total ?? 0);
        $cumpridos = (int) ($f->cumpridos ?? 0);

        return ['total' => $total, 'cumpridos' => $cumpridos, 'pct' => $total > 0 ? (int) round($cumpridos / $total * 100) : null, 'tempo_medio' => $total > 0 && isset($f->tempo_medio) ? (int) round((float) $f->tempo_medio) : null];
    }
}
