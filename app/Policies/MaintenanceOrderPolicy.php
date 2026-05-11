<?php

namespace App\Policies;

use App\Models\MaintenanceOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaintenanceOrderPolicy
{
    use HandlesAuthorization;

    /**
     * O 'before' permite acesso total ao admin.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        // Se não tiver Shield/Permissões configuradas ainda, use 'true' para testar
        return $user->can('ler_ordem_servico') || true; 
    }

    public function view(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        // AJUSTE: Usar tenant_id para bater com o Multi-tenancy
        return ($user->can('ler_ordem_servico') || true) && $user->tenant_id === $maintenanceOrder->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('criar_ordem_servico') || true;
    }

    public function update(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        // AJUSTE: Usar tenant_id
        return ($user->can('editar_ordem_servico') || true) && $user->tenant_id === $maintenanceOrder->tenant_id;
    }

    public function delete(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return ($user->can('excluir_ordem_servico') || true) && $user->tenant_id === $maintenanceOrder->tenant_id;
    }

    public function restore(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return ($user->can('excluir_ordem_servico') || true) && $user->tenant_id === $maintenanceOrder->tenant_id;
    }

    public function forceDelete(User $user, MaintenanceOrder $maintenanceOrder): bool
    {
        return ($user->can('excluir_ordem_servico') || true) && $user->tenant_id === $maintenanceOrder->tenant_id;
    }
}