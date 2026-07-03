<?php

namespace App\Filament\Pages;

use App\Filament\Attributes\BelongsToFeature;
use Filament\Pages\Page;
use App\Models\User;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\MaintenanceOrder;
use App\Models\Asset;
use App\Models\MaterialRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Filament\Notifications\Notification;

#[BelongsToFeature('users')]
class Chat extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationGroup = 'COMUNICAÇÃO';
    protected static ?string $navigationLabel = 'Chat Interno';
    protected static ?string $title = '';

    protected static string $view = 'filament.pages.chat';

    #[Url]
    public ?string $activeChatId = null;
    public ?string $chatRoomId = null;
    public string $newMessage = '';
    public string $searchQuery = '';

    public string $contextType = 'geral';
    public ?string $contextId = null;

    public $photo = null;
    public $document = null;
    public int $refreshCounter = 0;

    public function getMaxContentWidth(): string
    {
        return 'full';
    }

    /**
     * Chat liberado para todos os usuarios autenticados,
     * independente da feature do plano do tenant.
     */
    public static function canAccess(): bool
    {
        return true;
    }

    public function mount()
    {
        $user = Auth::user();

        if (! $user) {
            abort(403, 'Acesso não autorizado aos Canais de Chat corporativos.');
        }

        if ($this->activeChatId) {
            $this->resolveChatRoom();
        }
    }

    public function autoRefresh()
    {
        $this->refreshCounter++;
        $this->updateMyPresence();
    }

    protected function updateMyPresence()
    {
        if (Auth::check()) {
            Cache::put('user-online-' . Auth::id(), true, 30);
        }
    }

    public function selectChat($userId)
    {
        $this->activeChatId = $userId;
        $this->reset('newMessage', 'photo', 'document', 'contextId');
        $this->contextType = 'geral';
        $this->resolveChatRoom();
        $this->dispatch('scroll-to-bottom');
    }

    public function setContext($type, $id = null)
    {
        $this->contextType = $type;
        $this->contextId = $id;
    }

    public function updatedPhoto()
    {
        if (!$this->photo) return;
        $this->resolveChatRoom();

        $photoPath = $this->photo->store('chat_attachments', 'public');
        $originalName = $this->photo->getClientOriginalName();

        ChatMessage::create([
            'user_id'      => Auth::id(),
            'message'      => 'FILE_DOWNLOAD|' . $originalName . '|' . $photoPath,
            'chat_room_id' => $this->chatRoomId,
            'context_type' => $this->contextType,
            'context_id'   => $this->contextId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->sendSininhoNotification('Enviou uma foto');

        $this->reset('photo');
        $this->dispatch('scroll-to-bottom');
    }

    public function updatedDocument()
    {
        if (!$this->document) return;
        $this->resolveChatRoom();

        $docPath = $this->document->store('chat_attachments', 'public');
        $originalName = $this->document->getClientOriginalName();

        ChatMessage::create([
            'user_id'      => Auth::id(),
            'message'      => 'FILE_DOWNLOAD|' . $originalName . '|' . $docPath,
            'chat_room_id' => $this->chatRoomId,
            'context_type' => $this->contextType,
            'context_id'   => $this->contextId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->sendSininhoNotification('Enviou um documento: ' . $originalName);

        $this->reset('document');
        $this->dispatch('scroll-to-bottom');
    }

    public function sendMessage()
    {
        if (empty(trim($this->newMessage))) return;

        $this->resolveChatRoom();

        ChatMessage::create([
            'user_id'      => Auth::id(),
            'message'      => $this->newMessage,
            'chat_room_id' => $this->chatRoomId,
            'context_type' => $this->contextType,
            'context_id'   => $this->contextId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->sendSininhoNotification($this->newMessage);

        $this->reset('newMessage');
        $this->updateMyPresence();
        $this->dispatch('scroll-to-bottom');
    }

    protected function sendSininhoNotification($content)
    {
        if ($this->activeChatId) {
            $recipient = User::find($this->activeChatId);
            $senderName = Auth::user()->name;

            $contextLabel = $this->contextType !== 'geral'
                ? ' em ' . strtoupper($this->contextType) . ' (#' . substr($this->contextId, 0, 8) . ')'
                : '';

            if ($recipient) {
                Notification::make()
                    ->title('Nova mensagem de ' . $senderName)
                    ->body(Str::limit($content, 60) . $contextLabel)
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->iconColor('warning')
                    ->sendToDatabase($recipient);
            }
        }
    }

    public function resolveChatRoom()
    {
        if (!$this->activeChatId) {
            $this->chatRoomId = null;
            return;
        }

        $myId = Auth::id();
        $targetId = $this->activeChatId;
        $tenantId = \App\Support\Tenancy::current()?->id;

        $participants = [$myId, $targetId];
        sort($participants);
        $roomSlug = "room_" . $participants[0] . "_" . $participants[1];

        $room = ChatRoom::where('title', $roomSlug)->first();

        if ($room) {
            $this->chatRoomId = $room->id;
            return;
        }

        $newRoomId = Str::uuid()->toString();

        try {
            DB::table('chat_rooms')->insert([
                'id'         => $newRoomId,
                'tenant_id'  => $tenantId,
                'type'       => 'private',
                'title'      => $roomSlug,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            try {
                DB::table('chat_room_user')->insert([
                    ['chat_room_id' => $newRoomId, 'user_id' => $myId],
                    ['chat_room_id' => $newRoomId, 'user_id' => $targetId],
                ]);
            } catch (\Exception $pivoteE) {}

            $this->chatRoomId = $newRoomId;
        } catch (\Exception $e) {
            $fallbackRoom = ChatRoom::where('title', $roomSlug)->first();
            $this->chatRoomId = $fallbackRoom ? $fallbackRoom->id : null;
        }
    }

    /**
     * FILTRO MULTI-TENANT: busca usuarios do mesmo tenant via coluna direta
     * users.tenant_id (a pivot tenant_user nunca teve migration criada).
     */
    #[Computed]
    public function chats()
    {
        $this->updateMyPresence();

        $tenantId = \App\Support\Tenancy::current()?->id;
        if (!$tenantId) return collect();

        $query = User::where('tenant_id', $tenantId)
            ->where('id', '!=', Auth::id())
            ->with('roles');

        if (!empty(trim($this->searchQuery))) {
            $query->where('name', 'ilike', '%' . trim($this->searchQuery) . '%');
        }

        return $query->get();
    }

    #[Computed]
    public function messages()
    {
        if (!$this->chatRoomId) return collect();

        return ChatMessage::with('user')
            ->where('chat_room_id', $this->chatRoomId)
            ->where('context_type', $this->contextType)
            ->where('context_id', $this->contextId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    #[Computed]
    public function activeOrders()
    {
        if (!$this->activeChatId) return collect();

        $tenantId = \App\Support\Tenancy::current()?->id;

        try {
            return MaintenanceOrder::where('technician_id', $this->activeChatId)
                ->where('status', '!=', 'encerrada')
                ->get();
        } catch (\Exception $e) {
            try {
                return MaintenanceOrder::where('tenant_id', $tenantId)
                    ->where('status', '!=', 'encerrada')
                    ->limit(5)
                    ->get();
            } catch (\Exception $e2) {
                return collect();
            }
        }
    }

    #[Computed]
    public function activeAssets()
    {
        try {
            $tenantId = \App\Support\Tenancy::current()?->id;
            return Asset::where('tenant_id', $tenantId)->limit(10)->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    #[Computed]
    public function activeMaterialRequests()
    {
        try {
            $tenantId = \App\Support\Tenancy::current()?->id;
            return MaterialRequest::where('tenant_id', $tenantId)
                ->where('status', '!=', 'entregue')
                ->orderBy('requested_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }
}
