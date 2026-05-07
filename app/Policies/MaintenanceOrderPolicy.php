<?php

namespace App\Policies;

use App\Models\MaintenanceOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaintenanceOrderPolicy
{
    use HandlesAuthorization;

    /**
     * O 'before' permite que o admin tenha acesso total a tudo.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ler_ordem_servico');
    }

    public function view(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        // Verifica a permissão E se a OS pertence à mesma empresa do técnico
        return $user->can('ler_ordem_servico') && $user->company_id === $maintenanceOrder->company_id;
    }

    public function create(User $user): bool
    {
        return $user->can('criar_ordem_servico');
    }

    public function update(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        // Verifica a permissão E se a OS pertence à mesma empresa
        return $user->can('editar_ordem_servico') && $user->company_id === $maintenanceOrder->company_id;
    }

    public function delete(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return $user->can('excluir_ordem_servico') && $user->company_id === $maintenanceOrder->company_id;
    }

    public function restore(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return $user->can('excluir_ordem_servico') && $user->company_id === $maintenanceOrder->company_id;
    }

    public function forceDelete(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return $user->can('excluir_ordem_servico') && $user->company_id === $maintenanceOrder->company_id;
    }
}