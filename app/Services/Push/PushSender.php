<?php

namespace App\Services\Push;

/** Envia 1 push a 1 aparelho. Interface para os testes não dependerem da rede. */
interface PushSender
{
    /**
     * @param  array{endpoint: string, p256dh: string, auth: string}  $subscription
     * @return bool|null true = entregue, false = aparelho expirado (apagar), null = falha temporária
     */
    public function send(array $subscription, string $payload): ?bool;
}
