<?php

namespace App\Support;

use App\Mail\PlanilhaManutencaoMail;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Iscas (materiais gratuitos) do site: origem do lead => e-mail que entrega o material.
 * O site (HostGator) só entrega o lead ao app (POST /api/inbound/leads); quem envia o e-mail é o app, que
 * tem a caixa de e-mail configurada e funcionando -- o site não guarda senha de SMTP.
 *
 * Nova isca: 1 linha aqui + o Mailable + o item em public_html/lead-isca.php + a página em /materiais-gratuitos/.
 */
class Iscas
{
    /** @return array<string, callable(array): Mailable> */
    public static function mails(): array
    {
        return [
            'isca-planilha-manutencao' => fn (array $d) => new PlanilhaManutencaoMail((string) ($d['name'] ?? '')),
        ];
    }

    /**
     * Envia o material da isca. null = a origem não é uma isca (nada a fazer);
     * true/false = o e-mail saiu ou não. Envio síncrono de propósito: o site precisa saber se saiu.
     */
    public static function enviar(array $data): ?bool
    {
        $monta = self::mails()[$data['origem'] ?? ''] ?? null;
        if ($monta === null) {
            return null;
        }

        try {
            Mail::to($data['email'])->sendNow($monta($data));

            return true;
        } catch (\Throwable $e) {
            Log::error('Isca: e-mail não enviado', ['origem' => $data['origem'], 'email' => $data['email'], 'erro' => $e->getMessage()]);

            return false;
        }
    }
}
