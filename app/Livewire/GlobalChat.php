<?php

namespace App\Livewire;

use App\Livewire\Concerns\InteractsWithChat;
use App\Models\ChatRoom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * #[Layout] só tem efeito quando o componente é montado como raiz de uma
 * rota (ex: Route::get('/chat', GlobalChat::class), ver routes/chat.php)
 * -- é ignorado quando incluído via <livewire:global-chat /> dentro de
 * outra página (caso do painel, resources/views/filament/pages/chat.blade.php),
 * então é seguro declarar aqui sem afetar o uso existente dentro do admin.
 */
#[Layout('layouts.chat')]
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

    /**
     * Snapshot do total de não lidas no poll anterior -- usado só pra
     * detectar "chegou mensagem nova desde o último poll" e disparar o
     * evento JS de notificação (som/badge/título da aba). Ver
     * resources/js/chat-app.js.
     */
    public int $lastKnownUnreadTotal = 0;

    public function mount(): void
    {
        // GET /chat (rota raiz, ver routes/chat.php) fica fora do middleware
        // chat.auth de propósito -- um redirect HTTP no start_url do PWA
        // trava tela preta na primeira abertura em vários WebViews Android/
        // iOS (bug real 2026-08-12). Guest cai aqui em vez de nunca chegar
        // no mount: sem autorizar/carregar nada, render() decide mostrar o
        // login. Quando incluído via <livewire:global-chat/> dentro do
        // painel (sempre autenticado por causa do middleware do Filament),
        // este branch nunca é atingido.
        if (! Auth::check()) {
            return;
        }

        // Defesa em profundidade: Chat::canAccess() ja bloqueia a rota/menu,
        // mas este componente e' o que de fato executa a logica -- sem essa
        // checagem aqui, ele nunca teve nenhuma autorizacao propria (achado
        // de auditoria de permissoes, 2026-07-08).
        Gate::authorize('viewAny', ChatRoom::class);

        $this->selectedUserId ??= $this->users->first()['id'] ?? null;
        if ($this->selectedUserId) {
            $this->markRoomRead($this->selectedUserId);
        }

        $this->lastKnownUnreadTotal = $this->totalUnreadChatMessages();
    }

    /**
     * Chamado a cada wire:poll (ver global-chat.blade.php). Compara o total
     * de não lidas com o snapshot anterior -- se subiu, dispara o evento JS
     * que toca som/mostra notificação/atualiza o badge (chat-app.js).
     *
     * Nota: se a conversa aberta no momento é justamente a que recebeu a
     * mensagem nova, chatMessagesForRoom() já marca como lida ao renderizar
     * -- então o total pode não subir nesse caso específico (comportamento
     * esperado: não notificar de uma mensagem que o usuário já está vendo).
     */
    public function checkForNewMessages(): void
    {
        unset($this->users);

        $currentTotal = $this->totalUnreadChatMessages();

        if ($currentTotal > $this->lastKnownUnreadTotal) {
            $latestSender = $this->users->sortByDesc('last_message_at')->first();

            $this->dispatch(
                'chat-new-message',
                senderName: $latestSender['name'] ?? 'Oravel Chat',
                preview: $latestSender['last_message'] ?? null,
                unreadTotal: $currentTotal,
            );
        }

        $this->lastKnownUnreadTotal = $currentTotal;
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

    public function sendAudioMessage(string $base64Audio, string $transcript = ''): void
    {
        $room = $this->chatRoom;

        if (! $room) {
            return;
        }

        $this->createChatAudioMessage($room, $base64Audio, $transcript);

        unset($this->chatMessages, $this->users);

        $this->dispatch('scroll-to-bottom');
    }

    public function render()
    {
        // Guest chegando por GET /chat direto (ver mount() acima): mesmo
        // response 200, sem redirect -- só o conteúdo muda pro formulário
        // de login. Nenhuma das #[Computed] acima (users/chatRoom/...) é
        // avaliada nesse branch, porque a view de login não as referencia.
        if (! Auth::check()) {
            return view('chat.auth._login-form');
        }

        return view('livewire.global-chat');
    }
}
