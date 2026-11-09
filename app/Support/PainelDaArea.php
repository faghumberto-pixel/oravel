<?php

namespace App\Support;

use App\Dashboards\BlockRegistry;
use App\Dashboards\ModuleDashboards;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Painel de Controle por FUNCAO: cada Perfil de Acesso (roles.painel_area) escolhe qual painel de area a pessoa ve como Painel de Controle
 * (Financeiro, Comercial, Tecnico, Compras, Planejamento, RH, Administrativo). Vazio = Painel de Controle geral, o do dono. Administrador e
 * super admin nunca trocam de painel. So vale com a flag dos dashboards ligada e se a pessoa le ao menos um bloco do painel escolhido.
 */
class PainelDaArea
{
    /** Chave do contrato que liga as Visoes gerais e os Paineis de Controle por funcao (Central: Contratos > Painel Inicial). */
    public const CHAVE_CONTRATO = 'modulo_paineis_funcao';

    /**
     * Visoes gerais e paineis por funcao liberados para a empresa: a chave do contrato (a Central liga contrato a contrato) E a flag global
     * `oravel.dashboards_modulo`, que continua como "desligar tudo" da plataforma.
     */
    public static function liberadoPara(?Tenant $tenant): bool
    {
        return config('oravel.dashboards_modulo') && $tenant && $tenant->hasFeature(self::CHAVE_CONTRATO);
    }

    /** @return array<string, string> chave do dashboard => nome da area */
    public static function areas(): array
    {
        return [
            'administrativo' => 'Administrativo',
            'financeiro' => 'Financeiro',
            'manutencao' => 'Técnico',
            'comercial' => 'Comercial',
            'compras' => 'Compras',
            'planejamento' => 'Planejamento',
            'rh' => 'Recursos Humanos',
        ];
    }

    /** Chave do painel de area desta pessoa, ou null (administrador, perfil sem painel de area, ou nenhum bloco liberado). */
    public static function chaveDoPainel(?User $user): ?string
    {
        if (! $user || $user->isAdmin() || $user->isSuperAdmin() || ! self::liberadoPara(Tenancy::current() ?? $user->tenant)) {
            return null;
        }

        // Query direta (mesmo estilo de User::isAdmin): o relacionamento do Spatie com "teams" depende do tenant corrente da sessao.
        $escolhidas = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $user->id)->where('model_has_roles.model_type', get_class($user))
            ->whereNotNull('roles.painel_area')->pluck('roles.painel_area')->all();

        if ($escolhidas === []) {
            return null;
        }

        foreach (array_keys(self::areas()) as $chave) { // ordem fixa: se a pessoa tem mais de um perfil com painel, vale a ordem da lista
            if (in_array($chave, $escolhidas, true) && self::leAlgumBloco($user, $chave)) {
                return $chave;
            }
        }

        return null;
    }

    public static function rotulo(string $chave): string
    {
        return self::areas()[$chave] ?? $chave;
    }

    /** Endereco do Painel de Controle quando a funcao tem painel de area; senao null. */
    public static function urlInicialPara(?User $user): ?string
    {
        return self::chaveDoPainel($user) ? route('filament.admin.pages.painel-controle') : null;
    }

    private static function leAlgumBloco(User $user, string $chave): bool
    {
        foreach (ModuleDashboards::find($chave)['blocks'] ?? [] as $bloco) {
            if (($classe = BlockRegistry::find($bloco)) && $classe::visibleTo($user)) {
                return true;
            }
        }

        return false;
    }
}
