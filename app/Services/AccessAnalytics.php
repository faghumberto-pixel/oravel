<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Transforma o registro de atividade de UM usuário (logins, telas abertas, criações/edições)
 * em sessões de uso e tempo estimado por tela.
 *
 * O sistema só registra a navegação (abrir uma tela, entrar, salvar) — não existe "batimento"
 * enquanto o usuário fica parado numa tela — então o tempo é ESTIMADO: o tempo de uma tela é o
 * intervalo até a ação seguinte, limitado a IDLE_CAP_MINUTES (quem deixa a aba aberta não conta
 * horas). Uma sessão termina quando o intervalo passa de SESSION_GAP_MINUTES ou em um novo login.
 */
class AccessAnalytics
{
    public const SESSION_GAP_MINUTES = 30;

    public const IDLE_CAP_MINUTES = 10;

    /**
     * @param  iterable<array{at: CarbonInterface, action: string, label: ?string, path: ?string}>  $events  do mesmo usuário
     * @return list<array{start: CarbonInterface, end: CarbonInterface, seconds: int, events: list<array<string, mixed>>}> mais recente primeiro
     */
    public static function sessions(iterable $events): array
    {
        $sorted = collect($events)->sortBy(fn ($e) => $e['at']->getTimestamp())->values();
        $sessions = [];
        $current = [];

        $flush = function () use (&$current, &$sessions) {
            if ($current === []) {
                return;
            }
            $total = 0;
            foreach ($current as $i => &$e) {
                $next = $current[$i + 1]['at'] ?? null;
                $e['seconds'] = $next ? min($next->getTimestamp() - $e['at']->getTimestamp(), self::IDLE_CAP_MINUTES * 60) : 0;
                $total += $e['seconds'];
            }
            unset($e);
            $sessions[] = [
                'start' => $current[0]['at'],
                'end' => $current[array_key_last($current)]['at'],
                'seconds' => $total,
                'events' => $current,
            ];
            $current = [];
        };

        foreach ($sorted as $event) {
            $last = $current === [] ? null : $current[array_key_last($current)];
            $gap = $last ? $event['at']->getTimestamp() - $last['at']->getTimestamp() : 0;
            if ($last && ($gap > self::SESSION_GAP_MINUTES * 60 || $event['action'] === 'login' || $last['action'] === 'logout')) {
                $flush();
            }
            $current[] = $event;
        }
        $flush();

        return array_reverse($sessions);
    }

    /**
     * @param  list<array<string, mixed>>  $sessions  retorno de sessions()
     * @return array{sessions: int, logins: int, seconds: int, last_at: ?CarbonInterface, screens: int, top_screen: ?string, by_screen: array<string, int>}
     */
    public static function summary(array $sessions): array
    {
        $byScreen = [];
        $logins = 0;
        $screens = [];
        foreach ($sessions as $s) {
            foreach ($s['events'] as $e) {
                if ($e['action'] === 'login') {
                    $logins++;
                }
                if ($e['action'] === 'view') {
                    $label = $e['label'] ?: ($e['path'] ?: 'Tela');
                    $screens[$label] = true;
                    $byScreen[$label] = ($byScreen[$label] ?? 0) + $e['seconds'];
                }
            }
        }
        arsort($byScreen);

        return [
            'sessions' => count($sessions),
            'logins' => $logins,
            'seconds' => array_sum(array_column($sessions, 'seconds')),
            'last_at' => $sessions[0]['end'] ?? null,
            'screens' => count($screens),
            'top_screen' => array_key_first($byScreen),
            'by_screen' => $byScreen,
        ];
    }

    public static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds === 0 ? '—' : '< 1 min';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $h > 0 ? sprintf('%dh %02dmin', $h, $m) : "{$m} min";
    }

    public static function describe(array $e): string
    {
        $label = $e['label'] ?: ($e['path'] ?: '');

        return match ($e['action']) {
            'login' => 'Entrou no sistema',
            'logout' => 'Saiu do sistema',
            'created' => 'Criou registro'.($label ? " em {$label}" : ''),
            'updated' => 'Editou registro'.($label ? " em {$label}" : ''),
            'deleted' => 'Excluiu registro'.($label ? " em {$label}" : ''),
            default => $label ? "Abriu {$label}" : 'Abriu uma tela',
        };
    }

    /** @param  Collection<int, mixed>  $rows */
    public static function fromLogs(Collection $rows): array
    {
        return $rows->map(fn ($l) => [
            'at' => $l->created_at,
            'action' => (string) $l->action,
            'label' => $l->resource_label,
            'path' => $l->path,
        ])->all();
    }
}
