<?php

namespace App\Policies;

use App\Models\Checklist;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChecklistPolicy
{
    use HandlesAuthorization;

    /**
     * Super Admin: Ignora todas as restrições para usuários com a função 'admin'.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determina se o usuário pode ver o menu de Checklists.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('ler_checklist');
    }

    /**
     * Determina se o usuário pode visualizar um checklist específico.
     */
    public function view(User $user, Checklist $checklist): bool
    {
        return $user->hasPermissionTo('ler_checklist');
    }

    /**
     * Determina se o usuário pode criar novos modelos de checklist.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('criar_checklist');
    }

    /**
     * Determina se o usuário pode editar checklists existentes.
     */
    public function update(User $user, Checklist $checklist): bool
    {
        return $user->hasPermissionTo('editar_checklist');
    }

    /**
     * Determina se o usuário pode excluir checklists.
     */
    public function delete(User $user, Checklist $checklist): bool
    {
        return $user->hasPermissionTo('excluir_checklist');
    }

    /**
     * Restaurações e exclusões permanentes geralmente são bloqueadas para técnicos.
     */
    public function restore(User $user, Checklist $checklist): bool
    {
        return false;
    }

    public function forceDelete(User $user, Checklist $checklist): bool
    {
        return false;
    }
}