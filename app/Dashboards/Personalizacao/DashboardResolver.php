<?php

namespace App\Dashboards\Personalizacao;

use App\Dashboards\BlockRegistry;
use App\Dashboards\DashboardRenderer;
use App\Models\DashboardAssignment;
use App\Models\DashboardTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Collection;

/**
 * Decide o que a empresa vê: os dashboards personalizados atribuídos a ela, só com blocos de módulos que ela
 * contratou e que o usuário pode ler. Sem atribuição, a empresa segue com os dashboards padrão dos módulos.
 *
 * Todas as consultas filtram o tenant de forma explícita (não dependem do escopo do model): um modelo exclusivo de
 * outra empresa nunca entra, mesmo que exista uma atribuição apontando para ele.
 */
class DashboardResolver
{
    public static function enabled(): bool
    {
        return (bool) config('oravel.dashboards_personalizados');
    }

    /** @return Collection<int, DashboardTemplate> */
    public static function templatesFor(?Tenant $tenant): Collection
    {
        if (! $tenant) {
            return collect();
        }

        $ordem = DashboardAssignment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('position', 'dashboard_template_id');

        return DashboardTemplate::withoutGlobalScopes()
            ->whereIn('id', $ordem->keys())
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->with('blocks')
            ->get()
            ->sortBy(fn (DashboardTemplate $t) => [$ordem[$t->id] ?? 0, $t->name])
            ->values();
    }

    /** @return list<array{key: string, title: string, subtitle: string, size: int, payload: array}> */
    public static function blocks(DashboardTemplate $template, ?User $user): array
    {
        $tenant = Tenancy::current();
        if (! $user || ! $tenant) {
            return [];
        }

        $blocos = [];
        foreach ($template->blocks as $bloco) {
            $classe = BlockRegistry::find($bloco->block_key);
            if (! $classe || ! $classe::visibleTo($user)) {
                continue;
            }

            $blocos[] = [
                'key' => $bloco->block_key,
                'title' => $classe::title(),
                'subtitle' => $classe::subtitle(),
                'size' => $classe::size(),
                'payload' => DashboardRenderer::payload($classe, $classe::mergeConfig($bloco->config), $tenant, $user),
            ];
        }

        return $blocos;
    }

    /**
     * Ids dos modelos que a Central marcou como tela Início da empresa.
     *
     * @return Collection<int, string>
     */
    public static function idsDoInicio(?Tenant $tenant): Collection
    {
        if (! $tenant) {
            return collect();
        }

        return DashboardAssignment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_home', true)->pluck('dashboard_template_id');
    }

    /**
     * Dashboards atribuídos que têm ao menos um bloco visível para o usuário.
     * `$inicio = true` devolve só os da tela Início; `false` (padrão) devolve os demais, que ficam na página "Dashboards".
     *
     * @return Collection<int, DashboardTemplate>
     */
    public static function visiveisPara(?User $user, bool $inicio = false): Collection
    {
        if (! static::enabled() || ! $user) {
            return collect();
        }

        $tenant = Tenancy::current();
        $doInicio = static::idsDoInicio($tenant);

        return static::templatesFor($tenant)
            ->filter(fn (DashboardTemplate $t) => $doInicio->contains($t->id) === $inicio)
            ->filter(fn (DashboardTemplate $t) => $t->blocks->contains(function ($b) use ($user) {
                $classe = BlockRegistry::find($b->block_key);

                return $classe && $classe::visibleTo($user);
            }))
            ->values();
    }
}
