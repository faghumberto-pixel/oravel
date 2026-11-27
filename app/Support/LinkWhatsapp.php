<?php

namespace App\Support;

/** Link "clique para conversar" do WhatsApp (wa.me) com a mensagem já escrita. Mesmo tratamento de número da proposta do CRM. */
class LinkWhatsapp
{
    /** Número brasileiro só com dígitos e DDI 55; null se não parece um telefone (menos de 10 dígitos). */
    public static function numero(?string $telefone): ?string
    {
        $numero = preg_replace('/\D+/', '', (string) $telefone);
        if (strlen((string) $numero) < 10) {
            return null;
        }

        return strlen($numero) <= 11 ? '55'.$numero : $numero;
    }

    public static function para(?string $telefone, string $mensagem): ?string
    {
        $numero = self::numero($telefone);

        return $numero ? 'https://wa.me/'.$numero.'?text='.rawurlencode($mensagem) : null;
    }
}
