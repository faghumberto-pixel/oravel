<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Espelha ContaPagarNotification -- pedido do usuário 2026-08-25:
 * financeiro:verificar-vencimentos só cobria AccountPayable, então o único
 * alerta proativo de vencimento existente nunca avisava sobre o lado do
 * que a empresa tem A RECEBER, só o que tem a pagar.
 */
class ContaReceberNotification extends Notification
{
    use Queueable;

    private $conta;

    private $tipoGatilho; // 'vencimento_hoje', 'vencendo_breve', 'atrasada'

    public function __construct($conta, $tipoGatilho)
    {
        $this->conta = $conta;
        $this->tipoGatilho = $tipoGatilho;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    public function toDatabase($notifiable): array
    {
        $config = $this->config();
        $tenantSlug = $this->conta->tenant->slug ?? $notifiable->latest_tenant_slug ?? 'admin';

        return [
            'id' => Str::uuid()->toString(),
            'format' => 'filament',
            'title' => $config['title'],
            'body' => $config['body'],
            'status' => $config['status'],
            'icon' => $config['icon'],
            'tenant_id' => $this->conta->tenant_id,
            'actions' => [
                [
                    'name' => 'visualizar',
                    'label' => 'Visualizar',
                    'url' => "/admin/app/{$tenantSlug}/account-receivables",
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
        $tenantSlug = $this->conta->tenant->slug ?? $notifiable->latest_tenant_slug ?? 'admin';

        $mail = (new MailMessage)
            ->subject($config['title'])
            ->line($config['body'])
            ->action('Visualizar', url("/admin/app/{$tenantSlug}/account-receivables"));

        return $config['status'] === 'danger' ? $mail->error() : $mail;
    }

    private function config(): array
    {
        $descricao = $this->conta->description ?? 'Conta sem descrição';
        $valor = $this->conta->amount ?? 0;
        $vencimento = isset($this->conta->due_date) ? Carbon::parse($this->conta->due_date)->format('d/m/Y') : '';

        $configuracoes = [
            'vencendo_breve' => [
                'title' => '⚠️ Recebimento Próximo',
                'body' => "'{$descricao}' vence em breve ({$vencimento}). Valor a receber: R$ ".number_format($valor, 2, ',', '.'),
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            'vencimento_hoje' => [
                'title' => '🚨 Recebimento Vence Hoje!',
                'body' => "Atenção! '{$descricao}' vence hoje. Valor a receber: R$ ".number_format($valor, 2, ',', '.'),
                'status' => 'danger',
                'icon' => 'heroicon-o-clock',
            ],
            'atrasada' => [
                'title' => '❌ Recebimento Atrasado',
                'body' => "Alerta: '{$descricao}' vencida em {$vencimento} ainda não foi recebida! Valor: R$ ".number_format($valor, 2, ',', '.'),
                'status' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ],
        ];

        return $configuracoes[$this->tipoGatilho] ?? $configuracoes['vencendo_breve'];
    }
}
