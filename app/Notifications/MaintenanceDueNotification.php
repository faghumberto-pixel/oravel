<?php

namespace App\Notifications;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MaintenanceDueNotification extends Notification
{
    use Queueable;

    public function __construct(private Asset $asset, private array $status) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'id' => (string) Str::uuid(),
            // 'format' => 'filament' e' obrigatorio -- sem ele, o sino
            // (DatabaseNotifications::getNotificationsQuery(), que filtra
            // por 'data->format'='filament') nunca mostra esta notificacao,
            // mesmo com a linha certinha no banco. So' Notification::make()
            // ->sendToDatabase() (Filament) seta isso sozinho.
            'format' => 'filament',
            'title' => 'Preventiva vencida: '.$this->asset->name,
            'body' => $this->body(),
            'status' => 'warning',
            'icon' => 'heroicon-o-exclamation-triangle',
            'actions' => [
                [
                    'name' => 'view',
                    'label' => 'Visualizar',
                    'url' => route('filament.admin.resources.assets.edit', $this->asset->id),
                    'isOutlined' => false,
                    'isDisabled' => false,
                    'shouldOpenUrlInNewTab' => false,
                    'view' => 'filament-actions::button-action',
                ],
            ],
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Preventiva vencida: '.$this->asset->name)
            ->line($this->body())
            ->action('Ver Ativo', route('filament.admin.resources.assets.edit', $this->asset->id));
    }

    private function body(): string
    {
        return sprintf(
            'Patrimônio %s — %s horas de atraso (previsto para %sh, horímetro atual %sh).',
            $this->asset->patrimonio,
            number_format($this->status['overdue_hours'], 1),
            number_format($this->status['due_at_hours'], 1),
            number_format((float) $this->asset->horimetro_atual, 1),
        );
    }
}
