<?php

namespace App\Livewire;

use App\Models\GlobalChat as ChatModel;
use App\Models\GlobalMessage;
use Livewire\Component;
use Filament\Facades\Filament;

class GlobalChat extends Component
{
    public $activeChatId;
    public $newMessage;

    public function sendMessage()
    {
        if (empty($this->newMessage)) return;

        GlobalMessage::create([
            'global_chat_id' => $this->activeChatId,
            'user_id' => auth()->id(),
            'content' => $this->newMessage,
        ]);

        $this->newMessage = '';
    }

    public function render()
    {
        $tenantId = Filament::getTenant()->id;
        
        return view('livewire.global-chat', [
            'chats' => ChatModel::where('tenant_id', $tenantId)->get(),
            'messages' => $this->activeChatId 
                ? GlobalMessage::where('global_chat_id', $this->activeChatId)->with('user')->oldest()->get()
                : collect([]),
        ]);
    }
}


