<?php

namespace App\Dashboards;

use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;

/**
 * Monta os blocos de um dashboard para um usuário: só entram os blocos cujo módulo o tenant contratou e cujo
 * recurso de origem o usuário pode ler. A conta de cada bloco fica em cache curto, por tenant e por usuário
 * (alguns models filtram por técnico, então o resultado pode diferir entre usuários do mesmo tenant).
 */
class DashboardRenderer
{
    public const CACHE_SECONDS = 60;

    /** @return list<array{key: string, title: string, subtitle: string, size: int, payload: array}> */
    public static function blocks(string $dashboardKey, ?User $user): array
    {
        $definicao = ModuleDashboards::find($dashboardKey);
        $tenant = Tenancy::current();
        if (! $definicao || ! $user || ! $tenant) {
            return [];
        }

        $blocos = [];
        foreach ($definicao['blocks'] as $chave) {
            $classe = BlockRegistry::find($chave);
            if (! $classe || ! $classe::visibleTo($user)) {
                continue;
            }

            $config = $classe::defaultConfig();
            $payload = Cache::remember(
                'dash:'.$tenant->id.':'.$user->id.':'.$chave.':'.md5(json_encode($config)),
                static::CACHE_SECONDS,
                fn () => (new $classe)->data($config),
            );

            $blocos[] = [
                'key' => $chave,
                'title' => $classe::title(),
                'subtitle' => $classe::subtitle(),
                'size' => $classe::size(),
                'payload' => $payload,
            ];
        }

        return $blocos;
    }

    /** O dashboard aparece no menu só se a flag estiver ligada e ao menos um bloco for visível. */
    public static function available(string $dashboardKey, ?User $user): bool
    {
        if (! config('oravel.dashboards_modulo') || ! $user || ! Tenancy::current()) {
            return false;
        }

        foreach (ModuleDashboards::find($dashboardKey)['blocks'] ?? [] as $chave) {
            $classe = BlockRegistry::find($chave);
            if ($classe && $classe::visibleTo($user)) {
                return true;
            }
        }

        return false;
    }
}
