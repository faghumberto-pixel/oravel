<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * O 'before' intercepta o pedido e libera o dono do Oravel.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Se for o seu e-mail de dono ou tiver a role admin, libera tudo.
        if (str_ends_with($user->email, '@oravel.com.br') || $user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ler_usuario');
    }

    public function view(User $user, User $model): bool
    {
        // Verifica permissão e se pertencem ao mesmo Tenant
        return $user->can('ler_usuario') && $user->tenant_id === $model->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('criar_usuario');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('editar_usuario') && $user->tenant_id === $model->tenant_id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('excluir_usuario') && $user->tenant_id === $model->tenant_id;
    }
}