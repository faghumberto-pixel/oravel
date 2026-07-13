<?php

namespace App\Policies;

use App\Models\NotificationLog;
use App\Models\User;
use App\Support\Tenancy;

/**
 * Notificacao vira comprovacao de auditoria (data/hora, quem recebeu) --
 * estritamente admin do tenant (ou super admin), sem delegar por
 * permissao granular como AbstractPolicy::check() faria por padrao
 * (decisao explicita do usuario: nao e pra qualquer staff com permissao
 * solta ver isso). Por isso nao estende AbstractPolicy -- e' checagem
 * bem mais restrita que o convencional do resto do app.
 */
class NotificationLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAudit($user);
    }

    public function view(User $user, NotificationLog $record): bool
    {
        return $this->canAudit($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NotificationLog $record): bool
    {
        return false;
    }

    public function delete(User $user, NotificationLog $record): bool
    {
        return false;
    }

    private function canAudit(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isAdmin()) {
            return false;
        }

        $tenant = Tenancy::current();

        return $tenant?->hasFeature('tabela_notification_logs') ?? false;
    }
}
