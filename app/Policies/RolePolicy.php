<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Auth\Access\HandlesAuthorization;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Super Admin Bypass: Libera tudo automaticamente para você e e-mails oficiais.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Trava comercial: nega para todos, inclusive admin do tenant, se o
        // plano nao incluir esse modulo (mesmo padrao de AbstractPolicy::check()).
        if (($tenant = Tenancy::current()) && ! $tenant->hasFeature('tabela_roles')) {
            return false;
        }

        if (str_ends_with($user->email, '@oravel.com.br') || $user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determina se o usuário pode ver o menu de Funções.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('gestor') || $user->can('ler_funcao');
    }

    /**
     * Determina se o usuário pode visualizar uma função específica.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->hasRole('gestor') || $user->can('ler_funcao');
    }

    /**
     * Determina se o usuário pode criar novas funções.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('gestor') || $user->can('criar_funcao');
    }

    /**
     * Determina se o usuário pode editar permissões.
     */
    public function update(User $user, Role $role): bool
    {
        // Impede que o gestor tente alterar a própria role de gestor por URL
        if ($role->name === 'gestor' && ! $user->hasRole('admin')) {
            return false;
        }

        return $user->hasRole('gestor') || $user->can('editar_funcao');
    }

    /**
     * Determina se o usuário pode excluir funções.
     */
    public function delete(User $user, Role $role): bool
    {
        // Bloqueio rígido de exclusão das roles estruturais do SaaS
        if (in_array($role->name, ['admin', 'gestor', 'colaborador'])) {
            return false;
        }

        return $user->hasRole('gestor') || $user->can('excluir_funcao');
    }

    public function restore(User $user, Role $role): bool
    {
        return false;
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return false;
    }
}
