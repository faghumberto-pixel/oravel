<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\NotificationCategories;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * Ponto único de bloqueio: se a pessoa desligou a categoria em "Minhas notificações", o aviso
 * do sino nem é gravado. Retornar false cancela o envio (comportamento do Laravel).
 * Só mexe no canal "database" e em usuários internos -- e-mails/WhatsApp ao cliente não mudam.
 */
class SkipMutedNotification
{
    public function handle(NotificationSending $event): ?bool
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
            return null;
        }

        if (empty($event->notifiable->muted_notification_categories)) {
            return null;
        }

        $title = null;
        try {
            $data = method_exists($event->notification, 'toDatabase')
                ? $event->notification->toDatabase($event->notifiable)
                : [];
            $data = is_array($data) ? $data : [];
            $title = $data['title'] ?? $data['subject'] ?? null;
        } catch (\Throwable) {
            return null;
        }

        $category = NotificationCategories::classify(is_string($title) ? $title : null, $event->notification::class);

        return NotificationCategories::isMuted($event->notifiable, $category) ? false : null;
    }
}
