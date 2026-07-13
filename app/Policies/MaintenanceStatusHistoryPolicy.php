<?php

namespace App\Policies;

use App\Models\MaintenanceStatusHistory;
use App\Models\User;
use App\Support\Tenancy;

/**
 * Dado de auditoria (quem trocou o status de qual OS, quando) --
 * estritamente admin do tenant (ou super admin), mesma decisao de
 * NotificationLogPolicy: nao delega por permissao granular.
 */
class MaintenanceStatusHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAudit($user);
    }

    public function view(User $user, MaintenanceStatusHistory $record): bool
    {
        return $this->canAudit($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, MaintenanceStatusHistory $record): bool
    {
        return false;
    }

    public function delete(User $user, MaintenanceStatusHistory $record): bool
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

        return $tenant?->hasFeature('tabela_maintenance_status_histories') ?? false;
    }
}
