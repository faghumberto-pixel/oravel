<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;

/**
 * Ao criar um aviso (ja ativo), notifica de imediato os usuarios do
 * tenant-alvo (ou de todos os tenants, se target_tenant_id for nulo) --
 * so uma vez, na criacao, para nao reenviar a cada edicao.
 */
class AnnouncementObserver
{
    public function created(Announcement $announcement): void
    {
        if (! $announcement->is_active) {
            return;
        }

        $this->notifyUsers($announcement);
    }

    public function updated(Announcement $announcement): void
    {
        if ($announcement->wasChanged('is_active') && $announcement->is_active) {
            $this->notifyUsers($announcement);
        }
    }

    private function notifyUsers(Announcement $announcement): void
    {
        $users = User::query()
            ->whereNotNull('tenant_id')
            ->when($announcement->target_tenant_id, fn ($q) => $q->where('tenant_id', $announcement->target_tenant_id))
            ->get();

        foreach ($users as $user) {
            $user->notify(new AnnouncementNotification($announcement));
        }
    }
}
