<?php

namespace App\Listeners;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Push\PushDoColaborador;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Todo aviso que vai para o sino (database) também vira push no celular de quem ativou as notificações no app do
 * colaborador. Avisos de categorias desligadas em "Minhas notificações" nem chegam aqui (SkipMutedNotification).
 */
class EnviarPushDoAviso
{
    // Não se chama handle() de propósito: o Laravel descobre listeners 'handle' sozinho e o registro manual duplicaria o envio.
    public function enviar(NotificationSent $event): void
    {
        if ($event->channel !== 'database' || ! $event->notifiable instanceof User) {
            return;
        }

        try {
            if (! PushSubscription::withoutGlobalScopes()->where('user_id', $event->notifiable->id)->exists()) {
                return;
            }

            $dados = method_exists($event->notification, 'toDatabase') ? $event->notification->toDatabase($event->notifiable) : [];
            $titulo = $dados['title'] ?? null;

            if (! is_string($titulo) || $titulo === '') {
                return;
            }

            app(PushDoColaborador::class)->enviar(
                $event->notifiable,
                $titulo,
                is_string($dados['body'] ?? null) ? strip_tags($dados['body']) : null,
                route('colab.notificacoes', [], false),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
