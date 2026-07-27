<?php

namespace App\Notifications;

use App\Filament\Pages\CaixaDeEmail;
use App\Models\EmailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Aviso de e-mail interno novo (entre usuarios do mesmo tenant) -- canal
 * database apenas, sem 'mail'. Destinatario interno nao usa SMTP nesta v1
 * (ver EmailMessage::send()); a notificacao e' o unico jeito dele saber
 * que chegou algo, via o sino ja existente no topbar.
 */
class NewInternalEmailNotification extends Notification
{
    use Queueable;

    public function __construct(private EmailMessage $emailMessage) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'id' => (string) Str::uuid(),
            // 'format' => 'filament' e' obrigatorio -- sem ele, o sino do
            // Filament (DatabaseNotifications::getNotificationsQuery(),
            // que filtra por 'data->format'='filament') nunca mostra esta
            // notificacao, mesmo com a linha certinha no banco. So'
            // Notification::make()->sendToDatabase() (Filament) seta isso
            // sozinho; uma Notification crua do Laravel (como esta) precisa
            // declarar na mao.
            'format' => 'filament',
            'title' => 'Novo e-mail de '.($this->emailMessage->fromUser?->name ?? 'alguém'),
            'body' => $this->emailMessage->subject,
            'status' => 'info',
            'icon' => 'heroicon-o-envelope',
            'actions' => [
                [
                    'name' => 'view',
                    'label' => 'Abrir',
                    'url' => CaixaDeEmail::getUrl(['folder' => 'recebidos', 'message' => $this->emailMessage->id]),
                    'isOutlined' => false,
                    'isDisabled' => false,
                    'shouldOpenUrlInNewTab' => false,
                    'view' => 'filament-actions::button-action',
                ],
            ],
        ];
    }
}
