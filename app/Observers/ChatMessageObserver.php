<?php

namespace App\Observers;

use App\Models\ChatMessage;
use Illuminate\Support\Facades\Auth;

class ChatMessageObserver
{
    public function creating(ChatMessage $chatMessage): void
    {
        if (empty($chatMessage->tenant_id) && Auth::check()) {
            $chatMessage->tenant_id = Auth::user()->tenant_id;
        }
    }
}
