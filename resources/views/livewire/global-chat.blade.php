<div class="flex h-[80vh] bg-[#09090b] border border-gray-800 rounded-xl overflow-hidden shadow-2xl" wire:poll.3s>
    
    <div class="w-1/3 border-r border-gray-800 bg-[#111114] overflow-y-auto custom-chat-scrollbar">
        <div class="p-4 border-b border-gray-800 bg-[#111114]/80 sticky top-0 z-10 backdrop-blur-md">
            <h3 class="font-black text-xs uppercase tracking-widest text-gray-400">Canais Oravel</h3>
        </div>
        
        @foreach($chats as $chat)
            <div wire:click="$set('activeChatId', {{ $chat->id }})" 
                 class="p-4 cursor-pointer transition-all border-b border-gray-800/30 relative {{ $activeChatId == $chat->id ? 'bg-amber-500/5' : 'hover:bg-gray-800/40' }}">
                
                <div class="font-bold text-[11px] uppercase tracking-tight {{ $activeChatId == $chat->id ? 'text-amber-500' : 'text-gray-300' }}">
                    {{ $chat->name }}
                </div>
                <div class="text-[9px] text-gray-500 font-black uppercase mt-1 tracking-widest italic opacity-60">Canal Corporativo</div>

                @if($activeChatId == $chat->id)
                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-amber-500 shadow-[0_0_10px_#f59e0b]"></div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="w-2/3 flex flex-col bg-[#09090b]">
        @if($activeChatId)
            <div class="p-4 border-b border-gray-800 bg-[#111114] flex justify-between items-center shadow-md">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-500 border border-amber-500/20">
                        <x-heroicon-s-chat-bubble-left-right class="w-5 h-5" />
                    </div>
                    <h3 class="text-xs font-black text-white uppercase tracking-tighter">Histórico de Mensagens</h3>
                </div>

                <a href="{{ route('maintenance.chat.print', ['record' => $activeChatId]) }}" 
                   target="_blank" 
                   class="flex items-center gap-2 px-3 py-1.5 bg-gray-900 hover:bg-amber-500/10 text-amber-500 rounded-lg text-[10px] font-black border border-amber-500/30 transition-all shadow-sm">
                    <x-heroicon-m-printer class="w-4 h-4" />
                    IMPRIMIR CONVERSA
                </a>
            </div>

            <div class="flex-1 p-6 overflow-y-auto flex flex-col gap-6 custom-chat-scrollbar">
                @foreach($messages as $msg)
                    @php $isMine = $msg->user_id == auth()->id(); @endphp
                    <div class="flex flex-col {{ $isMine ? 'items-end' : 'items-start' }}">
                        <div class="max-w-[80%]">
                            @if(!$isMine)
                                <div class="text-[10px] font-black text-amber-500 uppercase tracking-tighter mb-1 ml-1">
                                    {{ $msg->user->name }}
                                </div>
                            @endif

                            <div class="p-3.5 rounded-2xl shadow-lg border {{ $isMine ? 'bg-amber-600 border-amber-500 text-white rounded-tr-none' : 'bg-[#1e1e21] border-gray-800 text-gray-100 rounded-tl-none' }}">
                                <div class="text-xs font-medium leading-relaxed">{{ $msg->content }}</div>
                            </div>

                            <div class="text-[9px] font-black text-gray-600 uppercase mt-1.5 {{ $isMine ? 'text-right mr-1' : 'ml-1' }}">
                                {{ $msg->created_at->format('d/m/Y H:i') }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="p-4 border-t border-gray-800 bg-[#111114]">
                <form wire:submit.prevent="sendMessage" class="flex gap-3 items-center bg-gray-900/50 p-2 rounded-2xl border border-gray-800 focus-within:border-amber-500/50 transition-all">
                    <input type="text" wire:model="newMessage" placeholder="Escreva sua mensagem técnica..." 
                           class="flex-1 bg-transparent border-none focus:ring-0 text-sm text-white placeholder-gray-600">
                    <button type="submit" class="bg-amber-600 hover:bg-amber-500 text-white p-2.5 rounded-xl shadow-lg transition-all active:scale-95 flex items-center justify-center">
                        <x-heroicon-s-paper-airplane class="w-5 h-5 transform rotate-45" />
                    </button>
                </form>
            </div>
        @else
            <div class="flex-1 flex flex-col items-center justify-center p-10 opacity-20">
                <x-heroicon-o-chat-bubble-left-right class="w-16 h-16 text-gray-400 mb-4" />
                <h2 class="text-sm font-black text-gray-500 uppercase tracking-[0.4em]">Selecione um Canal</h2>
            </div>
        @endif
    </div>
</div>

<style>
    .custom-chat-scrollbar::-webkit-scrollbar { width: 5px; }
    .custom-chat-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-chat-scrollbar::-webkit-scrollbar-thumb { background: #27272a; border-radius: 10px; }
</style>