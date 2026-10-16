<?php

namespace App\Services;

use App\Models\CrmLead;
use App\Models\CrmMeta;
use App\Models\CrmVisita;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Realizado × meta do mês para um vendedor (ou para a empresa inteira, com `$userId = null`).
 * Venda = lead ganho com `fechado_em` no mês, creditada ao responsável no fechamento; visita = realizada no mês.
 */
class CrmMetasService
{
    /**
     * @return array{
     *   mes: Carbon, meta: array{valor: ?float, ganhos: ?int, visitas: ?int}, tem_meta: bool,
     *   realizado: array{valor: float, ganhos: int, visitas: int},
     *   atingimento: array{valor: ?float, ganhos: ?float, visitas: ?float},
     *   falta: array{valor: ?float, ganhos: ?int, visitas: ?int},
     *   dias_uteis_restantes: int,
     *   ritmo: array{valor: ?float, ganhos: ?float, visitas: ?float},
     *   previsto: float, realizado_mais_previsto: float
     * }
     */
    public function indicadores(string $tenantId, ?string $userId, Carbon $mes, ?Carbon $hoje = null): array
    {
        $inicio = $mes->copy()->startOfMonth();
        $fim = $mes->copy()->endOfMonth();
        $hoje ??= now();

        $meta = $this->meta($tenantId, $userId, $inicio);

        $ganhos = CrmLead::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereHas('etapa', fn ($q) => $q->where('tipo', 'ganho'))
            ->whereBetween('fechado_em', [$inicio, $fim])
            ->when($userId, fn ($q) => $q->where('vendedor_fechamento_id', $userId));

        $realizado = [
            'valor' => round((float) (clone $ganhos)->sum('valor_fechado'), 2),
            'ganhos' => (clone $ganhos)->count(),
            'visitas' => CrmVisita::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', CrmVisita::STATUS_REALIZADA)
                ->whereRaw('coalesce(checkout_em, agendada_para) between ? and ?', [$inicio, $fim])
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->count(),
        ];

        $diasUteis = $this->diasUteisRestantes($inicio, $fim, $hoje);

        $atingimento = [];
        $falta = [];
        $ritmo = [];

        foreach (['valor', 'ganhos', 'visitas'] as $indicador) {
            $alvo = $meta[$indicador];

            if ($alvo === null || (float) $alvo <= 0) {
                $atingimento[$indicador] = $falta[$indicador] = $ritmo[$indicador] = null;

                continue;
            }

            $faltando = max(0, $alvo - $realizado[$indicador]);
            $atingimento[$indicador] = round($realizado[$indicador] / $alvo * 100, 1);
            $falta[$indicador] = $indicador === 'valor' ? round($faltando, 2) : (int) $faltando;
            $ritmo[$indicador] = $diasUteis > 0 ? round($faltando / $diasUteis, 2) : null;
        }

        $previsto = $this->previsto($tenantId, $userId, $inicio, $fim);

        return [
            'mes' => $inicio,
            'meta' => $meta,
            'tem_meta' => collect($meta)->contains(fn ($v) => $v !== null && (float) $v > 0),
            'realizado' => $realizado,
            'atingimento' => $atingimento,
            'falta' => $falta,
            'dias_uteis_restantes' => $diasUteis,
            'ritmo' => $ritmo,
            'previsto' => $previsto,
            'realizado_mais_previsto' => round($realizado['valor'] + $previsto, 2),
        ];
    }

    /**
     * Meta do alvo no mês. Para a empresa (`$userId` nulo): a linha "equipe" se existir; senão a soma das metas individuais.
     *
     * @return array{valor: ?float, ganhos: ?int, visitas: ?int}
     */
    public function meta(string $tenantId, ?string $userId, Carbon $inicio): array
    {
        $base = CrmMeta::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereDate('mes', $inicio->toDateString());

        if ($userId) {
            $m = (clone $base)->where('user_id', $userId)->first();

            return ['valor' => $m?->meta_valor !== null ? (float) $m->meta_valor : null, 'ganhos' => $m?->meta_ganhos, 'visitas' => $m?->meta_visitas];
        }

        if ($equipe = (clone $base)->whereNull('user_id')->first()) {
            return ['valor' => $equipe->meta_valor !== null ? (float) $equipe->meta_valor : null, 'ganhos' => $equipe->meta_ganhos, 'visitas' => $equipe->meta_visitas];
        }

        $individuais = (clone $base)->whereNotNull('user_id')->get();
        $soma = fn (string $campo) => $individuais->whereNotNull($campo)->isEmpty() ? null : $individuais->sum($campo);

        return ['valor' => $soma('meta_valor') !== null ? (float) $soma('meta_valor') : null, 'ganhos' => $soma('meta_ganhos'), 'visitas' => $soma('meta_visitas')];
    }

    /** Σ valor estimado × probabilidade da etapa dos leads ABERTOS com previsão de fechamento no mês. */
    public function previsto(string $tenantId, ?string $userId, Carbon $inicio, Carbon $fim): float
    {
        $leads = CrmLead::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('previsao_fechamento', [$inicio->toDateString(), $fim->toDateString()])
            ->whereHas('etapa', fn ($q) => $q->where('tipo', 'aberta'))
            ->when($userId, fn ($q) => $q->where('assigned_user_id', $userId))
            ->with('etapa:id,probabilidade')
            ->get(['id', 'etapa_id', 'estimated_value']);

        return round($leads->sum(fn ($l) => (float) $l->estimated_value * ($l->etapa?->probabilidade ?? 0) / 100), 2);
    }

    /** Dias úteis (segunda a sexta) de hoje até o fim do mês; mês passado = 0; mês futuro = todos os dias úteis dele. */
    public function diasUteisRestantes(Carbon $inicio, Carbon $fim, Carbon $hoje): int
    {
        if ($hoje->copy()->startOfDay()->gt($fim)) {
            return 0;
        }

        $de = $hoje->copy()->startOfDay()->max($inicio->copy()->startOfDay());
        $dias = 0;

        for ($d = $de->copy(); $d->lte($fim); $d->addDay()) {
            $d->isWeekday() && $dias++;
        }

        return $dias;
    }

    /** Vendedores da empresa com ou sem meta no mês (para a tabela da equipe). */
    public function equipe(string $tenantId, Carbon $mes): array
    {
        $ids = CrmMeta::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereDate('mes', $mes->copy()->startOfMonth()->toDateString())->whereNotNull('user_id')->pluck('user_id')
            ->merge(CrmLead::withoutGlobalScopes()->where('tenant_id', $tenantId)->whereNotNull('vendedor_fechamento_id')
                ->whereBetween('fechado_em', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()])->pluck('vendedor_fechamento_id'))
            ->unique()->values();

        return User::withoutGlobalScopes()->whereIn('id', $ids)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['usuario' => $u] + $this->indicadores($tenantId, $u->id, $mes))
            ->sortByDesc(fn ($l) => $l['atingimento']['valor'] ?? -1)->values()->all();
    }
}
