<?php

namespace App\Http\Middleware;

use App\Models\SiteVisit;
use App\Models\User;
use App\Support\RequestNoiseFilter;
use App\Support\SiteVisitTenantResolver;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra sessoes de visita (acesso, referrer, UTM, tempo de visita) pro
 * painel de acompanhamento do central -- cobre anonimo e autenticado, em
 * /admin, /central e rotas publicas por token, ao contrario de
 * LogUserActivity (so' autenticado, so' /admin). 1 linha por SESSAO, nao por
 * pagina vista: cria na entrada, atualiza (sem sobrescrever referrer/UTM) em
 * hits seguintes -- first-touch attribution de proposito, senao um clique
 * interno qualquer apagaria a origem real do visitante.
 *
 * Escrita sincrona (mesmo padrao do LogUserActivity ja em producao), depois
 * de $next($request) pra nao atrasar a resposta ao navegador.
 */
class TrackSiteVisit
{
    private const VISITOR_COOKIE = 'ov_vid';

    private const SESSION_COOKIE = 'ov_sid';

    private const VISITOR_COOKIE_MINUTES = 60 * 24 * 365;

    private const SESSION_INACTIVITY_MINUTES = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Tracking e' telemetria pura, best-effort -- nunca pode derrubar a
        // resposta real. Rotas publicas por token (patio.ativo-status,
        // quotes.public-*, hour-meter.public.*, portaria.verificar) recebem
        // parametros arbitrarios de visitantes/bots; um token que nao bate
        // com o formato UUID/coluna esperado gera QueryException no Postgres
        // (ex: "invalid input syntax for type uuid") dentro de
        // SiteVisitTenantResolver::resolve() ou de resolveAssetTenantId(), e
        // sem este catch essa excecao subia direto (fora do fluxo normal de
        // tratamento de excecao do request, que ja rodou dentro de $next())
        // e virava 500 puro -- ja aconteceu com /patio/ativo/<token invalido>
        // (ver commit revertido f419475) e explica erros 500 aparentemente
        // sem relacao, incluindo /admin/login, quando trafego de bot bate
        // repetidamente nessas rotas publicas.
        try {
            $this->track($request);
        } catch (\Throwable $e) {
            report($e);
        }

        return $response;
    }

    private function track(Request $request): void
    {
        if (! $request->isMethod('GET') || RequestNoiseFilter::isNoise($request)) {
            return;
        }

        $visitorToken = $request->cookie(self::VISITOR_COOKIE) ?: (string) Str::uuid();

        if (! $request->cookie(self::VISITOR_COOKIE)) {
            Cookie::queue(self::VISITOR_COOKIE, $visitorToken, self::VISITOR_COOKIE_MINUTES);
        }

        $sessionToken = $request->cookie(self::SESSION_COOKIE);
        $visit = $sessionToken ? SiteVisit::where('session_token', $sessionToken)->first() : null;

        $isExpired = $visit && $visit->last_activity_at->lt(now()->subMinutes(self::SESSION_INACTIVITY_MINUTES));

        if (! $visit || $isExpired) {
            $visit = $this->startNewVisit($request);
        } else {
            $this->continueVisit($visit, $request);
        }

        Cookie::queue(self::SESSION_COOKIE, $visit->session_token, self::SESSION_INACTIVITY_MINUTES);
    }

    private function startNewVisit(Request $request): SiteVisit
    {
        $now = Carbon::now();
        $referrer = $request->headers->get('referer');

        $visit = new SiteVisit;
        $visit->id = (string) Str::uuid();
        $visit->tenant_id = $this->resolveTenantId($request);
        $visit->user_id = $this->resolveUserId();
        $visit->visitor_token = $request->cookie(self::VISITOR_COOKIE) ?: (string) Str::uuid();
        $visit->session_token = (string) Str::uuid();
        $visit->ip_address = $request->ip();
        $visit->user_agent = $request->userAgent();
        $visit->device_type = $this->resolveDeviceType($request->userAgent());
        $visit->referrer_url = $referrer;
        $visit->referrer_host = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;
        $visit->landing_path = '/'.trim($request->path(), '/');
        $visit->utm_source = $request->query('utm_source');
        $visit->utm_medium = $request->query('utm_medium');
        $visit->utm_campaign = $request->query('utm_campaign');
        $visit->utm_term = $request->query('utm_term');
        $visit->utm_content = $request->query('utm_content');
        $visit->entry_panel = $this->resolveEntryPanel($request);
        $visit->page_views = 1;
        $visit->started_at = $now;
        $visit->last_activity_at = $now;
        $visit->duration_seconds = 0;
        $visit->save();

        return $visit;
    }

    private function continueVisit(SiteVisit $visit, Request $request): void
    {
        $now = Carbon::now();

        if (! $visit->user_id && ($userId = $this->resolveUserId())) {
            $visit->user_id = $userId;

            if (! $visit->tenant_id) {
                $visit->tenant_id = $this->resolveTenantId($request);
            }
        }

        $visit->page_views++;
        $visit->last_activity_at = $now;
        // diffInSeconds() do Carbon 3 retorna valor COM sinal por padrao
        // (mudou do Carbon 2) -- abs() evita duration_seconds negativo
        // estourando a coluna unsignedInteger.
        $visit->duration_seconds = (int) abs($visit->started_at->diffInSeconds($now));
        $visit->save();
    }

    private function resolveTenantId(Request $request): ?string
    {
        return Tenancy::current()?->id ?? SiteVisitTenantResolver::resolve($request);
    }

    /**
     * site_visits.user_id tem FK pra users -- um Client autenticado
     * (guard 'client', Portal do Cliente) não é User e quebraria a FK
     * aqui se não checássemos o tipo (Auth::id() usa o guard "current",
     * que o middleware auth:client já deixou apontando pro Client).
     */
    private function resolveUserId(): ?string
    {
        $user = Auth::user();

        return $user instanceof User ? $user->id : null;
    }

    private function resolveEntryPanel(Request $request): string
    {
        return match (true) {
            str_starts_with($request->path(), 'admin') => 'admin',
            str_starts_with($request->path(), 'central') => 'central',
            default => 'public',
        };
    }

    private function resolveDeviceType(?string $userAgent): ?string
    {
        if (! $userAgent) {
            return null;
        }

        if (preg_match('/bot|crawl|spider|slurp/i', $userAgent)) {
            return 'bot';
        }

        if (preg_match('/iPad|Tablet/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/Mobile|Android|iPhone/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }
}
