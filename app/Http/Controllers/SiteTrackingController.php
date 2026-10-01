<?php

namespace App\Http\Controllers;

use App\Models\WebEvent;
use App\Models\WebPageview;
use App\Models\WebVisit;
use App\Services\IpGeolocationService;
use App\Support\UserAgentInfo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Coletor do analytics do SITE INSTITUCIONAL (script public/t.js, rodando em
 * oravel.com.br). Recebe pequenos eventos JSON (text/plain, via sendBeacon --
 * evita preflight de CORS): pv = abriu uma página, ping = tempo ativo (a cada
 * 15 s), leave = saiu da página, click = clicou num CTA/link externo. Sempre
 * responde 204, sem revelar nada: origem não autorizada, robô ou payload
 * inválido são ignorados em silêncio, e erro nunca vira 500 no site.
 */
class SiteTrackingController extends Controller
{
    private const MAX_ACTIVE_SECONDS = 14400; // 4 h por página (descarta abas esquecidas)

    public function collect(Request $request, IpGeolocationService $geo): Response
    {
        $origin = $this->allowedOrigin($request);
        $response = response()->noContent();

        if ($origin === null) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Vary', 'Origin');

        try {
            $raw = $request->getContent();
            $data = strlen($raw) <= 4096 ? json_decode($raw, true) : null;

            if (! is_array($data) || UserAgentInfo::isBot($request->userAgent())) {
                return $response;
            }

            $type = (string) ($data['t'] ?? '');
            $visitor = $this->token($data['v'] ?? null);
            $session = $this->token($data['s'] ?? null);
            $page = $this->token($data['p'] ?? null);

            if (! $visitor || ! $session || ! in_array($type, ['pv', 'ping', 'leave', 'click'], true)) {
                return $response;
            }

            match ($type) {
                'pv' => $this->pageview($request, $geo, $data, $visitor, $session, $page),
                'click' => $this->click($data, $session),
                default => $this->time($data, $session, $page),
            };
        } catch (\Throwable $e) {
            Log::warning('SiteTrackingController: falha ao registrar.', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    /** Só aceita eventos vindos do próprio site (Origin, ou Referer na falta dele). */
    private function allowedOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin') ?: $request->headers->get('Referer');
        $host = $origin ? parse_url($origin, PHP_URL_HOST) : null;
        $scheme = $origin ? parse_url($origin, PHP_URL_SCHEME) : null;

        if (! $host || ! in_array(strtolower($host), config('oravel.site_tracking.allowed_hosts', []), true)) {
            return null;
        }

        return ($scheme ?: 'https').'://'.$host;
    }

    private function token(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9\-]{8,64}$/', $value) ? $value : null;
    }

    private function path(mixed $value): string
    {
        $path = is_string($value) ? (string) parse_url($value, PHP_URL_PATH) : '';

        return Str::limit('/'.ltrim($path ?: '/', '/'), 500, '');
    }

    private function text(mixed $value, int $max = 255): ?string
    {
        return is_string($value) && trim($value) !== '' ? Str::limit(trim($value), $max, '') : null;
    }

    /** @param  array<string, mixed>  $data */
    private function pageview(Request $request, IpGeolocationService $geo, array $data, string $visitor, string $session, ?string $page): void
    {
        if (! $page) {
            return;
        }

        $visit = WebVisit::where('session_token', $session)->first();

        if (! $visit) {
            $ua = (string) $request->userAgent();
            $agent = UserAgentInfo::parse($ua);
            $place = $geo->locate($request->ip());
            $referrer = $this->text($data['ref'] ?? null, 1000);

            try {
                $visit = WebVisit::create([
                    'visitor_token' => $visitor,
                    'session_token' => $session,
                    'ip_address' => $request->ip(),
                    'city' => $place['city'],
                    'state' => $place['state'],
                    'user_agent' => Str::limit($ua, 500, ''),
                    'device_type' => $agent['device'],
                    'browser' => $agent['browser'],
                    'os' => $agent['os'],
                    'referrer_url' => $referrer,
                    'referrer_host' => $referrer ? $this->host($referrer) : null,
                    'landing_path' => $this->path($data['path'] ?? '/'),
                    'utm_source' => $this->text($data['utm_source'] ?? null),
                    'utm_medium' => $this->text($data['utm_medium'] ?? null),
                    'utm_campaign' => $this->text($data['utm_campaign'] ?? null),
                    'utm_term' => $this->text($data['utm_term'] ?? null),
                    'utm_content' => $this->text($data['utm_content'] ?? null),
                    'is_returning' => WebVisit::where('visitor_token', $visitor)->exists(),
                    'started_at' => now(),
                    'last_activity_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                $visit = WebVisit::where('session_token', $session)->firstOrFail(); // pageview paralelo criou antes
            }
        }

        if (WebPageview::where('page_token', $page)->exists()) {
            return; // reenvio do mesmo pageview
        }

        WebPageview::create([
            'web_visit_id' => $visit->id,
            'page_token' => $page,
            'path' => $this->path($data['path'] ?? '/'),
            'title' => $this->text($data['title'] ?? null),
            'entered_at' => now(),
        ]);

        $visit->update(['page_views' => $visit->pageviews()->count(), 'last_activity_at' => now()]);
    }

    /** @param  array<string, mixed>  $data */
    private function time(array $data, string $session, ?string $page): void
    {
        $pageview = $page ? WebPageview::where('page_token', $page)->first() : null;

        if (! $pageview || $pageview->visit?->session_token !== $session) {
            return;
        }

        $seconds = min(self::MAX_ACTIVE_SECONDS, max(0, (int) round(((int) ($data['ms'] ?? 0)) / 1000)));
        $scroll = min(100, max(0, (int) ($data['sc'] ?? 0)));

        // Só cresce: pings fora de ordem não podem diminuir o tempo já medido.
        $pageview->update([
            'active_seconds' => max($pageview->active_seconds, $seconds),
            'max_scroll' => max($pageview->max_scroll, $scroll),
        ]);

        $pageview->visit->update([
            'duration_seconds' => (int) $pageview->visit->pageviews()->sum('active_seconds'),
            'last_activity_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function click(array $data, string $session): void
    {
        $visit = WebVisit::where('session_token', $session)->first();
        $label = $this->text($data['label'] ?? null);

        if (! $visit || ! $label) {
            return;
        }

        WebEvent::create([
            'web_visit_id' => $visit->id,
            'type' => 'click',
            'label' => $label,
            'path' => $this->path($data['path'] ?? '/'),
            'occurred_at' => now(),
        ]);
    }

    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }
}
