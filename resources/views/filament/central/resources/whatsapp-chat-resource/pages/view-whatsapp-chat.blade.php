<x-filament-panels::page>
    <div
        class="flex flex-col bg-white dark:bg-gray-900 shadow-xl border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden"
        style="height: calc(100vh - 14rem); min-height: 480px;"
    >
        <div class="flex-1 overflow-y-auto p-5 space-y-4" x-ref="messagesContainer" style="background-color:#f0f2f5;" x-init="$nextTick(() => $refs.messagesContainer.scrollTop = $refs.messagesContainer.scrollHeight)">
            @forelse($this->getMessages() as $message)
                <div @class(['flex w-full', 'justify-end' => $message->role !== 'user', 'justify-start' => $message->role === 'user'])>
                    <div
                        @class([
                            'max-w-[75%] px-4 py-2.5 shadow-sm text-sm font-medium rounded-2xl',
                            'bg-orange-600 text-white' => $message->role !== 'user',
                            'bg-white text-gray-800 border border-gray-200' => $message->role === 'user',
                        ])
                    >
                        <p @class(['text-xs font-bold mb-0.5', 'text-white/80' => $message->role !== 'user', 'text-orange-600' => $message->role === 'user'])>
                            {{ $message->role === 'user' ? ($this->record->contact_name ?: $this->record->phone_number) : 'Você' }}
                        </p>
                        <p class="whitespace-pre-wrap break-words leading-relaxed">{{ $message->content }}</p>
                        <p @class(['mt-1 text-[10px] text-right', 'text-white/70' => $message->role !== 'user', 'text-gray-400' => $message->role === 'user'])>
                            {{ $message->created_at->format('d/m H:i') }}
                        </p>
                    </div>
                </div>
            @empty
                <div class="h-full flex flex-col items-center justify-center text-gray-400">
                    <x-heroicon-o-chat-bubble-left-right class="w-10 h-10 mb-2 opacity-50" />
                    <p class="font-bold text-sm uppercase tracking-widest">Nenhuma mensagem ainda</p>
                </div>
            @endforelse
        </div>

        @if($this->record->status === \App\Models\WhatsAppChat::STATUS_CLOSED)
            <div class="p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800 text-center text-sm text-gray-500 dark:text-gray-400">
                Este atendimento está encerrado.
            </div>
        @else
            <div class="p-3 sm:p-4 border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800">
                <form wire:submit="sendReply" class="flex items-center gap-2">
                    <input
                        type="text"
                        wire:model="draftMessage"
                        placeholder="Digite sua resposta..."
                        class="flex-1 rounded-full border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                    >
                    <button type="submit" class="flex items-center justify-center w-11 h-11 rounded-full bg-orange-600 hover:bg-orange-700 text-white shadow-lg transition shrink-0" title="Enviar">
                        <x-heroicon-s-paper-airplane class="w-5 h-5" />
                    </button>
                </form>
            </div>
        @endif
    </div>
</x-filament-panels::page>
