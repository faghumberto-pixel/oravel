<?php

namespace App\Notifications;

use App\Models\Nr13Inspection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Mesmo padrão de App\Notifications\EmployeeCertificationExpiringNotification.
 */
class Nr13InspectionExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Nr13Inspection $inspecao,
        private string $tipoGatilho, // 'vencida', 'vencendo_7d', 'vencendo_15d', 'vencendo_30d'
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        $config = $this->config();

        return [
            'id' => Str::uuid()->toString(),
            'format' => 'filament',
            'title' => $config['title'],
            'body' => $config['body'],
            'status' => $config['status'],
            'icon' => $config['icon'],
            'tenant_id' => $this->inspecao->tenant_id,
            'actions' => [
                [
                    'name' => 'visualizar',
                    'label' => 'Visualizar',
                    'url' => "/admin/assets/{$this->inspecao->asset_id}/edit",
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
        $config = $this->config();

        $mail = (new MailMessage)
            ->subject($config['title'])
            ->line($config['body']);

        return $config['status'] === 'danger' ? $mail->error() : $mail;
    }

    private function config(): array
    {
        $equipamento = $this->inspecao->asset->name ?? 'Equipamento';
        $tipoInsp = Nr13Inspection::tipoLabels()[$this->inspecao->tipo] ?? $this->inspecao->tipo;
        $proxima = $this->inspecao->data_proxima_inspecao?->format('d/m/Y');

        $configuracoes = [
            'vencida' => [
                'title' => '❌ Inspeção NR-13 vencida',
                'body' => "Inspeção {$tipoInsp} de {$equipamento} venceu em {$proxima}.",
                'status' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ],
            'vencendo_7d' => [
                'title' => '🚨 Inspeção NR-13 vence em até 7 dias',
                'body' => "Inspeção {$tipoInsp} de {$equipamento} vence em {$proxima}.",
                'status' => 'danger',
                'icon' => 'heroicon-o-clock',
            ],
            'vencendo_15d' => [
                'title' => '⚠️ Inspeção NR-13 vence em até 15 dias',
                'body' => "Inspeção {$tipoInsp} de {$equipamento} vence em {$proxima}.",
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            'vencendo_30d' => [
                'title' => '⚠️ Inspeção NR-13 vence em até 30 dias',
                'body' => "Inspeção {$tipoInsp} de {$equipamento} vence em {$proxima}.",
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
        ];

        return $configuracoes[$this->tipoGatilho] ?? $configuracoes['vencendo_30d'];
    }
}
