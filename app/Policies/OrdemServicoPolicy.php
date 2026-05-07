<?php

namespace App\Policies;

use App\Models\OrdemServico;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class OrdemServicoPolicy
{
    use HandlesAuthorization;

    /**
     * Super Admin: Garante que você tenha acesso total para gerenciar o sistema.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determina se o usuário pode ver a lista de Ordens de Serviço.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('ler_ordem_servico');
    }

    /**
     * Determina se o usuário pode visualizar uma ordem específica.
     */
    public function view(User $user, OrdemServico $ordemServico): bool
    {
        return $user->hasPermissionTo('ler_ordem_servico');
    }

    /**
     * Determina se o técnico pode abrir novas ordens de serviço.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('criar_ordem_servico');
    }

    /**
     * Essencial: Permite que o Pedro edite a OS para inserir fotos e assinaturas.
     */
    public function update(User $user, OrdemServico $ordemServico): bool
    {
        return $user->hasPermissionTo('editar_ordem_servico');
    }

    /**
     * Determina se o usuário pode excluir ordens do sistema.
     */
    public function delete(User $user, OrdemServico $ordemServico): bool
    {
        return $user->hasPermissionTo('excluir_ordem_servico');
    }

    /**
     * Ações restritas para evitar perda de dados operacionais.
     */
    public function restore(User $user, OrdemServico $ordemServico): bool
    {
        return false;
    }

    public function forceDelete(User $user, OrdemServico $ordemServico): bool
    {
        return false;
    }
}