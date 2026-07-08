<?php

namespace App\Livewire;

use App\Filament\Pages\Chat;
use App\Jobs\TranscribeChatAudio;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Department;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class GlobalChat extends Component
{
    use WithFileUploads;

    #[Url]
    public ?string $selectedUserId = null;

    public $selectedDepartmentId = null;

    public string $newMessage = '';

    public $temporaryImage = null;

    public $temporaryDocument = null;

    public function mount(): void
    {
        // Defesa em profundidade: Chat::canAccess() ja bloqueia a rota/menu,
        // mas este componente e' o que de fato executa a logica -- sem essa
        // checagem aqui, ele nunca teve nenhuma autorizacao propria (achado
        // de auditoria de permissoes, 2026-07-08).
        Gate::authorize('viewAny', ChatRoom::class);

        $this->selectedUserId ??= $this->users->first()['id'] ?? null;
        if ($this->selectedUserId) {
            $this->markRoomRead($this->selectedUserId);
        }
    }

    #[Computed]
    public function departments(): Collection
    {
        if (blank(Tenancy::current()?->id)) {
            return collect();
        }

        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function users(): Collection
    {
        $tenantId = Tenancy::current()?->id;

        if (blank($tenantId)) {
            return collect();
        }

        $myId = Auth::id();

        // Salas pessoais em que o usuario participa
        $myRoomIds = ChatRoom::query()
            ->where('type', 'pessoal')
            ->whereHas('users', fn ($q) => $q->where('users.id', $myId))
            ->pluck('id');

        // Meu cliente fez poll agora -> mensagens recebidas contam como entregues
        // (nao necessariamente lidas -- so quando a conversa e aberta).
        ChatMessage::query()
            ->whereIn('chat_room_id', $myRoomIds)
            ->whereNull('delivered_at')
            ->where('user_id', '!=', $myId)
            ->update(['delivered_at' => now()]);

        // Mensagens nao lidas por remetente
        $unreadByUser = ChatMessage::query()
            ->whereIn('chat_room_id', $myRoomIds)
            ->whereNull('read_at')
            ->where('user_id', '!=', $myId)
            ->selectRaw('user_id, count(*) as c')
            ->groupBy('user_id')
            ->pluck('c', 'user_id');

        return User::query()
            ->where('tenant_id', $tenantId)
            ->where('id', '!=', $myId)
            ->when($this->selectedDepartmentId, fn ($q) => $q->where('department_id', $this->selectedDepartmentId))
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'last_seen', 'department_id'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'department' => $user->department?->name,
                'is_online' => $user->isOnline(),
                'unread' => (int) ($unreadByUser[$user->id] ?? 0),
            ]);
    }

    public function filterDepartment($id = null): void
    {
        $this->selectedDepartmentId = $id;
        unset($this->users);
    }

    #[Computed]
    public function selectedUser(): ?array
    {
        if (! $this->selectedUserId) {
            return null;
        }

        return $this->users->firstWhere('id', $this->selectedUserId);
    }

    #[Computed]
    public function chatRoom(): ?ChatRoom
    {
        if (! $this->selectedUserId) {
            return null;
        }

        $authId = Auth::id();
        $tenantId = Tenancy::current()?->id;

        $room = ChatRoom::query()
            ->where('type', 'pessoal')
            ->where('tenant_id', $tenantId)
            ->whereHas('users', fn ($q) => $q->where('users.id', $authId))
            ->whereHas('users', fn ($q) => $q->where('users.id', $this->selectedUserId))
            ->first();

        if (! $room) {
            $room = ChatRoom::create([
                'tenant_id' => $tenantId,
                'type' => 'pessoal',
            ]);

            $room->users()->syncWithoutDetaching([$authId, $this->selectedUserId]);
        }

        return $room;
    }

    #[Computed]
    public function chatMessages(): Collection
    {
        $room = $this->chatRoom;

        if (! $room) {
            return collect();
        }

        $authId = Auth::id();

        // Conversa aberta -> marca as recebidas como lidas (e entregues, se
        // ainda nao estivessem -- nao da pra ler sem ter sido entregue).
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

    public function selectUser(string $userId): void
    {
        $this->selectedUserId = $userId;
        $this->markRoomRead($userId);
        $this->reset(['newMessage', 'temporaryImage']);

        unset($this->chatRoom, $this->chatMessages, $this->users);

        $this->dispatch('scroll-to-bottom');
    }

    protected function markRoomRead(string $contactId): void
    {
        $authId = Auth::id();

        $room = ChatRoom::query()
            ->where('type', 'pessoal')
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

    public function updatedTemporaryImage(): void
    {
        $this->validate(['temporaryImage' => 'image|max:5120']);
        $this->sendMessage();
    }

    public function updatedTemporaryDocument(): void
    {
        $this->validate(['temporaryDocument' => 'file|max:10240|mimes:pdf,doc,docx,xls,xlsx,txt,csv']);
        $this->sendMessage();
    }

    public function sendMessage(): void
    {
        $this->validate(['newMessage' => ['nullable', 'string', 'max:2000']]);

        $room = $this->chatRoom;

        if (! $room) {
            return;
        }

        if (! $this->newMessage && ! $this->temporaryImage && ! $this->temporaryDocument) {
            return;
        }

        $chatMessage = ChatMessage::create([
            'chat_room_id' => $room->id,
            'tenant_id' => $room->tenant_id,
            'user_id' => Auth::id(),
            'message' => $this->newMessage ?: '',
        ]);

        if ($this->temporaryImage) {
            $chatMessage->addMedia($this->temporaryImage->getRealPath())
                ->usingFileName($this->temporaryImage->getClientOriginalName())
                ->toMediaCollection('chat_attachments');
        }

        if ($this->temporaryDocument) {
            $chatMessage->addMedia($this->temporaryDocument->getRealPath())
                ->usingFileName($this->temporaryDocument->getClientOriginalName())
                ->toMediaCollection('chat_attachments');
        }

        $this->notifyRecipient($chatMessage);

        $this->reset(['newMessage', 'temporaryImage', 'temporaryDocument']);

        unset($this->chatMessages, $this->users);

        $this->dispatch('scroll-to-bottom');
    }

    protected function notifyRecipient(ChatMessage $chatMessage): void
    {
        $recipient = User::find($this->selectedUserId);

        if (! $recipient) {
            return;
        }

        Notification::make()
            ->title('Nova mensagem de '.Auth::user()->name)
            ->body(Str::limit($chatMessage->message ?: $this->attachmentSummary($chatMessage), 60))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->iconColor('warning')
            ->actions([
                Action::make('ver')
                    ->label('Ver conversa')
                    ->url(Chat::getUrl(['selectedUserId' => Auth::id()]))
                    ->button(),
            ])
            ->sendToDatabase($recipient);
    }

    protected function attachmentSummary(ChatMessage $chatMessage): string
    {
        $media = $chatMessage->getMedia('chat_attachments')->first();

        return match (true) {
            ! $media => '',
            str_starts_with($media->mime_type, 'image/') => '📷 Imagem',
            str_starts_with($media->mime_type, 'audio/') => '🎤 Áudio',
            default => '📎 '.$media->file_name,
        };
    }

    public function sendAudioMessage(string $base64Audio): void
    {
        $room = $this->chatRoom;

        if (! $room || empty($base64Audio)) {
            return;
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
        ]);

        $chatMessage->addMedia($tmpPath)
            ->usingFileName('audio-'.Str::uuid().'.webm')
            ->toMediaCollection('chat_attachments');

        TranscribeChatAudio::dispatch($chatMessage);

        $this->notifyRecipient($chatMessage);

        unset($this->chatMessages, $this->users);

        $this->dispatch('scroll-to-bottom');
    }

    public function render()
    {
        return view('livewire.global-chat');
    }
}
