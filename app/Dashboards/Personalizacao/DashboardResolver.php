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

        $ids = DashboardAssignment::withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('dashboard_template_id');

        return DashboardTemplate::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->with('blocks')
            ->orderBy('name')
            ->get();
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
     * Dashboards atribuídos que têm ao menos um bloco visível para o usuário.
     *
     * @return Collection<int, DashboardTemplate>
     */
    public static function visiveisPara(?User $user): Collection
    {
        if (! static::enabled() || ! $user) {
            return collect();
        }

        return static::templatesFor(Tenancy::current())->filter(
            fn (DashboardTemplate $t) => $t->blocks->contains(function ($b) use ($user) {
                $classe = BlockRegistry::find($b->block_key);

                return $classe && $classe::visibleTo($user);
            })
        )->values();
    }
}
