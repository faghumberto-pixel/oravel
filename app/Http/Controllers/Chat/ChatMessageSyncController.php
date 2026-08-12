<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Livewire\Concerns\InteractsWithChat;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Endpoint dedicado a sincronizar mensagens de texto enviadas enquanto o
 * chat estava offline (fila local em IndexedDB, ver resources/js/chat/offline.js).
 * Não usa Livewire porque a fila é consumida pelo próprio JS da página ao
 * detectar volta de conexão, sem re-montar o componente -- um POST HTTP
 * simples é mais direto e mais fácil de re-tentar em caso de falha parcial.
 *
 * Escopo deliberadamente restrito a texto: imagem/áudio/documento offline
 * exigiriam guardar blobs binários grandes em IndexedDB e um fluxo de
 * upload multipart mais complexo para funcionar de forma confiável sem
 * rede -- fora do pedido original ("mesma lógica da OS: escrever e
 * sincronizar depois"), que é sobre não perder texto digitado em campo.
 */
class ChatMessageSyncController extends Controller
{
    use InteractsWithChat;

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ChatRoom::class);

        $validated = $request->validate([
            'recipient_id' => ['required', 'string'],
            'message' => ['required', 'string', 'max:2000'],
            'client_id' => ['required', 'string'], // id gerado no cliente, pra idempotência
        ]);

        // resolveOrCreateChatRoom() (InteractsWithChat) não valida por si só
        // que o destinatário é do mesmo tenant quando a sala ainda não
        // existe -- essa checagem existe hoje só porque o Livewire GlobalChat
        // só oferece IDs já filtrados via $this->users(). Este endpoint HTTP
        // aceita recipient_id livre do request, então precisa confirmar o
        // tenant explicitamente antes de criar/usar a sala (senão seria
        // possível criar uma ChatRoom cruzando dois tenants diferentes).
        $tenantId = Tenancy::current()?->id;
        $recipientBelongsToTenant = User::query()
            ->where('id', $validated['recipient_id'])
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $recipientBelongsToTenant) {
            throw new NotFoundHttpException('Destinatário inválido.');
        }

        // Idempotência: se essa mensagem (por client_id) já foi processada
        // antes -- ex. o cliente reenviou por timeout de rede sem receber
        // a resposta original -- devolve a mesma mensagem já criada em vez
        // de duplicar. Chave isolada por usuário (o client_id só precisa
        // ser único dentro da fila de um dispositivo/usuário).
        $idempotencyKey = 'chat-sync-'.Auth::id().'-'.$validated['client_id'];

        $messageId = Cache::remember($idempotencyKey, now()->addHours(24), function () use ($validated) {
            $room = $this->resolveOrCreateChatRoom($validated['recipient_id']);

            return $this->createChatMessage($room, $validated['message'])->id;
        });

        return response()->json([
            'ok' => true,
            'client_id' => $validated['client_id'],
            'message_id' => $messageId,
        ]);
    }
}
