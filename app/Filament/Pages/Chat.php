<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Facades\Filament;
use App\Models\User;
use App\Models\ChatMessage; 
use App\Models\ChatRoom;
use App\Models\MaintenanceOrder;
use App\Models\Asset;
use App\Models\MaterialRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use Filament\Notifications\Notification;

class Chat extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Canais de Chat';
    protected static ?string $title = 'Central Corporativa';
    protected static ?string $navigationGroup = 'GESTÃO DE PESSOAS';
    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.chat';

    #[Url]
    public ?int $activeChatId = null; 
    public ?string $chatRoomId = null; 
    public string $newMessage = '';
    public string $searchQuery = ''; 
    
    // Controle de Contextos Operacionais Dinâmicos (Abas)
    public string $contextType = 'geral'; // geral, os, asset, material
    public ?string $contextId = null;     // Guarda o ID/UUID do registro selecionado

    public $photo = null;
    public $document = null;
    public int $refreshCounter = 0;

    public function mount()
    {
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
        if (auth()->check()) {
            Cache::put('user-online-' . auth()->id(), true, 30);
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
            'user_id'      => auth()->id(),
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
            'user_id'      => auth()->id(),
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
            'user_id'      => auth()->id(),
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

    /**
     * DISPARA O ALERTA NO SININHO DO FILAMENT EM TEMPO REAL
     */
    protected function sendSininhoNotification($content)
    {
        if ($this->activeChatId) {
            $recipient = User::find($this->activeChatId);
            $senderName = auth()->user()->name;
            
            $contextLabel = $this->contextType !== 'geral' 
                ? ' em ' . strtoupper($this->contextType) . ' (#' . substr($this->contextId, 0, 8) . ')' 
                : '';

            if ($recipient) {
                Notification::make()
                    ->title('Nova mensagem de ' . $senderName)
                    ->body(Str::limit($content, 60) . $contextLabel)
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->iconColor('warning') // Dourado padrão do Oravel
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

        $myId = auth()->id();
        $targetId = $this->activeChatId;
        $tenant = Filament::getTenant();

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
                'tenant_id'  => $tenant?->id,
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

    #[Computed]
    public function chats()
    {
        $this->updateMyPresence(); 
        
        $tenant = Filament::getTenant();
        if (!$tenant) return collect();

        $query = User::where('tenant_id', $tenant->id)
            ->where('id', '!=', auth()->id())
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
        try {
            return MaintenanceOrder::where('user_id', $this->activeChatId)
                ->where('status', '!=', 'encerrada')
                ->get();
        } catch (\Exception $e) {
            try {
                return MaintenanceOrder::where('tenant_id', Filament::getTenant()?->id)
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
            return Asset::where('tenant_id', Filament::getTenant()?->id)->limit(10)->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    #[Computed]
    public function activeMaterialRequests()
    {
        try {
            return MaterialRequest::where('tenant_id', Filament::getTenant()?->id)
                ->where('status', '!=', 'entregue')
                ->orderBy('requested_at', 'desc')
                ->get();
        } catch (\Exception $e) {
            return collect();
        }
    }
}