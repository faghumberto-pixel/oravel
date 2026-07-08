<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ChatHistoryPdfController extends Controller
{
    /**
     * So quem participa da sala pode exportar -- mesma sala pessoal usada
     * pelo GlobalChat (App\Livewire\GlobalChat::chatRoom()).
     */
    public function download(ChatRoom $room): Response
    {
        abort_unless($room->users()->where('users.id', Auth::id())->exists(), 403);

        $messages = $room->messages()
            ->with(['user', 'media'])
            ->oldest()
            ->get();

        $otherUser = $room->users()->where('users.id', '!=', Auth::id())->first();

        $pdf = Pdf::loadView('pdf.chat_history', [
            'room' => $room,
            'messages' => $messages,
            'otherUser' => $otherUser,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);

        return $pdf->download('conversa-'.($otherUser?->name ?? $room->id).'.pdf');
    }
}
