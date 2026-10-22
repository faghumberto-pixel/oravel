<?php

namespace App\Support;

use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\TimeClock;
use App\Models\User;

/**
 * Atalhos da barra de icones do rodape dos apps mobile (componente
 * <x-mobile.bottom-nav>). Cada pessoa so ve o que pode abrir: o item some se a
 * policy nega. "Inicio" e' a tela inicial do perfil (AppProfile) e "Mais" abre o
 * menu completo do app. No maximo 5 icones, sempre Inicio primeiro e Mais por ultimo.
 */
class MobileNav
{
    public const MAX_ITENS = 5;

    /** @return list<array{key: string, label: string, icon: string, url: string}> */
    public static function itens(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $inicio = AppProfile::homeUrl($user, AppProfile::for($user));
        $menu = route('filament.admin.pages.app-colaborador');

        $meios = [];
        if ($user->can('viewAny', MaintenanceOrder::class)) {
            $meios[] = ['key' => 'os', 'label' => 'OS', 'icon' => 'lista', 'url' => route('filament.admin.pages.maintenance-order-list')];
        }
        if ($user->can('viewAny', Client::class)) {
            $meios[] = ['key' => 'clientes', 'label' => 'Clientes', 'icon' => 'pessoa', 'url' => route('filament.admin.resources.clients.index')];
        }
        if ($user->can('viewAny', MaintenanceOrder::class)) {
            $meios[] = ['key' => 'agenda', 'label' => 'Agenda', 'icon' => 'calendario', 'url' => route('agenda-tecnico.mobile')];
        }
        if ($user->can('viewAny', TimeClock::class)) {
            $meios[] = ['key' => 'ponto', 'label' => 'Ponto', 'icon' => 'relogio', 'url' => route('time-clock.offline')];
        }

        $itens = [['key' => 'inicio', 'label' => 'Início', 'icon' => 'casa', 'url' => $inicio]];
        $itens = array_merge($itens, array_slice($meios, 0, self::MAX_ITENS - 2));

        // Quando o inicio ja e' o menu completo (colaborador), "Mais" seria o mesmo destino.
        if ($inicio !== $menu) {
            $itens[] = ['key' => 'mais', 'label' => 'Mais', 'icon' => 'mais', 'url' => $menu];
        }

        return $itens;
    }
}
