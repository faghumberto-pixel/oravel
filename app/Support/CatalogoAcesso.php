<?php

namespace App\Support;

use App\Models\CatalogoItem;
use Illuminate\Support\Facades\Gate;

/**
 * Acesso ao catalogo de equipamentos (G1). Tudo passa pela CatalogoItemPolicy (AbstractPolicy): trava comercial
 * (chave tabela_catalogo_equipamentos, desligada por padrao) + permissao individual.
 * Ler = ler_catalogo_equipamentos; "gerenciar catalogo" = editar_catalogo_equipamentos (admin do tenant tem bypass).
 */
class CatalogoAcesso
{
    public static function podeLer(): bool
    {
        return auth()->check() && Gate::allows('viewAny', CatalogoItem::class);
    }

    public static function podeGerenciar(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return Gate::allows('update', new CatalogoItem(['tenant_id' => $user->tenant_id ?? Tenancy::current()?->id]));
    }
}
