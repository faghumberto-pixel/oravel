<?php

namespace App\Notifications;

use App\Mail\SignatureLinkMail;
use App\Models\DocumentSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SignatureLinkMailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private DocumentSignature $signature
    ) {
        $this->onQueue('default');
        $this->delay(now()->addSeconds(5));
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable)
    {
        return new SignatureLinkMail($this->signature);
    }
}
