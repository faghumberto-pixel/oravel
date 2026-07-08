<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithChat;
use App\Models\ChatRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Bolha de chat flutuante persistente (estilo LinkedIn), injetada em toda
 * pagina do painel admin via render hook BODY_END (ver AdminPanelProvider).
 * Reaproveita a mesma logica de dados de App\Livewire\GlobalChat (a pagina
 * "Chat Interno" cheia, com documentos/audio/export PDF, continua existindo
 * sem mudanca) atraves da trait InteractsWithChat.
 *
 * Persistencia entre paginas: o painel admin NAO usa Filament::spa()
 * (cada navegacao e' reload completo), entao o componente e' remontado do
 * zero a cada pagina -- nao ha estado de servidor pra persistir sozinho.
 * O estado visual (minimizado/expandido, conversa aberta) e' salvo no
 * sessionStorage do navegador pelo Alpine (ver chat-widget.blade.php) e
 * restaurado via restoreState() no proximo mount, dando a impressao de
 * continuidade entre paginas.
 */
class ChatWidget extends Component
{
    use InteractsWithChat;
    use WithFileUploads;

    public bool $isExpanded = false;

    public ?string $selectedUserId = null;

    public string $search = '';

    public string $newMessage = '';

    public $temporaryImage = null;

    public function mount(): void
    {
        // Mesma defesa em profundidade do GlobalChat -- widget nao aparece
        // (Blade condiciona a renderizacao), mas o componente em si tambem
        // nao deve executar nada se o tenant nao tiver o modulo.
        if (! Gate::check('viewAny', ChatRoom::class)) {
            return;
        }
    }

    /**
     * Chamado pelo Alpine (x-init) com o que foi salvo no sessionStorage,
     * restaurando o estado visual apos o reload de pagina.
     */
    public function restoreState(?string $userId, bool $expanded): void
    {
        $this->isExpanded = $expanded;

        if ($userId && $this->users->firstWhere('id', $userId)) {
            $this->selectConversation($userId);
        }
    }

    public function toggleExpanded(): void
    {
        $this->isExpanded = ! $this->isExpanded;
    }

    public function backToList(): void
    {
        $this->selectedUserId = null;
    }

    #[Computed]
    public function users(): Collection
    {
        if (! Gate::check('viewAny', ChatRoom::class)) {
            return collect();
        }

        $contacts = $this->chatContacts();

        if (trim($this->search) === '') {
            return $contacts;
        }

        $term = mb_strtolower(trim($this->search));

        return $contacts->filter(fn (array $c) => str_contains(mb_strtolower($c['name']), $term))->values();
    }

    #[Computed]
    public function totalUnread(): int
    {
        if (! Gate::check('viewAny', ChatRoom::class)) {
            return 0;
        }

        return $this->totalUnreadChatMessages();
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

    public function selectConversation(string $userId): void
    {
        $this->selectedUserId = $userId;
        $this->markChatRoomRead($userId);
        $this->reset(['newMessage', 'temporaryImage']);

        unset($this->chatRoom, $this->chatMessages, $this->users, $this->totalUnread);

        $this->dispatch('chat-widget-scroll-to-bottom');
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

        $this->createChatMessage($room, $this->newMessage ?: '', $this->temporaryImage);

        $this->reset(['newMessage', 'temporaryImage']);

        unset($this->chatMessages, $this->users, $this->totalUnread);

        $this->dispatch('chat-widget-scroll-to-bottom');
    }

    public function render()
    {
        return view('livewire.chat-widget');
    }
}
