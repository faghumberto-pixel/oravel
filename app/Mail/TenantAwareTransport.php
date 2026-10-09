<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\TenantMailSetting;
use App\Models\User;
use App\Support\Tenancy;
use Closure;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Escolhe a caixa de e-mail na hora de enviar: se a empresa dona da mensagem
 * cadastrou a caixa dela e ela está ativa, envia por ela (com o endereço dela como
 * remetente); caso contrário usa a caixa da plataforma. A empresa vem do destinatário
 * (usuário ou cliente cadastrado) ou, na falta, da empresa em uso.
 */
class TenantAwareTransport implements TransportInterface
{
    /** @param  Closure(TenantMailSetting): TransportInterface|null  $construtor */
    public function __construct(private TransportInterface $plataforma, private ?Closure $construtor = null) {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $config = $message instanceof Email ? $this->configDaEmpresa($message) : null;

        if (! $config) {
            return $this->plataforma->send($message, $envelope);
        }

        $remetente = new Address($config->from_address, (string) $config->from_name);
        $message->from($remetente);
        $message->sender($remetente);
        $message->returnPath($remetente);

        $destinatarios = array_merge($message->getTo(), $message->getCc(), $message->getBcc());

        return $this->transporteDa($config)->send($message, new Envelope($remetente, $destinatarios));
    }

    public function __toString(): string
    {
        return 'oravel-tenant-aware';
    }

    public function configDaEmpresa(Email $message): ?TenantMailSetting
    {
        $tenantId = null;

        foreach ($message->getTo() as $para) {
            $tenantId = User::withoutGlobalScopes()->where('email', $para->getAddress())->value('tenant_id')
                ?? Client::withoutGlobalScopes()->where('email', $para->getAddress())->value('tenant_id');

            if ($tenantId) {
                break;
            }
        }

        $tenantId ??= Tenancy::current()?->id;

        if (! $tenantId) {
            return null;
        }

        return TenantMailSetting::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('enabled', true)->first();
    }

    /** Transporte SMTP da caixa da empresa (usado também no teste de conexão). */
    public static function smtpDa(TenantMailSetting $config): EsmtpTransport
    {
        $transporte = new EsmtpTransport($config->host, $config->port, $config->security === 'ssl');

        if ($config->security === 'none') {
            $transporte->setAutoTls(false);
        }

        $transporte->setUsername($config->username);
        $transporte->setPassword((string) $config->password);

        return $transporte;
    }

    private function transporteDa(TenantMailSetting $config): TransportInterface
    {
        return $this->construtor ? ($this->construtor)($config) : static::smtpDa($config);
    }
}
