<?php

namespace App\Services;

use App\Models\OnboardingInicio;
use App\Models\OnboardingProgresso;
use App\Models\Tenant;
use App\Support\Onboarding\OnboardingService;
use Illuminate\Support\Carbon;

/**
 * Métricas do onboarding por solução, para decidir se ele funciona: assinaturas no período, % que concluiu cada passo, % que concluiu os
 * passos todos em até 24 h e o tempo mediano até concluir. Uma "assinatura" é uma empresa criada no período com a solução.
 */
class OnboardingMetricas
{
    public function __construct(private OnboardingService $onboarding) {}

    /** @return list<array<string, mixed>> uma linha por solução configurada */
    public function porSolucao(?Carbon $desde = null, ?Carbon $ate = null): array
    {
        $linhas = [];

        foreach (config('onboarding.solucoes', []) as $codigo => $cfg) {
            $tenants = Tenant::withoutGlobalScopes()->whereHas('plan', fn ($q) => $q->where('pacote', $codigo))
                ->when($desde, fn ($q) => $q->where('created_at', '>=', $desde))->when($ate, fn ($q) => $q->where('created_at', '<=', $ate))->get();

            $total = $tenants->count();
            $ids = $tenants->pluck('id');

            foreach ($tenants as $t) {
                $this->onboarding->sincronizar($t, $codigo);
            }

            $feitos = OnboardingProgresso::withoutGlobalScopes()->where('solution', $codigo)->whereIn('tenant_id', $ids)->whereNotNull('completed_at')->get()->groupBy('step_key');
            $inicios = OnboardingInicio::withoutGlobalScopes()->where('solution', $codigo)->whereIn('tenant_id', $ids)->get();
            $concluidos = $inicios->filter(fn ($i) => $i->concluido_em !== null);
            $minutos = $concluidos->map(fn ($i) => $i->iniciado_em->diffInMinutes($i->concluido_em))->sort()->values();
            $em24h = $concluidos->filter(fn ($i) => $i->iniciado_em->diffInMinutes($i->concluido_em) <= 1440)->count();

            $linhas[] = [
                'solucao' => $codigo, 'titulo' => $cfg['titulo'], 'assinaturas' => $total,
                'passos' => collect($cfg['passos'])->map(fn ($p) => [
                    'chave' => $p['chave'], 'titulo' => $p['titulo'], 'concluiram' => $n = ($feitos[$p['chave']] ?? collect())->count(), 'percentual' => $this->pct($n, $total),
                ])->all(),
                'concluiram_todos' => $concluidos->count(), 'pct_concluiram_todos' => $this->pct($concluidos->count(), $total),
                'concluiram_em_24h' => $em24h, 'pct_em_24h' => $this->pct($em24h, $total),
                'mediana_minutos' => $this->mediana($minutos->all()),
            ];
        }

        return $linhas;
    }

    private function pct(int $parte, int $total): float
    {
        return $total > 0 ? round($parte / $total * 100, 1) : 0.0;
    }

    /** @param  list<int>  $ordenados */
    private function mediana(array $ordenados): ?int
    {
        $n = count($ordenados);

        if ($n === 0) {
            return null;
        }

        return $n % 2 ? $ordenados[intdiv($n, 2)] : (int) round(($ordenados[$n / 2 - 1] + $ordenados[$n / 2]) / 2);
    }

    /** "3 h 20 min", "2 dias 4 h" ou "—". */
    public static function duracao(?int $minutos): string
    {
        if ($minutos === null) {
            return '—';
        }

        if ($minutos < 60) {
            return $minutos.' min';
        }

        if ($minutos < 1440) {
            return intdiv($minutos, 60).' h'.($minutos % 60 ? ' '.($minutos % 60).' min' : '');
        }

        $dias = intdiv($minutos, 1440);
        $horas = intdiv($minutos % 1440, 60);

        return $dias.' '.($dias === 1 ? 'dia' : 'dias').($horas ? " {$horas} h" : '');
    }
}
