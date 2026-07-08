<?php

namespace App\Policies;

use App\Models\User;

/**
 * Agendamento e auto-servico do proprio tecnico -- nao um recurso comercial
 * gated por plano/permissao granular como os modelos que usam
 * AbstractPolicy::check() (Client, Asset, etc). Por isso o corpo nao fica
 * vazio como nos outros Policies: qualquer usuario do tenant pode ver/criar
 * o proprio compromisso; editar/excluir fica restrito ao dono ou a um admin.
 */
class AppointmentPolicy extends AbstractPolicy
{
    public function viewAny(User $user, $model = null): bool
    {
        return true;
    }

    public function view(User $user, $model): bool
    {
        return $user->isSuperAdmin() || $user->isAdmin() || $model->technician_id === $user->id;
    }

    public function create(User $user, $model = null): bool
    {
        return true;
    }

    public function update(User $user, $model): bool
    {
        return $this->view($user, $model);
    }

    public function delete(User $user, $model): bool
    {
        return $this->view($user, $model);
    }
}
