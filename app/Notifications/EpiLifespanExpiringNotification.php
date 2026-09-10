<?php

namespace App\Notifications;

use App\Models\EpiDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Vida util (tempo de uso recomendado) de um EPI ja entregue, separada da
 * validade do CA -- so' sugere troca, nunca bloqueia (diferente do CA
 * vencido, que a trigger de banco bloqueia de verdade).
 */
class EpiLifespanExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        private EpiDelivery $delivery,
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
            'tenant_id' => $this->delivery->tenant_id,
            'actions' => [
                [
                    'name' => 'visualizar',
                    'label' => 'Visualizar',
                    'url' => "/admin/employees/{$this->delivery->employee_id}/edit",
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

        return (new MailMessage)
            ->subject($config['title'])
            ->line($config['body']);
    }

    private function config(): array
    {
        $colaborador = $this->delivery->employee->name ?? 'Colaborador';
        $material = $this->delivery->material->name ?? 'EPI';
        $diasEmUso = $this->delivery->daysInUse();

        $configuracoes = [
            'vencida' => [
                'title' => '⚠️ Vida útil do EPI estourada',
                'body' => "{$material} de {$colaborador} está em uso há {$diasEmUso} dias, além da vida útil recomendada. Avalie a troca.",
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            'vencendo_7d' => [
                'title' => 'Vida útil do EPI vence em até 7 dias',
                'body' => "{$material} de {$colaborador} está próximo do fim da vida útil recomendada ({$diasEmUso} dias em uso).",
                'status' => 'warning',
                'icon' => 'heroicon-o-clock',
            ],
            'vencendo_15d' => [
                'title' => 'Vida útil do EPI vence em até 15 dias',
                'body' => "{$material} de {$colaborador} está próximo do fim da vida útil recomendada ({$diasEmUso} dias em uso).",
                'status' => 'warning',
                'icon' => 'heroicon-o-clock',
            ],
            'vencendo_30d' => [
                'title' => 'Vida útil do EPI vence em até 30 dias',
                'body' => "{$material} de {$colaborador} está próximo do fim da vida útil recomendada ({$diasEmUso} dias em uso).",
                'status' => 'warning',
                'icon' => 'heroicon-o-clock',
            ],
        ];

        return $configuracoes[$this->tipoGatilho] ?? $configuracoes['vencendo_30d'];
    }
}
