<?php

namespace App\Domain\Intelligence\Trial\Services;

use Illuminate\Support\Facades\Log;

/**
 * Envio de WhatsApp pela Oravel ao cliente do teste. AINDA NÃO HÁ número/API da Oravel para mensagens de sistema (o WhatsApp
 * existente é por empresa-cliente): esta classe não envia nada e devolve false. Quando houver, estenda e troque com
 * app()->bind(EnviadorWhatsApp::class, ...).
 */
class EnviadorWhatsApp
{
    public function disponivel(): bool
    {
        return false;
    }

    public function enviar(string $e164, string $mensagem): bool
    {
        Log::info('Intelligence trial: WhatsApp da Oravel não configurado, mensagem não enviada.');

        return false;
    }
}
