<?php

namespace App\Dashboards;

use App\Models\Tenant;
use App\Models\User;
use App\Support\PainelDaArea;
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

            $payload = static::payload($classe, $classe::defaultConfig(), $tenant, $user);

            $blocos[] = [
                'key' => $chave,
                'title' => $classe::title(),
                'subtitle' => $classe::subtitle(),
                'size' => $classe::size(),
                'payload' => $payload,
            ];
        }

        return static::ajustarLinhas($blocos);
    }

    /**
     * Fecha as 12 colunas de cada linha. Os blocos têm largura própria (3, 4, 6...), pensada para o dashboard de cada módulo, e quando o
     * contrato esconde alguns, ou quando blocos de módulos diferentes se juntam, a linha ficava com buraco (3 + 4 + 4 = 11). Cada linha que
     * fecha ganha as colunas que faltam, uma a uma, sempre para o bloco mais estreito (6 + 4 vira 6 + 6; 3 + 4 + 4 vira 4 + 4 + 4). A última
     * linha fica como está: sobra no fim da página é natural.
     *
     * @param  list<array{key: string, title: string, subtitle: string, size: int, payload: array}>  $blocos
     * @return list<array{key: string, title: string, subtitle: string, size: int, payload: array}>
     */
    public static function ajustarLinhas(array $blocos): array
    {
        $linha = [];
        $soma = 0;
        $fechadas = [];

        foreach ($blocos as $i => $bloco) {
            if ($linha && $soma + $bloco['size'] > 12) {
                $fechadas[] = $linha;
                $linha = [];
                $soma = 0;
            }
            $linha[] = $i;
            $soma += $bloco['size'];
        }

        foreach ($fechadas as $indices) {
            $falta = 12 - array_sum(array_map(fn ($i) => $blocos[$i]['size'], $indices));
            while ($falta-- > 0) {
                $menor = $indices[0];
                foreach ($indices as $i) {
                    if ($blocos[$i]['size'] < $blocos[$menor]['size']) {
                        $menor = $i;
                    }
                }
                $blocos[$menor]['size']++;
            }
        }

        return $blocos;
    }

    /**
     * Conta de um bloco, em cache curto por tenant e por usuário (o resultado pode diferir entre usuários do mesmo
     * tenant) e por configuração.
     *
     * @param  class-string<Block>  $classe
     */
    public static function payload(string $classe, array $config, Tenant $tenant, User $user): array
    {
        return Cache::remember(
            'dash:'.$tenant->id.':'.$user->id.':'.$classe::key().':'.md5(json_encode($config)),
            static::CACHE_SECONDS,
            fn () => (new $classe)->data($config),
        );
    }

    /** O dashboard aparece no menu só se a flag estiver ligada e ao menos um bloco for visível. */
    public static function available(string $dashboardKey, ?User $user): bool
    {
        if (! $user || ! PainelDaArea::liberadoPara(Tenancy::current())) {
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
