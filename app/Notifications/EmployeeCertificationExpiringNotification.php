<?php

namespace App\Notifications;

use App\Models\EmployeeCertification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class EmployeeCertificationExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        private EmployeeCertification $certification,
        private string $tipoGatilho, // 'vencida', 'vencendo_7d', 'vencendo_15d', 'vencendo_30d'
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * 'format' => 'filament' e' obrigatorio -- sem ele o sino nunca mostra
     * esta notificacao (mesmo padrao de ContaPagarNotification).
     */
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
            'tenant_id' => $this->certification->tenant_id,
            'actions' => [
                [
                    'name' => 'visualizar',
                    'label' => 'Visualizar',
                    'url' => "/admin/employees/{$this->certification->employee_id}/edit",
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
        $colaborador = $this->certification->employee->name ?? 'Colaborador';
        $norma = $this->certification->norma;
        $validade = $this->certification->data_validade?->format('d/m/Y');

        $configuracoes = [
            'vencida' => [
                'title' => '❌ Certificação vencida',
                'body' => "{$norma} de {$colaborador} venceu em {$validade}. Alocações que dependem dela foram bloqueadas.",
                'status' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ],
            'vencendo_7d' => [
                'title' => '🚨 Certificação vence em até 7 dias',
                'body' => "{$norma} de {$colaborador} vence em {$validade}.",
                'status' => 'danger',
                'icon' => 'heroicon-o-clock',
            ],
            'vencendo_15d' => [
                'title' => '⚠️ Certificação vence em até 15 dias',
                'body' => "{$norma} de {$colaborador} vence em {$validade}.",
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            'vencendo_30d' => [
                'title' => '⚠️ Certificação vence em até 30 dias',
                'body' => "{$norma} de {$colaborador} vence em {$validade}.",
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
        ];

        return $configuracoes[$this->tipoGatilho] ?? $configuracoes['vencendo_30d'];
    }
}
