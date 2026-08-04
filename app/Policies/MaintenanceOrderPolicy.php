<?php

namespace App\Policies;

use App\Models\MaintenanceOrder;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Auth\Access\HandlesAuthorization;

class MaintenanceOrderPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        // Super admin acima de tudo, inclusive da trava comercial --
        // mesma ordem de AbstractPolicy::check(). Antes a trava comercial
        // era checada primeiro, o que so nao vazava porque
        // Tenant::hasFeature() ja tem seu proprio bypass redundante de
        // super admin (achado de auditoria de permissoes, 2026-07-08).
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Trava comercial: nega para todos, inclusive admin do tenant, se o
        // plano nao incluir esse modulo (mesmo padrao de AbstractPolicy::check()).
        if (($tenant = Tenancy::current()) && ! $tenant->hasFeature('tabela_maintenance_orders')) {
            return false;
        }

        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->can('ler_ordem_servico');
    }

    public function view(User $user, MaintenanceOrder $order): bool
    {
        return $user->can('ler_ordem_servico') && $user->tenant_id === $order->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('criar_ordem_servico');
    }

    public function update(User $user, MaintenanceOrder $order): bool
    {
        // Admin e super admin (já permitidos pelo before())
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->tenant_id !== $order->tenant_id) {
            return false;
        }

        // Técnico atribuído pode editar sua própria execução -- MAS só
        // enquanto a O.S. ainda não foi enviada. Depois de "Concluída",
        // ele perde acesso de edição; só quem supervisiona o departamento
        // do técnico (ou admin, já coberto acima) pode reabrir/editar a
        // partir daí. Pedido explicito do usuario 2026-08-04: uma vez
        // enviada, edição exige autorização do supervisor.
        if ($order->technician_id === $user->id && $order->status !== 'Concluída') {
            return true;
        }

        // Supervisor do departamento do técnico atribuído -- mesmo critério
        // já usado em routes/web.php e PainelGestao::canAccess() pra
        // distinguir "técnico puro" de quem supervisiona alguém.
        $technicianDepartmentId = $order->technician?->department_id;
        if ($technicianDepartmentId && in_array($technicianDepartmentId, $user->supervisedDepartmentIds(), true)) {
            return true;
        }

        // Permissão granular para outros
        return $user->can('editar_ordem_servico');
    }

    public function delete(User $user, MaintenanceOrder $order): bool
    {
        return $user->can('excluir_ordem_servico') && $user->tenant_id === $order->tenant_id;
    }
}
