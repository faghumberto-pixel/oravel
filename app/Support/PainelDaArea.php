<?php

namespace App\Support;

use App\Dashboards\BlockRegistry;
use App\Dashboards\ModuleDashboards;
use App\Filament\Pages\PainelControleAdministrativo;
use App\Filament\Pages\PainelControleCompras;
use App\Filament\Pages\PainelControlePlanejamento;
use App\Filament\Pages\PainelControleRh;
use App\Filament\Pages\VisaoGeralComercial;
use App\Filament\Pages\VisaoGeralFinanceiro;
use App\Filament\Pages\VisaoGeralManutencao;
use App\Models\User;

/**
 * Painel de Controle de cada area (Administrativo, Financeiro, Tecnico, Comercial, RH, Compras, Planejamento): a tela inicial de quem NAO e o
 * administrador da empresa. O dono/administrador continua no Painel de Controle geral. Cada pessoa abre no painel em que o perfil dela le
 * mais blocos (empate: a ordem da lista abaixo); sem nenhum painel de area liberado, a tela inicial segue como sempre.
 */
class PainelDaArea
{
    /** Ordem de desempate. chave do dashboard => pagina */
    private const PAINEIS = [
        'financeiro' => VisaoGeralFinanceiro::class,
        'comercial' => VisaoGeralComercial::class,
        'manutencao' => VisaoGeralManutencao::class,
        'compras' => PainelControleCompras::class,
        'planejamento' => PainelControlePlanejamento::class,
        'rh' => PainelControleRh::class,
        'administrativo' => PainelControleAdministrativo::class,
    ];

    /** Chave do painel de area da pessoa, ou null (administrador, ou nenhum painel de area liberado). */
    public static function chaveDoPainel(?User $user): ?string
    {
        if (! $user || $user->isAdmin() || $user->isSuperAdmin() || ! config('oravel.dashboards_modulo')) {
            return null;
        }

        $melhor = null;
        $max = 0;

        foreach (array_keys(self::PAINEIS) as $chave) {
            $visiveis = collect(ModuleDashboards::find($chave)['blocks'] ?? [])
                ->filter(fn (string $b) => ($classe = BlockRegistry::find($b)) && $classe::visibleTo($user))->count();

            if ($visiveis > $max) {
                [$melhor, $max] = [$chave, $visiveis];
            }
        }

        return $melhor;
    }

    public static function urlInicialPara(?User $user): ?string
    {
        $chave = self::chaveDoPainel($user);

        return $chave ? self::PAINEIS[$chave]::getUrl() : null;
    }
}
