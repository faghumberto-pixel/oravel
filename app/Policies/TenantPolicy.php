<?php

namespace App\Policies;

use App\Models\Tenant; // Ou o nome do seu Model de empresa
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TenantPolicy
{
    use HandlesAuthorization;

    // O "before" aqui é a sua barreira de segurança
    public function before(User $user, string $ability): ?bool
    {
        // Apenas o e-mail oficial ou role admin pode tocar em tenants
        if (str_ends_with($user->email, '@oravel.com.br') || $user->hasRole('admin')) {
            return true;
        }
        
        // Se não for admin, retorna false para qualquer habilidade relacionada a Tenant
        return false;
    }

    // Métodos como viewAny, create, etc., nem precisam ser definidos 
    // se o 'before' retornar false para tudo que não for admin.
}