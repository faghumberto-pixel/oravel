<?php

namespace App\Support;

use App\Http\Middleware\ColaboradorSomenteCelular;
use App\Models\CrmVisita;
use App\Models\MaintenanceOrder;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Área de campo (/campo): versão simples para celular. Ligada por empresa (tenants.campo_ativo, padrão desligado); o celular só é
 * levado para ela no login quando está ligada e a pessoa não escolheu a versão completa. Reaproveita as telas e serviços existentes.
 */
class Campo
{
    public const COOKIE = 'oravel_versao';

    public static function ativaPara(?User $user): bool
    {
        return (bool) ($user?->tenant?->campo_ativo);
    }

    /** Celular + campo ligado + sem escolha manual pela versão completa. */
    public static function deveAbrirNoLogin(?User $user, Request $request): bool
    {
        return self::ativaPara($user)
            && $request->cookie(self::COOKIE) !== 'completa'
            && ColaboradorSomenteCelular::ehCelular($request);
    }

    /** @return 'tecnico'|'vendedor' perfis de campo do usuário (administrador conta como todos). */
    public static function perfis(User $user): array
    {
        if ($user->isAdmin()) {
            return ['tecnico', 'vendedor'];
        }

        $perfis = [];
        if ($user->can('viewAny', MaintenanceOrder::class)) {
            $perfis[] = 'tecnico';
        }
        if ($user->can('viewAny', CrmVisita::class)) {
            $perfis[] = 'vendedor';
        }

        return $perfis;
    }

    /**
     * Atalhos que o usuário vê, já com cor do produto.
     *
     * @return list<array{chave: string, titulo: string, descricao: string, icone: string, url: string, cor: string}>
     */
    public static function itens(User $user): array
    {
        $perfis = self::perfis($user);

        return collect(config('campo.itens', []))
            ->filter(fn ($i) => array_intersect($i['perfis'], $perfis) !== [])
            ->filter(fn ($i) => $user->can('viewAny', $i['modelo']))
            ->map(fn ($i) => [
                'chave' => $i['chave'], 'titulo' => $i['titulo'], 'descricao' => $i['descricao'], 'icone' => $i['icone'],
                'url' => route($i['rota']),
                'cor' => Pacotes::todos()[$i['produto']]['app']['cor'] ?? '#334155',
            ])->values()->all();
    }
}
