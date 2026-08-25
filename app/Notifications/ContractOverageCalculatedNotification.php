<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ContractOverageCalculatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private int $pendentes,
        private int $conflitos,
        private Carbon $periodStart
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'id' => (string) Str::uuid(),
            // 'format' => 'filament' é obrigatório -- sem ele o sino não
            // mostra a notificação mesmo com a linha certa no banco (ver
            // App\Notifications\MaintenanceDueNotification).
            'format' => 'filament',
            'title' => 'Excedente de franquia calculado — '.$this->periodStart->translatedFormat('F/Y'),
            'body' => $this->body(),
            'status' => $this->conflitos > 0 ? 'warning' : 'info',
            'icon' => 'heroicon-o-currency-dollar',
            'actions' => [
                [
                    'name' => 'view',
                    'label' => 'Revisar',
                    'url' => route('filament.admin.resources.rental-overage-charges.index'),
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
            ->subject('Excedente de franquia de horas — '.$this->periodStart->translatedFormat('F/Y'))
            ->line($this->body())
            ->action('Revisar Excedentes', route('filament.admin.resources.rental-overage-charges.index'));
    }

    private function body(): string
    {
        $parts = [];

        if ($this->pendentes > 0) {
            $parts[] = "{$this->pendentes} contrato(s) com excedente pendente de revisão";
        }

        if ($this->conflitos > 0) {
            $parts[] = "{$this->conflitos} conflito(s) precisando de revisão manual (contratos sobrepostos ou leitura de horímetro insuficiente)";
        }

        return implode('. ', $parts).'.';
    }
}
