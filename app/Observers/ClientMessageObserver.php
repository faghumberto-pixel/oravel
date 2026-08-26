<?php

namespace App\Observers;

use App\Models\ClientMessage;
use App\Models\User;
use App\Notifications\ClientMessageReceivedNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifica só os User do tenant que enxergam a área da mensagem
 * (User::visibleClientMessageAreas() -- admin vê tudo, demais só a área
 * cuja Role dedicada eles têm). Mensagem sem área (legada, antes desta
 * feature) notifica todos, mesmo comportamento de antes. Mensagens
 * enviadas pelo User (sender_type='user') não disparam nada aqui -- o
 * Client não tem sino/push, só vê ao entrar no portal.
 */
class ClientMessageObserver
{
    public function created(ClientMessage $message): void
    {
        if ($message->sender_type !== ClientMessage::SENDER_CLIENT) {
            return;
        }

        $users = User::where('tenant_id', $message->tenant_id)
            ->get()
            ->filter(fn (User $user) => blank($message->area) || in_array($message->area, $user->visibleClientMessageAreas(), true));

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
