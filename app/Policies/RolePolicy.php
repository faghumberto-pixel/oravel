<?php

namespace App\Policies;

use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Super Admin: Garante acesso total para você configurar o Oravel.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determina se o usuário pode ver o menu de Funções.
     * Se retornar false, o menu desaparece da barra lateral.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('ler_funcao');
    }

    /**
     * Determina se o usuário pode visualizar uma função específica.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('ler_funcao');
    }

    /**
     * Determina se o usuário pode criar novas funções.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('criar_funcao');
    }

    /**
     * Determina se o usuário pode editar permissões e nomes de funções.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('editar_funcao');
    }

    /**
     * Determina se o usuário pode excluir funções do sistema.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('excluir_funcao');
    }

    /**
     * Ações críticas bloqueadas para perfis operacionais.
     */
    public function restore(User $user, Role $role): bool
    {
        return false;
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }
}