<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            // O canal 'mail' da AnnouncementNotification envia sincrono
            // (QUEUE_CONNECTION=sync) -- uma falha de SMTP pra 1 usuario
            // (endereco invalido, limite de bounce do provedor, etc) nao
            // pode travar o aviso pros outros nem impedir o Announcement
            // de ser criado. Descoberto 2026-07-25: limite de bounce da
            // Titan Email interrompeu a criacao de avisos globais no meio
            // do loop.
            try {
                $user->notify(new AnnouncementNotification($announcement));
            } catch (Throwable $e) {
                Log::warning('Falha ao notificar usuario sobre aviso', [
                    'announcement_id' => $announcement->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
