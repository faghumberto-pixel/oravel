<?php

namespace App\Livewire;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class GlobalChat extends Component
{
    use WithFileUploads;

    public ?string $selectedUserId = null;
    public $selectedDepartmentId = null;
    public string $newMessage = '';
    public $temporaryImage = null;

    public function mount(): void
    {
        $this->selectedUserId = $this->users->first()['id'] ?? null;
        if ($this->selectedUserId) {
            $this->markRoomRead($this->selectedUserId);
        }
    }

    #[Computed]
    public function departments(): Collection
    {
        if (blank(Auth::user()?->tenant_id)) {
            return collect();
        }

        return Department::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function users(): Collection
    {
        $tenantId = Auth::user()?->tenant_id;

        if (blank($tenantId)) {
            return collect();
        }

        $myId = Auth::id();

        // Salas pessoais em que o usuario participa
        $myRoomIds = ChatRoom::query()
            ->where('type', 'pessoal')
            ->whereHas('users', fn ($q) => $q->where('users.id', $myId))
            ->pluck('id');

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
                'id'         => $user->id,
                'name'       => $user->name,
                'department' => $user->department?->name,
                'is_online'  => $user->isOnline(),
                'unread'     => (int) ($unreadByUser[$user->id] ?? 0),
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

        $authId   = Auth::id();
        $tenantId = Auth::user()?->tenant_id;

        $room = ChatRoom::query()
            ->where('type', 'pessoal')
            ->where('tenant_id', $tenantId)
            ->whereHas('users', fn ($q) => $q->where('users.id', $authId))
            ->whereHas('users', fn ($q) => $q->where('users.id', $this->selectedUserId))
            ->first();

        if (! $room) {
            $room = ChatRoom::create([
                'tenant_id' => $tenantId,
                'type'      => 'pessoal',
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

        // Conversa aberta -> marca as recebidas como lidas
        $room->messages()
            ->whereNull('read_at')
            ->where('user_id', '!=', $authId)
            ->update(['read_at' => now()]);

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

                $audio = $media->first(fn ($m) => ! str_starts_with($m->mime_type, "image/"));

                return [
                    'id'          => $msg->id,
                    'is_mine'     => $msg->user_id === $authId,
                    'is_read'     => $msg->read_at !== null,
                    'message'     => $msg->message,
                    'attachments' => $images,
                    'audio'       => $audio?->getUrl(),
                    'created_at'  => $msg->created_at->format('d/m/Y H:i'),
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
                ->update(['read_at' => now()]);
        }
    }

    public function updatedTemporaryImage(): void
    {
        $this->validate(['temporaryImage' => 'image|max:5120']);
        $this->sendMessage();
    }

    public function sendMessage(): void
    {
        $this->validate(['newMessage' => ['nullable', 'string', 'max:2000']]);

        $room = $this->chatRoom;

        if (! $room) {
            return;
        }

        if (! $this->newMessage && ! $this->temporaryImage) {
            return;
        }

        $chatMessage = ChatMessage::create([
            'chat_room_id' => $room->id,
            'tenant_id'    => $room->tenant_id,
            'user_id'      => Auth::id(),
            'message'      => $this->newMessage ?: '',
        ]);

        if ($this->temporaryImage) {
            $chatMessage->addMedia($this->temporaryImage->getRealPath())
                ->usingFileName($this->temporaryImage->getClientOriginalName())
                ->toMediaCollection('chat_attachments');
        }

        $this->reset(['newMessage', 'temporaryImage']);

        unset($this->chatMessages, $this->users);

        $this->dispatch('scroll-to-bottom');
    }

    public function sendAudioMessage(string $base64Audio): void
    {
        $room = $this->chatRoom;

        if (! $room || empty($base64Audio)) {
            return;
        }

        $data   = preg_replace('/^data:audio\/\w+;base64,/', '', $base64Audio);
        $binary = base64_decode($data);

        $tmpPath = tempnam(sys_get_temp_dir(), 'audio_') . '.webm';
        file_put_contents($tmpPath, $binary);

        $chatMessage = ChatMessage::create([
            'chat_room_id' => $room->id,
            'tenant_id'    => $room->tenant_id,
            'user_id'      => Auth::id(),
            'message'      => '',
        ]);

        $chatMessage->addMedia($tmpPath)
            ->usingFileName('audio-' . Str::uuid() . '.webm')
            ->toMediaCollection('chat_attachments');

        unset($this->chatMessages, $this->users);

        $this->dispatch('scroll-to-bottom');
    }

    public function render()
    {
        return view('livewire.global-chat');
    }
}
