<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ContaPagarNotification extends Notification
{
    use Queueable;

    private $conta;

    private $tipoGatilho; // 'lancamento', 'vencimento_hoje', 'vencendo_breve', 'atrasada'

    public function __construct($conta, $tipoGatilho)
    {
        $this->conta = $conta;
        $this->tipoGatilho = $tipoGatilho;
    }

    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /**
     * Estrutura os dados no formato nativo que o painel do Filament exige para leitura
     */
    public function toDatabase($notifiable): array
    {
        $config = $this->config();

        // Captura o slug do tenant de forma dinâmica para a rota
        $tenantSlug = $this->conta->tenant->slug ?? $notifiable->latest_tenant_slug ?? 'admin';

        return [
            'id' => Str::uuid()->toString(),
            'title' => $config['title'],
            'body' => $config['body'],
            'status' => $config['status'],
            'icon' => $config['icon'],
            'tenant_id' => $this->conta->tenant_id, // 🚨 ESSENCIAL PARA O MULTI-TENANT DO FILAMENT
            'actions' => [
                [
                    'name' => 'visualizar',
                    'label' => 'Visualizar',
                    'url' => "/admin/app/{$tenantSlug}/account-payables",
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
            ->action('Visualizar', url("/admin/app/{$tenantSlug}/account-payables"));

        return $config['status'] === 'danger' ? $mail->error() : $mail;
    }

    /**
     * Monta o mesmo config (title/body/status/icon) usado pelo banco e pelo e-mail.
     */
    private function config(): array
    {
        // 🚨 Atributos validados via Tinker:
        $descricao = $this->conta->description ?? 'Conta sem descrição';
        $valor = $this->conta->amount ?? 0;
        $vencimento = isset($this->conta->due_date) ? Carbon::parse($this->conta->due_date)->format('d/m/Y') : '';

        $configuracoes = [
            'lancamento' => [
                'title' => '📄 Nova Conta Lançada',
                'body' => "{$descricao} no valor de R$ ".number_format($valor, 2, ',', '.').' foi cadastrada.',
                'status' => 'info',
                'icon' => 'heroicon-o-document-text',
            ],
            'vencendo_breve' => [
                'title' => '⚠️ Vencimento Próximo',
                'body' => "A conta '{$descricao}' vence em breve ({$vencimento}). Valor: R$ ".number_format($valor, 2, ',', '.'),
                'status' => 'warning',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            'vencimento_hoje' => [
                'title' => '🚨 Conta Vence Hoje!',
                'body' => "Atenção imediata! '{$descricao}' vence hoje. Valor: R$ ".number_format($valor, 2, ',', '.'),
                'status' => 'danger',
                'icon' => 'heroicon-o-clock',
            ],
            'atrasada' => [
                'title' => '❌ Conta Vencida / Atrasada',
                'body' => "Alerta: '{$descricao}' vencida em {$vencimento} está pendente! Valor: R$ ".number_format($valor, 2, ',', '.'),
                'status' => 'danger',
                'icon' => 'heroicon-o-x-circle',
            ],
        ];

        return $configuracoes[$this->tipoGatilho] ?? $configuracoes['lancamento'];
    }
}
