<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Tenancy;

/**
 * Chat e' uma ferramenta de uso interno (como Appointment -- ver
 * AppointmentPolicy), nao um recurso de negocio restrito por role: uma vez
 * que o PLANO do tenant inclua modulo_chat, qualquer membro do tenant pode
 * usar, sem precisar de permissao granular ler_chat/criar_chat concedida
 * por role. Exigir a permissao granular quebraria o chat pra todo
 * funcionario nao-admin de todo tenant hoje (nenhum jamais teve essa
 * permissao concedida, porque o chat nunca exigiu isso antes).
 *
 * A trava comercial (Tenant::hasFeature('modulo_chat')) continua valendo
 * -- era isso que estava faltando (achado de auditoria de permissoes,
 * 2026-07-08): sem Policy nomeada, ChatRoom caia no DynamicPolicy generico,
 * que nao consegue resolver o modelo em viewAny/create sem $record e
 * pulava a trava comercial silenciosamente pra qualquer admin de tenant.
 */
class ChatRoomPolicy extends AbstractPolicy
{
    public function viewAny(User $user, $model = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenant = Tenancy::current();
        if ($tenant && ! $tenant->hasFeature('modulo_chat')) {
            return false;
        }

        return true;
    }

    public function create(User $user, $model = null): bool
    {
        return $this->viewAny($user, $model);
    }
}
