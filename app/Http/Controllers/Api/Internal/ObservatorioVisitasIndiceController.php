<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\WebPageview;
use App\Models\WebVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Resumo das visitas a pagina do Indice Oravel (oravel.org/indice/) para o painel do Observatorio (outra aplicacao).
 * Usa o MESMO rastreamento que o Central ja faz do oravel.org (WebVisit/WebPageview), entao robos e IPs ignorados ja ficaram de fora.
 * So' devolve agregados (estado, cidade, dia, origem, dispositivo): nunca IP nem dados de uma visita individual.
 * Protegido por chave (OBSERVATORIO_STATS_TOKEN); sem chave configurada, a rota nao existe.
 */
class ObservatorioVisitasIndiceController extends Controller
{
    private const PAGINA = '/indice%';

    public function __invoke(Request $request): JsonResponse
    {
        $esperado = (string) config('oravel.observatorio_stats.token');
        if ($esperado === '') {
            abort(404);
        }
        if (! hash_equals($esperado, (string) $request->bearerToken())) {
            return response()->json(['erro' => 'nao_autorizado'], 401);
        }

        $dias = max(1, min(90, (int) $request->query('dias', 30)));
        $ini = now()->subDays($dias - 1)->startOfDay();

        $visitas = fn () => WebVisit::query()->doSite(WebVisit::SITE_ORG)->where('started_at', '>=', $ini)
            ->whereHas('pageviews', fn ($q) => $q->where('path', 'like', self::PAGINA));

        $conta = fn (string $coluna) => $visitas()->whereNotNull($coluna)->where($coluna, '!=', '')->selectRaw("{$coluna} as rotulo, count(*) as n")
            ->groupBy($coluna)->orderByDesc('n')->limit(15)->get()->map(fn ($l) => ['rotulo' => (string) $l->rotulo, 'n' => (int) $l->n])->all();

        $porDia = $visitas()->get(['started_at'])->countBy(fn ($v) => $v->started_at->format('Y-m-d'));
        $serie = collect(range(0, $dias - 1))->map(fn ($i) => $ini->copy()->addDays($i)->format('Y-m-d'))->map(fn ($d) => ['dia' => $d, 'n' => (int) ($porDia[$d] ?? 0)])->all();

        $origens = $visitas()->get(['utm_source', 'utm_medium', 'referrer_host'])->map->sourceLabel()->countBy()->sortDesc()->take(10)
            ->map(fn ($n, $rotulo) => ['rotulo' => (string) $rotulo, 'n' => (int) $n])->values()->all();

        $cidades = $visitas()->whereNotNull('city')->where('city', '!=', '')->selectRaw('city, state, count(*) as n')->groupBy('city', 'state')->orderByDesc('n')->limit(15)->get()
            ->map(fn ($l) => ['rotulo' => $l->city.($l->state ? ' ('.$l->state.')' : ''), 'n' => (int) $l->n])->all();

        return response()->json([
            'dias' => $dias,
            'visitas' => $visitas()->count(),
            'pageviews' => WebPageview::where('path', 'like', self::PAGINA)->where('entered_at', '>=', $ini)->whereHas('visit', fn ($q) => $q->doSite(WebVisit::SITE_ORG))->count(),
            'estados' => $conta('state'),
            'cidades' => $cidades,
            'dispositivos' => $conta('device_type'),
            'origens' => $origens,
            'por_dia' => $serie,
        ])->header('Cache-Control', 'no-store');
    }
}
