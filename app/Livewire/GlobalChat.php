<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithChat;
use App\Models\ChatRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

class GlobalChat extends Component
{
    use InteractsWithChat;
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
        return $this->chatDepartments();
    }

    #[Computed]
    public function users(): Collection
    {
        return $this->chatContacts($this->selectedDepartmentId);
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

        return $this->resolveOrCreateChatRoom($this->selectedUserId);
    }

    #[Computed]
    public function chatMessages(): Collection
    {
        $room = $this->chatRoom;

        if (! $room) {
            return collect();
        }

        return $this->chatMessagesForRoom($room);
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
        $this->markChatRoomRead($contactId);
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

        $this->createChatMessage($room, $this->newMessage ?: '', $this->temporaryImage, $this->temporaryDocument);

        $this->reset(['newMessage', 'temporaryImage', 'temporaryDocument']);

        unset($this->chatMessages, $this->users);

        $this->dispatch('scroll-to-bottom');
    }

    public function sendAudioMessage(string $base64Audio): void
    {
        $room = $this->chatRoom;

        if (! $room) {
            return;
        }

        $this->createChatAudioMessage($room, $base64Audio);

        unset($this->chatMessages, $this->users);

        $this->dispatch('scroll-to-bottom');
    }

    public function render()
    {
        return view('livewire.global-chat');
    }
}
