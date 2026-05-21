<?php

namespace App\Policies;

use App\Models\MaintenanceOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaintenanceOrderPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ler_ordens_de_servico');
    }

    public function view(User $user, MaintenanceOrder $order): bool
    {
        return $user->can('ler_ordens_de_servico') && $user->tenant_id === $order->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('criar_ordens_de_servico');
    }

    public function update(User $user, MaintenanceOrder $order): bool
    {
        return $user->can('editar_ordens_de_servico') && $user->tenant_id === $order->tenant_id;
    }

    public function delete(User $user, MaintenanceOrder $order): bool
    {
        return $user->can('excluir_ordens_de_servico') && $user->tenant_id === $order->tenant_id;
    }
}