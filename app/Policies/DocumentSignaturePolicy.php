<?php

namespace App\Policies;

use App\Models\DocumentSignature;
use App\Models\User;

class DocumentSignaturePolicy extends AbstractPolicy
{
    /**
     * Determina o model para esta policy (fallback quando não pode ser deduzido do nome).
     */
    protected function resolveModelClass(): string
    {
        return DocumentSignature::class;
    }

    /**
     * Determina se o usuário pode ver a lista de assinaturas.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('ler_assinatura_eletronica');
    }

    /**
     * Determina se o usuário pode ver uma assinatura específica.
     */
    public function view(User $user, DocumentSignature $signature): bool
    {
        return $this->viewAny($user) && $user->tenant_id === $signature->tenant_id;
    }

    /**
     * Determina se o usuário pode criar assinaturas.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermissionTo('criar_assinatura_eletronica');
    }

    /**
     * Determina se o usuário pode renovar um token de assinatura.
     */
    public function renew(User $user, DocumentSignature $signature): bool
    {
        return $this->view($user, $signature);
    }

    /**
     * Determina se o usuário pode cancelar uma assinatura.
     */
    public function cancel(User $user, DocumentSignature $signature): bool
    {
        return $this->view($user, $signature);
    }

    /**
     * Determina se o usuário pode enviar link de assinatura.
     */
    public function send(User $user, DocumentSignature $signature): bool
    {
        return $this->view($user, $signature);
    }
}
