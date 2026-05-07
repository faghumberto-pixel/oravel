<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AssetPolicy
{
    use HandlesAuthorization;

    /**
     * O método 'before' é executado antes de qualquer outra verificação.
     * Isso garante que você (Admin) sempre tenha acesso total.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }

    /**
     * Valida se o usuário tem a permissão específica via Spatie.
     * Utilizamos os nomes definidos no seu PermissionSeeder (ler, criar, editar, excluir).
     */
    public function viewAny(User $user): bool 
    { 
        return $user->hasPermissionTo('ler_ativo'); 
    }

    public function view(User $user, Asset $asset): bool 
    { 
        return $user->hasPermissionTo('ler_ativo'); 
    }

    public function create(User $user): bool 
    { 
        return $user->hasPermissionTo('criar_ativo'); 
    }

    public function update(User $user, Asset $asset): bool 
    { 
        return $user->hasPermissionTo('editar_ativo'); 
    }

    public function delete(User $user, Asset $asset): bool 
    { 
        return $user->hasPermissionTo('excluir_ativo'); 
    }
}