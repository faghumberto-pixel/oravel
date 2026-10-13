<?php

namespace App\Observers;

use App\Models\ClientFeedback;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Avisa os administradores da empresa (sino do painel) quando um cliente registra reclamação, sugestão ou elogio. */
class ClientFeedbackObserver
{
    public function created(ClientFeedback $feedback): void
    {
        $tipo = ClientFeedback::typeLabels()[$feedback->type] ?? 'Registro';
        $cliente = $feedback->client?->name ?? 'Cliente';

        try {
            $admins = User::withoutGlobalScopes()->where('tenant_id', $feedback->tenant_id)->get()->filter(fn (User $u) => $u->isAdmin());

            Notification::make()
                ->title("{$tipo} de {$cliente}")
                ->body("Protocolo {$feedback->protocol}: {$feedback->subject}")
                ->icon($feedback->type === ClientFeedback::TYPE_RECLAMACAO ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-chat-bubble-left-right')
                ->sendToDatabase($admins);
        } catch (Throwable $e) {
            Log::warning('ClientFeedbackObserver: falha ao avisar a equipe.', ['feedback_id' => $feedback->id, 'error' => $e->getMessage()]);
        }
    }
}
