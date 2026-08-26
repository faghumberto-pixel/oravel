<?php

namespace App\Notifications;

use App\Models\AccountReceivable;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Paralela a ContaReceberNotification (que é pra User do tenant, link
 * /admin/...) -- esta é dedicada ao Client, link /cliente/meu-financeiro.
 * Disparada só para o tipo 'atrasada' (ver VerificarVencimentosCommand),
 * não replica 'vencendo_breve' pro Client, evita ruído.
 */
class ClientAccountReceivableOverdueNotification extends Notification
{
    use Queueable;

    public function __construct(
        private AccountReceivable $conta,
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $vencimento = $this->conta->due_date?->format('d/m/Y');

        return (new MailMessage)
            ->subject('Conta em atraso')
            ->line("A conta \"{$this->conta->description}\" venceu em {$vencimento} e ainda consta como pendente.")
            ->action('Ver meu financeiro', url('/cliente/meu-financeiro'))
            ->line('Se o pagamento já foi feito, desconsidere este aviso.');
    }
}
