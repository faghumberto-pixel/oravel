<?php

namespace App\Policies;

use App\Models\AbcMatrixHistory;
use App\Models\User;
use App\Support\Tenancy;

/**
 * Dado de auditoria (quem mudou o nível ABC de qual Ativo, quando) --
 * mesmo padrão de MaintenanceStatusHistoryPolicy: estritamente admin do
 * tenant (ou super admin), sem delegar por permissão granular.
 */
class AbcMatrixHistoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAudit($user);
    }

    public function view(User $user, AbcMatrixHistory $record): bool
    {
        return $this->canAudit($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AbcMatrixHistory $record): bool
    {
        return false;
    }

    public function delete(User $user, AbcMatrixHistory $record): bool
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

        return $tenant?->hasFeature('tabela_abc_matrix_histories') ?? false;
    }
}
