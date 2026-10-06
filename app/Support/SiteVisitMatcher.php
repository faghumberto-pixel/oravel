<?php

namespace App\Support;

use App\Models\WebVisit;
use Illuminate\Support\Carbon;

/**
 * Descobre QUEM foi o visitante de um lead do formulário do site: a visita que estava ativa, em páginas
 * com formulário (contato/locadoras), no instante do envio. O site não repassa o visitante ao sistema,
 * então o cruzamento é por horário -- só vale quando existe EXATAMENTE uma visita candidata (com várias
 * simultâneas, não adivinha). Dá cidade/estado, de onde veio, aparelho e o caminho dentro do site.
 */
class SiteVisitMatcher
{
    public static function forMoment(Carbon $at): ?WebVisit
    {
        $candidates = WebVisit::query()
            ->where('started_at', '<=', $at->copy()->addSeconds(10))
            ->where('last_activity_at', '>=', $at->copy()->subSeconds(90))
            ->whereHas('pageviews', fn ($q) => $q->where('path', 'like', '/contato%')->orWhere('path', 'like', '/locadoras%'))
            ->get();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    /**
     * @param  array<string, mixed>  $lead  utm_*, gclid... informados pelo formulário
     * @return array<string, string>
     */
    public static function details(WebVisit $visit, array $lead = []): array
    {
        $paths = $visit->pageviews()->orderBy('entered_at')->pluck('path')->implode(' → ');

        return array_filter([
            'cidade' => $visit->city,
            'estado' => $visit->state,
            'veio_de' => self::cameFrom($visit, $lead),
            'primeira_pagina' => $visit->landing_path,
            'caminho_no_site' => $paths,
            'tempo_no_site' => WebAnalytics::duration($visit->duration_seconds),
            'aparelho' => trim(($visit->device_type === 'mobile' ? 'Celular' : ($visit->device_type === 'desktop' ? 'Computador' : (string) $visit->device_type)).' · '.$visit->browser.' · '.$visit->os, ' ·'),
            'identificado_por' => 'visita ativa no horário do envio',
        ], fn ($v) => filled($v));
    }

    /** "Anúncio do Google (campanha X)", "Google (busca)", "chatgpt.com" ou "Direto". */
    public static function cameFrom(WebVisit $visit, array $lead = []): string
    {
        $source = $lead['utm_source'] ?? $visit->utm_source;
        $campaign = $lead['utm_campaign'] ?? $visit->utm_campaign;

        if (! empty($lead['gclid']) || ! empty($lead['gbraid']) || ! empty($lead['wbraid']) || in_array(strtolower((string) $source), ['google', 'google_ads'], true) && $campaign) {
            return 'Anúncio do Google'.($campaign ? " (campanha {$campaign})" : '');
        }

        if ($source) {
            return (string) $source;
        }

        if ($visit->referrer_host) {
            return str_contains($visit->referrer_host, 'google.') ? 'Google (busca)' : $visit->referrer_host;
        }

        return 'Direto (digitou o endereço ou usou um favorito)';
    }
}
