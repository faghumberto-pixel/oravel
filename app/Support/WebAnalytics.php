<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Períodos e formatações do painel do site institucional (Central -> Site Institucional). */
class WebAnalytics
{
    /** @return array<string, string> */
    public static function periods(): array
    {
        return ['today' => 'Hoje', '7' => 'Últimos 7 dias', '30' => 'Últimos 30 dias', '90' => 'Últimos 90 dias'];
    }

    public static function since(?string $period): Carbon
    {
        return match ($period) {
            'today' => now()->startOfDay(),
            '30' => now()->subDays(29)->startOfDay(),
            '90' => now()->subDays(89)->startOfDay(),
            default => now()->subDays(6)->startOfDay(), // 7 dias, padrão
        };
    }

    public static function duration(int|float|null $seconds): string
    {
        $seconds = (int) round((float) $seconds);

        return $seconds >= 3600
            ? sprintf('%dh%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60))
            : sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
