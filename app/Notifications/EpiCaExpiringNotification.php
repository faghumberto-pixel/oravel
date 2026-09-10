<?php

namespace App\Notifications;

use App\Models\EpiSpecification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Mesmo padrao de EmployeeCertificationExpiringNotification, mas pro CA
 * (Certificado de Aprovação) de um EPI do catálogo -- não é vinculado a
 * um colaborador especifico, o CA e' do modelo/tamanho do EPI.
 */
class EpiCaExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        private EpiSpecification $specification,
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
            'tenant_id' => $this->specification->tenant_id,
            'actions' => [
                [
                    'name' => 'visualizar',
                    'label' => 'Visualizar',
                    'url' => "/admin/materials/{$this->specification->material_id}/edit",
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
        $material = $this->specification->material->name ?? 'EPI';
        $ca = $this->specification->ca_number;
        $validade = $this->specification->ca_validade?->format('d/m/Y');

        $configuracoes = [
            'vencida' => [
                'title' => '❌ CA de EPI vencido',
                'body' => "O CA {$ca} de {$material} venceu em {$validade}. Novas entregas ficam bloqueadas até renovação do certificado.",
                'status' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ],
            'vencendo_7d' => [
                'title' => '🚨 CA de EPI vence em até 7 dias',
                'body' => "O CA {$ca} de {$material} vence em {$validade}.",
                'status' => 'danger',
                'icon' => 'heroicon-o-clock',
            ],
            'vencendo_15d' => [
                'title' => '⚠️ CA de EPI vence em até 15 dias',
                'body' => "O CA {$ca} de {$material} vence em {$validade}.",
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            'vencendo_30d' => [
                'title' => '⚠️ CA de EPI vence em até 30 dias',
                'body' => "O CA {$ca} de {$material} vence em {$validade}.",
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
        ];

        return $configuracoes[$this->tipoGatilho] ?? $configuracoes['vencendo_30d'];
    }
}
