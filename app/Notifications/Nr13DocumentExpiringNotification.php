<?php

namespace App\Notifications;

use App\Models\Nr13Document;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Mesmo padrão de App\Notifications\EmployeeCertificationExpiringNotification.
 */
class Nr13DocumentExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Nr13Document $documento,
        private string $tipoGatilho, // 'vencido', 'vencendo_7d', 'vencendo_15d', 'vencendo_30d'
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
            'tenant_id' => $this->documento->tenant_id,
            'actions' => [
                [
                    'name' => 'visualizar',
                    'label' => 'Visualizar',
                    'url' => "/admin/assets/{$this->documento->asset_id}/edit",
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
        $equipamento = $this->documento->asset->name ?? 'Equipamento';
        $tipoDoc = Nr13Document::tipoLabels()[$this->documento->tipo] ?? $this->documento->tipo;
        $validade = $this->documento->data_validade?->format('d/m/Y');

        $configuracoes = [
            'vencido' => [
                'title' => '❌ Documento NR-13 vencido',
                'body' => "{$tipoDoc} de {$equipamento} venceu em {$validade}.",
                'status' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ],
            'vencendo_7d' => [
                'title' => '🚨 Documento NR-13 vence em até 7 dias',
                'body' => "{$tipoDoc} de {$equipamento} vence em {$validade}.",
                'status' => 'danger',
                'icon' => 'heroicon-o-clock',
            ],
            'vencendo_15d' => [
                'title' => '⚠️ Documento NR-13 vence em até 15 dias',
                'body' => "{$tipoDoc} de {$equipamento} vence em {$validade}.",
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            'vencendo_30d' => [
                'title' => '⚠️ Documento NR-13 vence em até 30 dias',
                'body' => "{$tipoDoc} de {$equipamento} vence em {$validade}.",
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
        ];

        return $configuracoes[$this->tipoGatilho] ?? $configuracoes['vencendo_30d'];
    }
}
