<?php

namespace App\Services\Push;

use App\Models\PushSubscription;
use App\Models\User;

/** Dispara o push do app do colaborador para todos os aparelhos de um usuário. Nunca derruba quem chamou. */
class PushDoColaborador
{
    public function __construct(private PushSender $sender) {}

    public function enviar(User $user, string $titulo, ?string $corpo, string $url): int
    {
        $aparelhos = PushSubscription::withoutGlobalScopes()->where('user_id', $user->id)->get();
        $entregues = 0;

        foreach ($aparelhos as $aparelho) {
            try {
                $resultado = $this->sender->send(
                    ['endpoint' => $aparelho->endpoint, 'p256dh' => $aparelho->p256dh, 'auth' => $aparelho->auth],
                    json_encode(['title' => $titulo, 'body' => (string) $corpo, 'url' => $url], JSON_UNESCAPED_UNICODE),
                );
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            if ($resultado === true) {
                $entregues++;
            } elseif ($resultado === false) {
                $aparelho->delete(); // aparelho desinstalou/revogou: não tenta mais
            }
        }

        return $entregues;
    }
}
