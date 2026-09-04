<?php

namespace App\Notifications;

use App\Models\DocumentSignature;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SignatureLinkWhatsAppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private DocumentSignature $signature
    ) {
        $this->onQueue('default');
        $this->delay(now()->addSeconds(5));
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $link = route('signature.sign', ['token' => $this->signature->token]);
        $documentType = match ($this->signature->signable_type) {
            'App\\Models\\Contract' => 'Contrato de Locação',
            'App\\Models\\MaintenanceOrder' => 'Ordem de Serviço',
            default => 'Documento',
        };

        // Prepara mensagem para envio via WhatsApp
        $message = $this->buildWhatsAppMessage($link, $documentType);

        // Se telefone está configurado, envia
        if ($this->signature->signer_phone) {
            $this->sendViaWhatsApp($message);
        }

        return [
            'type' => 'signature_link',
            'signature_id' => $this->signature->id,
            'message' => $message,
            'link' => $link,
        ];
    }

    /**
     * Constrói mensagem formatada para WhatsApp.
     */
    private function buildWhatsAppMessage(string $link, string $documentType): string
    {
        return <<<MSG
👋 Olá {$this->signature->signer_name}!

📋 Você foi solicitado para assinar um *{$documentType}* eletronicamente.

✍️ *Como assinar:*
Clique no link abaixo para acessar o formulário de assinatura.

🔗 Acesse: {$link}

⏰ *O link expira em 30 dias*

🔐 Todos os dados são criptografados e seguros.

Se não foi você quem solicitou, ignore esta mensagem.

---
Equipe Oravel
MSG;
    }

    /**
     * Envia mensagem via WhatsApp (integração com serviço externo).
     *
     * Pode usar:
     * - Twilio WhatsApp API
     * - Evolution API
     * - Z-API
     * - Venom Bot
     * - Backend próprio com WhatsApp Business API
     */
    private function sendViaWhatsApp(string $message): void
    {
        try {
            // Exemplo usando Evolution API (adaptar conforme necessário)
            $phone = $this->sanitizePhone($this->signature->signer_phone);

            if (!$phone) {
                \Log::warning('Telefone inválido para WhatsApp', [
                    'signature_id' => $this->signature->id,
                    'phone' => $this->signature->signer_phone,
                ]);
                return;
            }

            // Implementação específica de acordo com o serviço escolhido
            // Por ora, loga apenas
            \Log::info('WhatsApp a enviar', [
                'phone' => $phone,
                'message_length' => strlen($message),
                'signature_id' => $this->signature->id,
            ]);

            // TODO: Implementar integração real com Evolution API / Twilio / etc.
            // dispatch(new \App\Jobs\SendWhatsAppMessage($phone, $message));

        } catch (\Throwable $e) {
            \Log::error('Erro ao enviar WhatsApp', [
                'signature_id' => $this->signature->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sanitiza número de telefone para formato internacional.
     * Converte (11) 99999-9999 ou 11999999999 para 5511999999999
     */
    private function sanitizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        // Remove caracteres especiais
        $phone = preg_replace('/\D/', '', $phone);

        // Se começar com 0, remove (padrão brasileiro)
        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        // Se tiver menos de 11 dígitos, é inválido
        if (strlen($phone) < 11) {
            return null;
        }

        // Se tiver mais de 13 dígitos, provavelmente já tem código de país
        if (strlen($phone) > 13) {
            return null;
        }

        // Se tiver 11 dígitos (formato local), adiciona código do Brasil (55)
        if (strlen($phone) === 11) {
            $phone = '55' . $phone;
        }

        return $phone;
    }
}
