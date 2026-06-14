<?php

namespace App\Http/Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use Illuminate\Http\Request;

class ChatPrintController extends Controller
{
    public function printRoom($roomId)
    {
        $room = ChatRoom::findOrFail($roomId);
        
        // Puxa as mensagens com os respectivos usuários ordenadas por data
        $messages = ChatMessage::where('chat_room_id', $roomId)
            ->with('user')
            ->oldest()
            ->get();

        return view('maintenance.chat-print', [
            'room' => $room,
            'messages' => $messages
        ]);
    }
}
