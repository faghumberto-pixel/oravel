<div>
@if(auth()->user()?->can('viewAny', \App\Models\ChatRoom::class))
    <div
        x-data="{
            expanded: $wire.entangle('isExpanded'),
            selectedUserId: $wire.entangle('selectedUserId'),
            init() {
                const saved = sessionStorage.getItem('oravel_chat_widget_state');
                if (saved) {
                    try {
                        const state = JSON.parse(saved);
                        $wire.restoreState(state.userId ?? null, state.expanded ?? false);
                    } catch (e) {}
                }
                this.$watch('expanded', () => this.persist());
                this.$watch('selectedUserId', () => this.persist());
                this.$wire.on('chat-widget-scroll-to-bottom', () => {
                    this.$nextTick(() => this.scrollToBottom());
                });
            },
            persist() {
                sessionStorage.setItem('oravel_chat_widget_state', JSON.stringify({ expanded: this.expanded, userId: this.selectedUserId }));
            },
            scrollToBottom() {
                const el = this.$refs.chatWidgetMessages;
                if (el) { el.scrollTop = el.scrollHeight; }
            }
        }"
        wire:poll.15s
        class="fixed bottom-0 right-6 z-[9999] w-80 font-sans"
    >
        <div class="bg-gray-900 rounded-t-xl shadow-2xl border border-gray-800 border-b-0 overflow-hidden flex flex-col" style="max-height: 28rem;">
            {{-- Header: sempre visivel, clicavel pra expandir/recolher --}}
            <button type="button" wire:click="toggleExpanded" class="flex items-center justify-between px-4 py-3 bg-gray-900 hover:bg-gray-800 transition-colors shrink-0">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="flex items-center justify-center w-7 h-7 rounded-full bg-primary-600 text-white text-xs font-bold shrink-0">
                        {{ $this->selectedUser ? Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($this->selectedUser['name'], 0, 1)) : '💬' }}
                    </span>
                    <span class="text-sm font-bold text-white truncate">{{ $this->selectedUser['name'] ?? 'Mensagens' }}</span>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    @if($this->totalUnread > 0)
                        <span class="min-w-[18px] h-[18px] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">
                            {{ $this->totalUnread }}
                        </span>
                    @endif
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         class="w-4 h-4 text-gray-400 transition-transform" :class="expanded ? 'rotate-180' : ''">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                    </svg>
                </div>
            </button>

            <div x-show="expanded" x-cloak class="flex-1 flex flex-col min-h-0" style="height: 24rem;">
                @if(! $this->selectedUserId)
                    {{-- Lista de conversas --}}
                    <div class="px-3 pt-3 pb-2 shrink-0">
                        <div class="relative">
                            <x-heroicon-m-magnifying-glass class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-500" />
                            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Pesquisar mensagens..."
                                   class="w-full bg-gray-800 text-xs text-white placeholder-gray-500 pl-8 pr-2 py-2 rounded-lg border border-gray-700 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none">
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto">
                        @forelse($this->users as $contact)
                            <button type="button" wire:click="selectConversation('{{ $contact['id'] }}')"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 hover:bg-gray-800/60 transition-colors text-left">
                                <div class="relative shrink-0">
                                    <span class="flex items-center justify-center w-9 h-9 rounded-full bg-primary-900/40 text-primary-300 text-sm font-bold">
                                        {{ Illuminate\Support\Str::upper(Illuminate\Support\Str::substr($contact['name'], 0, 1)) }}
                                    </span>
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-gray-900 {{ $contact['is_online'] ? 'bg-green-500' : 'bg-gray-600' }}"></span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p @class(['text-xs truncate', 'font-bold text-white' => $contact['unread'] > 0, 'font-semibold text-gray-300' => $contact['unread'] === 0])>
                                        {{ $contact['name'] }}
                                    </p>
                                    <p class="text-[11px] text-gray-500 truncate">{{ $contact['last_message'] ?: 'Sem mensagens ainda' }}</p>
                                </div>
                                @if($contact['unread'] > 0)
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
                                @endif
                            </button>
                        @empty
                            <p class="text-center text-xs text-gray-500 py-6">Nenhum contato encontrado.</p>
                        @endforelse
                    </div>
                @else
                    {{-- Thread da conversa --}}
                    <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-800 shrink-0">
                        <button type="button" wire:click="backToList" class="p-1 text-gray-400 hover:text-white transition-colors">
                            <x-heroicon-o-arrow-left class="w-4 h-4" />
                        </button>
                        <span class="text-xs font-semibold text-gray-300">{{ $this->selectedUser['name'] ?? '' }}</span>
                    </div>

                    <div x-ref="chatWidgetMessages" class="flex-1 overflow-y-auto px-3 py-2 space-y-2" style="background-color:#0b141a;">
                        @forelse($this->chatMessages as $msg)
                            <div @class(['flex w-full', 'justify-end' => $msg['is_mine'], 'justify-start' => ! $msg['is_mine']])>
                                <div @class([
                                        'max-w-[80%] px-3 py-1.5 rounded-2xl text-xs',
                                        'bg-primary-600 text-white' => $msg['is_mine'],
                                        'bg-gray-800 text-gray-100 border border-gray-700' => ! $msg['is_mine'],
                                    ])>
                                    @if($msg['message'])
                                        <p class="whitespace-pre-wrap break-words">{{ $msg['message'] }}</p>
                                    @endif
                                    @foreach($msg['attachments'] as $imageUrl)
                                        <img src="{{ $imageUrl }}" class="mt-1 max-w-[140px] max-h-32 rounded-lg object-cover" loading="lazy">
                                    @endforeach
                                    <div @class(['flex items-center justify-end gap-1 mt-0.5 text-[9px]', 'text-white/70' => $msg['is_mine'], 'text-gray-500' => ! $msg['is_mine']])>
                                        <span>{{ $msg['created_at'] }}</span>
                                        @if($msg['is_mine'])
                                            @if($msg['is_read'])
                                                <span class="text-sky-300">✓✓</span>
                                            @elseif($msg['is_delivered'])
                                                <span>✓✓</span>
                                            @else
                                                <span>✓</span>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-center text-[11px] text-gray-600 py-6">Nenhuma mensagem ainda.</p>
                        @endforelse
                    </div>

                    <div class="p-2 border-t border-gray-800 shrink-0" style="background-color:#0b141a;">
                        <div class="flex items-center gap-1.5">
                            <label class="flex items-center justify-center w-8 h-8 cursor-pointer text-gray-400 hover:text-primary-400 transition-colors shrink-0">
                                <input type="file" wire:model="temporaryImage" accept="image/*" class="hidden">
                                <x-heroicon-s-paper-clip class="w-4 h-4" wire:loading.remove wire:target="temporaryImage" />
                                <div wire:loading wire:target="temporaryImage" class="animate-spin h-4 w-4 border-2 border-primary-500 border-t-transparent rounded-full"></div>
                            </label>
                            <input type="text" wire:model="newMessage" wire:keydown.enter="sendMessage" placeholder="Digite uma mensagem..."
                                   class="flex-1 bg-gray-800 text-xs text-white placeholder-gray-500 px-3 py-2 rounded-full border border-gray-700 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none">
                            <button type="button" wire:click="sendMessage"
                                    class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-600 hover:bg-primary-700 text-white shrink-0 transition-colors">
                                <x-heroicon-s-paper-airplane class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
</div>
