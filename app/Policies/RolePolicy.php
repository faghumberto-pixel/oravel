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
        // Super admin acima de tudo, inclusive da trava comercial -- mesma
        // ordem de AbstractPolicy::check(). Antes isso era checado depois
        // da trava comercial (so nao vazava porque Tenant::hasFeature() ja
        // tem seu proprio bypass redundante de super admin), e o proprio
        // isAdmin() abaixo usava str_ends_with(email, '@oravel.com.br') ||
        // hasRole('admin'), o que bloqueava super admins cadastrados com
        // outro dominio de e-mail (achado de auditoria de permissoes,
        // 2026-07-08).
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Trava comercial: nega para todos, inclusive admin do tenant, se o
        // plano nao incluir esse modulo (mesmo padrao de AbstractPolicy::check()).
        if (($tenant = Tenancy::current()) && ! $tenant->hasFeature('tabela_roles')) {
            return false;
        }

        if ($user->isAdmin()) {
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
