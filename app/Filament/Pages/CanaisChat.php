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

class CanaisChat extends Page
{
    use WithFileUploads;

    // Travado no seu botão do menu inferior
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Canais de Chat';
    protected static ?string $title = 'Central Corporativa';
    protected static ?string $navigationGroup = 'GESTÃO DE PESSOAS';
    protected static ?int $navigationSort = 4;

    // Força a URL a ser exatamente o /chat que o seu menu já chama
    protected static ?string $slug = 'chat';

    // Aponta para o arquivo de visualização padrão do pátio
    protected static string $view = 'filament.pages.chat';

    #[Url]
    public ?int $activeChatId = null; 
    public ?string $chatRoomId = null; 
    public string $newMessage = '';
    public string $searchQuery = ''; 
    
    // Abas de Contexto Ativas
    public string $contextType = 'geral'; // geral, os, asset, material
    public ?string $contextId = null;     

    public bool $isBroadcastMode = false;
    public bool $isShareModalOpen = false;
    public ?int $messageIdToShare = null;

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
        if (auth()->check()) {
            Cache::put('user-online-' . auth()->id(), true, 30);
        }
    }

    public function selectChat($userId)
    {
        $this->isBroadcastMode = false;
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

        $this->reset('newMessage');
        $this->dispatch('scroll-to-bottom');
    }

    public function resolveChatRoom()
    {
        if (!$this->activeChatId) return;

        $myId = auth()->id();
        $targetId = $this->activeChatId;
        $tenantId = Filament::getTenant()?->id;

        $participants = [$myId, $targetId];
        sort($participants);
        $roomSlug = "room_" . $participants[0] . "_" . $participants[1];

        $room = ChatRoom::where('title', $roomSlug)->first();
        if ($room) {
            $this->chatRoomId = $room->id;
            return;
        }

        $newRoomId = Str::uuid()->toString();
        DB::table('chat_rooms')->insert([
            'id'         => $newRoomId,
            'tenant_id'  => $tenantId,
            'type'       => 'private',
            'title'      => $roomSlug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('chat_room_user')->insert([
            ['chat_room_id' => $newRoomId, 'user_id' => $myId],
            ['chat_room_id' => $newRoomId, 'user_id' => $targetId],
        ]);

        $this->chatRoomId = $newRoomId;
    }

    #[Computed]
    public function chats()
    {
        $tenant = Filament::getTenant();
        if (!$tenant) return collect();

        $query = User::where('tenant_id', $tenant->id)->where('id', '!=', auth()->id());

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
        return MaintenanceOrder::where('user_id', $this->activeChatId)
            ->where('status', '!=', 'encerrada')
            ->get();
    }

    #[Computed]
    public function activeAssets()
    {
        return Asset::where('tenant_id', Filament::getTenant()?->id)->limit(15)->get();
    }

    #[Computed]
    public function activeMaterialRequests()
    {
        return MaterialRequest::where('tenant_id', Filament::getTenant()?->id)
            ->where('status', '!=', 'entregue')
            ->with(['user'])
            ->orderBy('requested_at', 'desc')
            ->get();
    }
}
