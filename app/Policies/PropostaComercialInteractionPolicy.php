<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Tenancy;

/**
 * Mesmo padrão de CrmLeadInteractionPolicy: PropostaComercialInteraction é
 * um log de contato (auto-serviço de quem acessa a proposta), não um
 * recurso de negócio restrito por role -- qualquer membro do tenant com o
 * módulo de Propostas Comerciais no plano pode ver/registrar interações.
 * update/delete ficam restritos ao autor do registro ou admin.
 */
class PropostaComercialInteractionPolicy extends AbstractPolicy
{
    public function viewAny(User $user, $model = null): bool
    {
        return $this->hasFeature($user);
    }

    public function view(User $user, $model = null): bool
    {
        return $this->hasFeature($user);
    }

    public function create(User $user, $model = null): bool
    {
        return $this->hasFeature($user);
    }

    public function update(User $user, $model): bool
    {
        return $this->hasFeature($user) && ($user->isAdmin() || $user->id === $model->user_id);
    }

    public function delete(User $user, $model): bool
    {
        return $this->hasFeature($user) && ($user->isAdmin() || $user->id === $model->user_id);
    }

    private function hasFeature(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = Tenancy::current();

        return ! $tenant || $tenant->hasFeature('tabela_proposta_comercial');
    }
}
