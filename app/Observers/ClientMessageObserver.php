<?php

namespace App\Observers;

use App\Models\ClientMessage;
use App\Models\User;
use App\Notifications\ClientMessageReceivedNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifica todos os User do tenant quando o Client manda mensagem --
 * mesmo padrão de VerificarVencimentosCommand/AnnouncementObserver (não
 * existe role dedicada pra "quem responde mensagem de cliente" hoje,
 * inventar uma seria escopo além do pedido). Mensagens enviadas pelo
 * User (sender_type='user') não disparam nada aqui -- o Client não tem
 * sino/push, só vê ao entrar no portal.
 */
class ClientMessageObserver
{
    public function created(ClientMessage $message): void
    {
        if ($message->sender_type !== ClientMessage::SENDER_CLIENT) {
            return;
        }

        $users = User::where('tenant_id', $message->tenant_id)->get();

        foreach ($users as $user) {
            try {
                $user->notify(new ClientMessageReceivedNotification($message));
            } catch (Throwable $e) {
                Log::warning('ClientMessageObserver: falha ao notificar user.', [
                    'user_id' => $user->id, 'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
