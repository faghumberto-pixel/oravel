<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(private Announcement $announcement) {}

    public function via($notifiable): array
    {
        return ['database'];
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
            'title' => $this->announcement->title,
            'body' => $this->announcement->message,
            'status' => $status,
            'icon' => $icon,
        ];
    }
}
