<?php

namespace App\Domain\Intelligence\Support;

use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;

/**
 * Item de menu de um MÓDULO do Intelligence que depende do degrau do plano. Aparece para todo cliente do Intelligence; quem ainda não
 * tem o módulo vê o item DESABILITADO (sem clique), com o nome do plano que o libera. Registrado em AdminPanelProvider::navigationItems
 * (a Page correspondente tem shouldRegisterNavigation() = false, senão o Filament esconderia o item para quem não tem acesso).
 */
class ItemDeMenuDoModulo
{
    /** @param  class-string<Page>  $pagina */
    public static function make(string $rotulo, string $icone, int $ordem, string $pagina, string $chaveDeMenu, string $planoQueLibera): NavigationItem
    {
        return NavigationItem::make($rotulo)
            ->group('Intelligence')
            ->icon($icone)
            ->sort($ordem)
            ->visible(fn () => self::empresaDoIntelligence($chaveDeMenu))
            ->url(fn () => $pagina::canAccess() ? $pagina::getUrl() : null)
            ->isActiveWhen(fn () => request()->routeIs($pagina::getRouteName()))
            ->badge(fn () => $pagina::canAccess() ? null : $planoQueLibera, 'gray')
            ->badgeTooltip(fn () => $pagina::canAccess() ? null : "Disponível no plano {$planoQueLibera}");
    }

    /** Cliente (não o super admin) cujo contrato inclui o menu deste módulo. */
    public static function empresaDoIntelligence(string $chaveDeMenu): bool
    {
        $u = auth()->user();

        return $u && ! $u->isSuperAdmin() && filled($u->tenant_id) && (bool) $u->tenant?->hasFeature($chaveDeMenu);
    }
}
