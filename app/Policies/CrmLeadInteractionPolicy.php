<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Tenancy;

/**
 * CrmLeadInteraction e' um "log" de contato (auto-servico do vendedor,
 * como Appointment/ChatRoom -- ver AppointmentPolicy/ChatRoomPolicy), nao
 * um recurso de negocio restrito por role: qualquer membro do tenant com
 * o modulo de CRM no plano pode ver/registrar interacoes.
 *
 * Antes deste fix, CrmLeadInteraction nao tinha HasSaaSMetadata (proposital
 * -- a ideia original era que a trava comercial acontecesse so no acesso ao
 * CrmLead) e nenhuma logica propria de policy (corpo vazio), entao dois
 * problemas reais apareciam juntos: (1) a trava de tabela_crm_leads nunca
 * era checada de fato AQUI (SaaSRegistry::forModel() retorna null sem
 * HasSaaSMetadata, entao AbstractPolicy::check() pulava a checagem de
 * feature nesta policy especificamente), e (2) como nao ha permissao
 * granular pra conceder (sem HasSaaSMetadata, nao aparece aba nenhuma no
 * RoleResource), vendedor nao-admin NUNCA conseguia registrar uma
 * interacao, mesmo com CRM habilitado no plano (achado de auditoria de
 * permissoes, 2026-07-08).
 *
 * update/delete ficam restritos ao autor do registro ou admin -- e' um
 * historico de contato com cliente, nao faz sentido um vendedor editar/
 * apagar o log de outro.
 */
class CrmLeadInteractionPolicy extends AbstractPolicy
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

        return ! $tenant || $tenant->hasFeature('tabela_crm_leads');
    }
}
