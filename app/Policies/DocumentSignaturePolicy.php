<?php

namespace App\Policies;

use App\Models\DocumentSignature;
use App\Models\User;

class DocumentSignaturePolicy extends AbstractPolicy
{
    /**
     * Determina se o usuário pode ver a lista de assinaturas.
     */
    public function viewAny(User $user, $model = null): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('ler_assinatura');
    }

    /**
     * Determina se o usuário pode ver uma assinatura específica.
     */
    public function view(User $user, $signature): bool
    {
        return $this->viewAny($user) && $user->tenant_id === $signature->tenant_id;
    }

    /**
     * Determina se o usuário pode criar assinaturas.
     */
    public function create(User $user, $model = null): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('criar_assinatura');
    }

    /**
     * Determina se o usuário pode renovar um token de assinatura.
     */
    public function renew(User $user, $signature): bool
    {
        return $this->view($user, $signature);
    }

    /**
     * Determina se o usuário pode cancelar uma assinatura.
     */
    public function cancel(User $user, $signature): bool
    {
        return $this->view($user, $signature);
    }

    /**
     * Determina se o usuário pode enviar link de assinatura.
     */
    public function send(User $user, $signature): bool
    {
        return $this->view($user, $signature);
    }
}
