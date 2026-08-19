<?php

namespace App\Livewire\Concerns;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Department;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Logica de dados do chat pessoal (chat_rooms tipo 'pessoal' + chat_messages),
 * compartilhada entre App\Livewire\GlobalChat (pagina "Chat Interno" cheia,
 * com documentos/audio/export PDF) e App\Livewire\ChatWidget (bolha
 * flutuante persistente, estilo LinkedIn). Metodos recebem os parametros
 * explicitamente (nao dependem de propriedades de uma classe especifica),
 * pra poderem ser chamados por qualquer um dos dois componentes.
 */
trait InteractsWithChat
{
    protected function chatDepartments(): Collection
    {
        if (blank(Tenancy::current()?->id)) {
            return collect();
        }

        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Lista de contatos do tenant com contagem de nao lidas. Como efeito
     * colateral, marca como "entregues" as mensagens recebidas ainda nao
     * marcadas -- meu cliente fez poll agora, entao chegou ate ele (nao
     * necessariamente lida -- so quando a conversa e' aberta).
     */
    protected function chatContacts(?string $departmentId = null): Collection
    {
        $tenantId = Tenancy::current()?->id;

        if (blank($tenantId)) {
            return collect();
        }

        $myId = Auth::id();

        $myRoomIds = ChatRoom::query()
            ->where('type', 'pessoal')
            ->whereHas('users', fn ($q) => $q->where('users.id', $myId))
            ->pluck('id');

        ChatMessage::query()
            ->whereIn('chat_room_id', $myRoomIds)
            ->whereNull('delivered_at')
            ->where('user_id', '!=', $myId)
            ->update(['delivered_at' => now()]);

        $unreadByUser = ChatMessage::query()
            ->whereIn('chat_room_id', $myRoomIds)
            ->whereNull('read_at')
            ->where('user_id', '!=', $myId)
            ->selectRaw('user_id, count(*) as c')
            ->groupBy('user_id')
            ->pluck('c', 'user_id');

        $lastMessageByRoom = ChatMessage::query()
            ->whereIn('chat_room_id', $myRoomIds)
            ->selectRaw('DISTINCT ON (chat_room_id) chat_room_id, message, created_at')
            ->orderBy('chat_room_id')
            ->orderByDesc('created_at')
            ->get()
            ->keyBy('chat_room_id');

        $roomIdByUser = ChatRoom::query()
            ->where('type', 'pessoal')
            ->whereIn('id', $myRoomIds)
            ->with(['users' => fn ($q) => $q->where('users.id', '!=', $myId)])
            ->get()
            ->mapWithKeys(fn (ChatRoom $room) => [$room->users->first()?->id => $room->id])
            ->filter();

        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $myId)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'last_seen', 'department_id', 'avatar_url'])
            ->map(function (User $user) use ($unreadByUser, $lastMessageByRoom, $roomIdByUser) {
                $roomId = $roomIdByUser[$user->id] ?? null;
                $lastMessage = $roomId ? $lastMessageByRoom->get($roomId) : null;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'department' => $user->department?->name,
                    'avatar_url' => $user->getFilamentAvatarUrl(),
                    'is_online' => $user->isOnline(),
                    'unread' => (int) ($unreadByUser[$user->id] ?? 0),
                    'last_message' => $lastMessage?->message,
                    'last_message_at' => $lastMessage?->created_at,
                ];
            });
    }

    protected function resolveOrCreateChatRoom(string $selectedUserId): ChatRoom
    {
        $authId = Auth::id();
        $tenantId = Tenancy::current()?->id;

        $selectedUserBelongsToTenant = User::query()
            ->where('id', $selectedUserId)
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $selectedUserBelongsToTenant) {
            abort(403);
        }

        $room = ChatRoom::query()
            ->where('type', 'pessoal')
            ->where('tenant_id', $tenantId)
            ->whereHas('users', fn ($q) => $q->where('users.id', $authId))
            ->whereHas('users', fn ($q) => $q->where('users.id', $selectedUserId))
            ->first();

        if (! $room) {
            $room = ChatRoom::create([
                'tenant_id' => $tenantId,
                'type' => 'pessoal',
            ]);

            $room->users()->syncWithoutDetaching([$authId, $selectedUserId]);
        }

        return $room;
    }

    /**
     * Marca as mensagens da sala como lidas (e entregues, se ainda nao
     * estivessem) e devolve o historico mapeado pra exibicao.
     */
    protected function chatMessagesForRoom(ChatRoom $room): Collection
    {
        $authId = Auth::id();

        $room->messages()
            ->whereNull('read_at')
            ->where('user_id', '!=', $authId)
            ->update([
                'read_at' => now(),
                'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())'),
            ]);

        return $room->messages()
            ->with('media')
            ->oldest()
            ->get()
            ->map(function (ChatMessage $msg) use ($authId) {
                $media = $msg->getMedia('chat_attachments');

                $images = $media
                    ->filter(fn ($m) => str_starts_with($m->mime_type, 'image/'))
                    ->map(fn ($m) => $m->getUrl())
                    ->values();

                $audio = $media->first(fn ($m) => str_starts_with($m->mime_type, 'audio/'));

                $documents = $media
                    ->filter(fn ($m) => ! str_starts_with($m->mime_type, 'image/') && ! str_starts_with($m->mime_type, 'audio/'))
                    ->map(fn ($m) => ['name' => $m->file_name, 'url' => $m->getUrl(), 'size' => $m->human_readable_size])
                    ->values();

                return [
                    'id' => $msg->id,
                    'is_mine' => $msg->user_id === $authId,
                    'is_read' => $msg->read_at !== null,
                    'is_delivered' => $msg->delivered_at !== null,
                    'message' => $msg->message,
                    'attachments' => $images,
                    'audio' => $audio?->getUrl(),
                    'transcript' => $msg->transcript,
                    'documents' => $documents,
                    'created_at' => $msg->created_at->format('d/m/Y H:i'),
                ];
            });
    }

    protected function markChatRoomRead(string $contactId): void
    {
        $authId = Auth::id();
        $tenantId = Tenancy::current()?->id;

        $room = ChatRoom::query()
            ->where('type', 'pessoal')
            ->where('tenant_id', $tenantId)
            ->whereHas('users', fn ($q) => $q->where('users.id', $authId))
            ->whereHas('users', fn ($q) => $q->where('users.id', $contactId))
            ->first();

        if ($room) {
            $room->messages()
                ->whereNull('read_at')
                ->where('user_id', '!=', $authId)
                ->update([
                    'read_at' => now(),
                    'delivered_at' => DB::raw('COALESCE(delivered_at, NOW())'),
                ]);
        }
    }

    protected function createChatMessage(ChatRoom $room, string $text = '', $image = null, $document = null): ChatMessage
    {
        $chatMessage = ChatMessage::create([
            'chat_room_id' => $room->id,
            'tenant_id' => $room->tenant_id,
            'user_id' => Auth::id(),
            'message' => $text,
        ]);

        if ($image) {
            $chatMessage->addMedia($image->getRealPath())
                ->usingFileName($image->getClientOriginalName())
                ->toMediaCollection('chat_attachments');
        }

        if ($document) {
            $chatMessage->addMedia($document->getRealPath())
                ->usingFileName($document->getClientOriginalName())
                ->toMediaCollection('chat_attachments');
        }

        return $chatMessage;
    }

    /**
     * $transcript vem pronto do client-side (Web Speech API nativa do
     * navegador, ver script de chatComponent() em global-chat.blade.php --
     * mesmo mecanismo já usado no wizard de campo da OS, x-mobile.voice-note).
     * NÃO despacha mais TranscribeChatAudio/AudioTranscriptionService
     * (Whisper via OPENAI_API_KEY): essa chave nunca foi configurada em
     * PROD, então a transcrição do chat nunca funcionou, silenciosamente
     * (achado 2026-08-13). Web Speech API não tem esse custo/dependência.
     */
    protected function createChatAudioMessage(ChatRoom $room, string $base64Audio, string $transcript = ''): ?ChatMessage
    {
        if (empty($base64Audio)) {
            return null;
        }

        $data = preg_replace('/^data:audio\/\w+;base64,/', '', $base64Audio);
        $binary = base64_decode($data);

        $tmpPath = tempnam(sys_get_temp_dir(), 'audio_').'.webm';
        file_put_contents($tmpPath, $binary);

        $chatMessage = ChatMessage::create([
            'chat_room_id' => $room->id,
            'tenant_id' => $room->tenant_id,
            'user_id' => Auth::id(),
            'message' => '',
            'transcript' => $transcript !== '' ? $transcript : null,
        ]);

        $chatMessage->addMedia($tmpPath)
            ->usingFileName('audio-'.Str::uuid().'.webm')
            ->toMediaCollection('chat_attachments');

        return $chatMessage;
    }

    protected function chatAttachmentSummary(ChatMessage $chatMessage): string
    {
        $media = $chatMessage->getMedia('chat_attachments')->first();

        return match (true) {
            ! $media => '',
            str_starts_with($media->mime_type, 'image/') => '📷 Imagem',
            str_starts_with($media->mime_type, 'audio/') => '🎤 Áudio',
            default => '📎 '.$media->file_name,
        };
    }

    /**
     * Total de mensagens nao lidas em todas as salas do usuario logado --
     * usado pelo badge do widget flutuante.
     */
    protected function totalUnreadChatMessages(): int
    {
        $tenantId = Tenancy::current()?->id;
        if (blank($tenantId)) {
            return 0;
        }

        $myId = Auth::id();

        $myRoomIds = ChatRoom::query()
            ->where('type', 'pessoal')
            ->whereHas('users', fn ($q) => $q->where('users.id', $myId))
            ->pluck('id');

        return ChatMessage::query()
            ->whereIn('chat_room_id', $myRoomIds)
            ->whereNull('read_at')
            ->where('user_id', '!=', $myId)
            ->count();
    }
}
