<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaterialPolicy
{
    use HandlesAuthorization;

    /**
     * Trava de Segurança "Super Admin": 
     * Libera tudo automaticamente para quem tem o papel de admin.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    /**
     * Define se o menu "Materiais" aparece na barra lateral.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('ler_material');
    }

    /**
     * Define se o usuário pode ver os detalhes de um material.
     */
    public function view(User $user, Material $material): bool
    {
        return $user->hasPermissionTo('ler_material');
    }

    /**
     * Define se o técnico pode cadastrar novos itens ao estoque.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('criar_material');
    }

    /**
     * Define se o usuário pode editar informações de materiais.
     */
    public function update(User $user, Material $material): bool
    {
        return $user->hasPermissionTo('editar_material');
    }

    /**
     * Define se o usuário pode remover materiais do sistema.
     */
    public function delete(User $user, Material $material): bool
    {
        return $user->hasPermissionTo('excluir_material');
    }

    /**
     * Bloqueio de ações críticas para técnicos.
     */
    public function restore(User $user, Material $material): bool
    {
        return false;
    }

    public function forceDelete(User $user, Material $material): bool
    {
        return false;
    }
}