<?php

namespace App\Listeners;

use App\Models\Client;
use App\Models\EmailMessage;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Email;

/**
 * Todo e-mail que o sistema dispara (aviso, acesso ao portal, link de assinatura,
 * orçamento...) fica registrado na Caixa de E-mail da empresa, como Enviado ou
 * Falhou. Antes só os escritos pela própria Caixa apareciam.
 *
 * Funciona por eventos do Laravel, então cobre qualquer Mail::send e qualquer
 * Notification por e-mail sem mexer em cada ponto. O registro nasce como "Falhou"
 * e vira "Enviado" só quando o servidor de e-mail aceita a mensagem -- se o envio
 * der erro, ele já está certo. E-mails da própria Caixa não são duplicados.
 */
class RegistrarEnvioDeEmail
{
    public function sending(MessageSending $event): void
    {
        try {
            $message = $event->message;

            if (EmailMessage::$enviandoPeloModulo || ! $message instanceof Email) {
                return;
            }

            $para = collect($message->getTo())->map(fn ($a) => $a->getAddress())->values()->all();
            $tenantId = $this->tenantDe($para);

            if (! $tenantId || $para === []) {
                return; // e-mail da plataforma (sem empresa): não há caixa de empresa para mostrar
            }

            $usuario = Auth::guard('web')->user();
            $texto = $message->getTextBody() ?: trim(html_entity_decode(strip_tags((string) $message->getHtmlBody())));

            $registro = EmailMessage::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'from_user_id' => $usuario && $usuario->tenant_id === $tenantId ? $usuario->id : null,
                'to_external' => $para,
                'subject' => mb_substr((string) $message->getSubject(), 0, 250),
                'body' => mb_substr((string) $texto, 0, 20000),
                'status' => EmailMessage::STATUS_FALHOU,
                'error' => 'O servidor de e-mail não aceitou a mensagem (ou não respondeu). Veja Configurações de e-mail do sistema.',
                'origem' => 'sistema',
                'tipo' => $this->tipoDe($event),
            ]);

            // O Symfony copia a mensagem ao enviar; o cabeçalho acompanha a cópia e liga as duas pontas.
            $message->getHeaders()->addTextHeader('X-Oravel-Registro', $registro->id);
        } catch (\Throwable $e) {
            Log::warning('Não foi possível registrar o e-mail na Caixa.', ['erro' => $e->getMessage()]);
        }
    }

    public function sent(MessageSent $event): void
    {
        try {
            $message = $event->message;
            $id = $message instanceof Email ? $message->getHeaders()->get('X-Oravel-Registro')?->getBodyAsString() : null;

            if (! $id) {
                return;
            }

            EmailMessage::withoutGlobalScopes()->whereKey($id)->update([
                'status' => EmailMessage::STATUS_ENVIADO,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Não foi possível confirmar o e-mail na Caixa.', ['erro' => $e->getMessage()]);
        }
    }

    /** Empresa do destinatário (usuário ou cliente); senão, a empresa atual. */
    private function tenantDe(array $enderecos): ?string
    {
        foreach ($enderecos as $endereco) {
            $tenantId = User::withoutGlobalScopes()->where('email', $endereco)->value('tenant_id')
                ?? Client::withoutGlobalScopes()->where('email', $endereco)->value('tenant_id');

            if ($tenantId) {
                return $tenantId;
            }
        }

        return Tenancy::current()?->id;
    }

    private function tipoDe(MessageSending $event): ?string
    {
        $notificacao = $event->data['__laravel_notification'] ?? null;

        return $notificacao ? class_basename($notificacao) : null;
    }
}
