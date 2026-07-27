<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(private Announcement $announcement) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->announcement->title)
            ->line($this->announcement->message);

        return $this->announcement->level === Announcement::LEVEL_CRITICAL
            ? $mail->error()
            : $mail;
    }

    public function toDatabase($notifiable): array
    {
        $status = match ($this->announcement->level) {
            Announcement::LEVEL_WARNING => 'warning',
            Announcement::LEVEL_CRITICAL => 'danger',
            default => 'info',
        };

        $icon = match ($this->announcement->level) {
            Announcement::LEVEL_WARNING => 'heroicon-o-exclamation-triangle',
            Announcement::LEVEL_CRITICAL => 'heroicon-o-exclamation-circle',
            default => 'heroicon-o-megaphone',
        };

        return [
            'id' => (string) Str::uuid(),
            // 'format' => 'filament' e' obrigatorio -- sem ele, o sino
            // (DatabaseNotifications::getNotificationsQuery(), que filtra
            // por 'data->format'='filament') nunca mostra esta notificacao,
            // mesmo com a linha certinha no banco. So' Notification::make()
            // ->sendToDatabase() (Filament) seta isso sozinho.
            'format' => 'filament',
            'title' => $this->announcement->title,
            'body' => $this->announcement->message,
            'status' => $status,
            'icon' => $icon,
        ];
    }
}
