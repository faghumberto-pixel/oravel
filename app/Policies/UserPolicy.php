<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Super admin da plataforma libera tudo.
     */
    public function before(User $user, string $ability): ?bool
    {
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }
        return null;
    }

    /**
     * Só o admin do tenant vê/gerencia o módulo Funcionários.
     * Técnico comum NÃO vê o menu.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->tenant_id === $model->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin() && $user->tenant_id === $model->tenant_id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isAdmin()
            && $user->tenant_id === $model->tenant_id
            && $user->id !== $model->id; // não pode se autodeletar
    }
}