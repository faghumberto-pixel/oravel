<?php

namespace App\Support;

/** Leitura simples de User-Agent para o analytics do site (dispositivo, navegador, sistema). */
class UserAgentInfo
{
    /** @return array{device: string, browser: string, os: string} */
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;

        $device = match (true) {
            (bool) preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua) => 'tablet',
            (bool) preg_match('/Mobi|iPhone|iPod|Android.*Mobile|Windows Phone/i', $ua) => 'mobile',
            (bool) preg_match('/Android/i', $ua) => 'tablet',
            default => 'desktop',
        };

        $browser = match (true) {
            (bool) preg_match('/Edg(e|A|iOS)?\//i', $ua) => 'Edge',
            (bool) preg_match('/OPR\/|Opera/i', $ua) => 'Opera',
            (bool) preg_match('/SamsungBrowser/i', $ua) => 'Samsung Internet',
            (bool) preg_match('/Firefox|FxiOS/i', $ua) => 'Firefox',
            (bool) preg_match('/Chrome|CriOS/i', $ua) => 'Chrome',
            (bool) preg_match('/Safari/i', $ua) => 'Safari',
            default => 'Outro',
        };

        $os = match (true) {
            (bool) preg_match('/Windows/i', $ua) => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod|iOS/i', $ua) => 'iOS',
            (bool) preg_match('/Android/i', $ua) => 'Android',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua) => 'macOS',
            (bool) preg_match('/Linux|X11/i', $ua) => 'Linux',
            default => 'Outro',
        };

        return ['device' => $device, 'browser' => $browser, 'os' => $os];
    }

    public static function isBot(?string $ua): bool
    {
        if (blank($ua)) {
            return true;
        }

        return (bool) preg_match(
            '/bot|crawl|spider|slurp|headless|lighthouse|pagespeed|gtmetrix|facebookexternalhit|whatsapp|curl|wget|python|httpclient|okhttp|monitor|uptime|preview|scanner/i',
            $ua
        );
    }
}
