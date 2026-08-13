<div>
    <div
        x-data="chatComponent()"
        x-init="init()"
        wire:poll.15s="checkForNewMessages"
        class="flex bg-white shadow-xl border border-gray-200"
        style="height: calc(100vh - 8rem); min-height: 540px; border-radius: 1.5rem; overflow: hidden;"
    >
        {{-- SIDEBAR --}}
        <div class="border-r border-gray-200 flex-col bg-white shrink-0 flex" :style="isDesktop ? 'width: 22rem' : 'width: 100%'" x-show="isDesktop || mobileView === 'list'">
            <div class="px-5 pt-5 pb-4 shrink-0 bg-gray-50 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-extrabold tracking-tight text-gray-900">Chat Interno</h2>
                    @if($avatarUrl = auth()->user()?->getFilamentAvatarUrl())
                        <img src="{{ $avatarUrl }}" class="w-10 h-10 rounded-full object-cover shadow" alt="">
                    @else
                        <div class="w-10 h-10 bg-orange-500 text-white flex items-center justify-center text-sm font-bold shadow" style="border-radius:9999px;">
                            {{ Str::upper(Str::substr(auth()->user()?->name ?? '?', 0, 1)) }}
                        </div>
                    @endif
                </div>
                <div class="mt-1.5 flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-gray-500 min-w-0 truncate">
                        Você: <span class="font-bold text-orange-600">{{ auth()->user()?->name }}</span>
                    </p>
                    {{-- Só existe rota chat.logout no chat standalone (/chat) --
                         este mesmo Blade é reaproveitado como <livewire:global-chat/>
                         dentro do painel admin, onde o logout é o do Filament. --}}
                    @if(\Illuminate\Support\Facades\Route::has('chat.logout') && request()->routeIs('chat.*'))
                        <form method="POST" action="{{ route('chat.logout') }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="text-xs font-semibold text-gray-500 hover:text-red-600 transition" title="Sair">
                                Sair
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="px-4 pt-3 pb-3 shrink-0 border-b border-gray-100">
                <div class="relative">
                    <x-heroicon-m-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input type="text" x-model="search" placeholder="Pesquisar conversa..."
                        class="w-full bg-gray-100 text-sm text-gray-900 placeholder-gray-400 pl-9 pr-3 py-2.5 border border-gray-200 focus:ring-2 focus:ring-orange-500 focus:border-orange-500 outline-none transition" style="border-radius:9999px;">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto chat-scroll">
                @forelse($this->users as $user)
                    <div
                        wire:click="selectUser('{{ data_get($user, 'id') }}')"
                        @click="mobileView = 'chat'"
                        wire:key="contact-{{ data_get($user, 'id') }}"
                        x-show="search === '' || @js(Str::lower(data_get($user, 'name', '') ?? '')).includes(search.toLowerCase())"
                        @class([
                            'px-4 py-3 cursor-pointer flex items-center gap-3 transition-colors border-l-2',
                            'bg-orange-50 border-orange-500' => $this->selectedUserId === data_get($user, 'id'),
                            'border-transparent hover:bg-gray-50' => $this->selectedUserId !== data_get($user, 'id'),
                        ])
                    >
                        <div class="relative shrink-0">
                            @if(data_get($user, 'avatar_url'))
                                <img src="{{ data_get($user, 'avatar_url') }}" class="w-11 h-11 rounded-full object-cover" alt="">
                            @else
                                <div class="w-11 h-11 bg-orange-100 text-orange-700 flex items-center justify-center text-base font-bold" style="border-radius:9999px;">
                                    {{ Str::upper(Str::substr(data_get($user, 'name', '?'), 0, 1)) }}
                                </div>
                            @endif
                            <span @class(['absolute bottom-0 right-0 w-3 h-3 border-2 border-white','bg-green-500' => data_get($user, 'is_online'),'bg-gray-300' => ! data_get($user, 'is_online')]) style="border-radius:9999px;"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-gray-900 truncate">{{ data_get($user, 'name', 'Usuário') }}</span>
                            <span class="block text-xs truncate text-gray-500">{{ data_get($user, 'department') ?? 'Sem departamento' }}</span>
                        </div>
                        @if(data_get($user, 'unread') > 0)
                            <span class="ml-auto shrink-0 min-w-[20px] h-5 px-1.5 bg-green-500 text-white text-[11px] font-bold flex items-center justify-center" style="border-radius:9999px;">
                                {{ data_get($user, 'unread') }}
                            </span>
                        @endif
                    </div>
                @empty
                    <p class="p-4 text-sm text-gray-500">Nenhum contato no momento.</p>
                @endforelse
            </div>

            <div class="shrink-0 border-t border-gray-200 p-3 bg-gray-50" x-data="{ openDept: false }">
                @php
                    $activeDep = $this->selectedDepartmentId
                        ? optional($this->departments->firstWhere('id', $this->selectedDepartmentId))->name
                        : null;
                @endphp
                <div x-show="openDept" x-cloak class="mb-2 max-h-44 overflow-y-auto chat-scroll space-y-1">
                    <button type="button" wire:click="filterDepartment" @click="openDept = false"
                        @class(['w-full text-left px-3 py-2 text-sm font-medium transition','bg-orange-500 text-white' => blank($this->selectedDepartmentId),'text-gray-600 hover:bg-gray-100' => filled($this->selectedDepartmentId)]) style="border-radius:0.5rem;">Todos</button>
                    @forelse($this->departments as $dep)
                        <button type="button" wire:click="filterDepartment('{{ $dep->id }}')" @click="openDept = false"
                            @class(['w-full text-left px-3 py-2 text-sm font-medium transition','bg-orange-500 text-white' => (string) $this->selectedDepartmentId === (string) $dep->id,'text-gray-600 hover:bg-gray-100' => (string) $this->selectedDepartmentId !== (string) $dep->id]) style="border-radius:0.5rem;">{{ $dep->name }}</button>
                    @empty
                        <p class="px-3 py-2 text-xs text-gray-500">Nenhum departamento cadastrado.</p>
                    @endforelse
                </div>
                <button type="button" @click="openDept = !openDept"
                    class="w-full flex items-center justify-between gap-2 px-3 py-2.5 bg-white border border-gray-200 text-sm font-semibold text-gray-700 hover:bg-gray-100 transition" style="border-radius:0.75rem;">
                    <span class="flex items-center gap-2 truncate">
                        <x-heroicon-o-building-office-2 class="w-5 h-5 text-orange-600 shrink-0" />
                        <span class="truncate">Departamento: <span class="text-orange-600">{{ $activeDep ?? 'Todos' }}</span></span>
                    </span>
                    <x-heroicon-m-chevron-up-down class="w-4 h-4 shrink-0" />
                </button>
            </div>
        </div>

        {{-- MAIN CHAT --}}
        <div class="flex-1 flex-col min-w-0 flex bg-[#efeae2]" x-show="isDesktop || mobileView === 'chat'">
            @if($this->selectedUser)
                <div class="px-5 py-4 bg-gray-50 border-b border-gray-200 flex items-center gap-3 shrink-0">
                    <button @click="mobileView = 'list'" type="button" class="md:hidden -ml-1 mr-1 p-1.5 text-gray-600 hover:bg-gray-200 transition" style="border-radius:0.5rem;" title="Voltar">
                        <x-heroicon-o-arrow-left class="w-5 h-5" />
                    </button>
                    <div class="relative shrink-0">
                        @if(data_get($this->selectedUser, 'avatar_url'))
                            <img src="{{ data_get($this->selectedUser, 'avatar_url') }}" class="w-10 h-10 rounded-full object-cover" alt="">
                        @else
                            <div class="w-10 h-10 bg-orange-100 text-orange-700 flex items-center justify-center text-base font-bold" style="border-radius:9999px;">
                                {{ Str::upper(Str::substr(data_get($this->selectedUser, 'name', '?'), 0, 1)) }}
                            </div>
                        @endif
                        <span @class(['absolute bottom-0 right-0 w-3 h-3 border-2 border-white','bg-green-500' => data_get($this->selectedUser, 'is_online'),'bg-gray-300' => ! data_get($this->selectedUser, 'is_online')]) style="border-radius:9999px;"></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-bold text-gray-900 truncate">{{ data_get($this->selectedUser, 'name', 'Usuário') }}</h3>
                        <p @class(['text-[11px] font-semibold','text-green-600' => data_get($this->selectedUser, 'is_online'),'text-gray-400' => ! data_get($this->selectedUser, 'is_online')])>{{ data_get($this->selectedUser, 'is_online') ? 'Online' : 'Offline' }}</p>
                    </div>
                    @if($this->chatRoom)
                        <a href="{{ route('chat.history.pdf', ['room' => $this->chatRoom->id]) }}" target="_blank"
                           title="Exportar conversa em PDF"
                           class="flex items-center justify-center w-9 h-9 text-gray-500 hover:text-orange-600 hover:bg-gray-200 transition shrink-0" style="border-radius:9999px;">
                            <x-heroicon-o-arrow-down-tray class="w-5 h-5" />
                        </a>
                    @endif
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-4 chat-scroll" x-ref="messagesContainer">
                    @forelse($this->chatMessages as $msg)
                        <div wire:key="msg-{{ data_get($msg, 'id') }}" @class(['flex w-full','justify-end' => data_get($msg, 'is_mine'),'justify-start' => ! data_get($msg, 'is_mine')])>
                            <div
                                @class([
                                    'max-w-[78%] lg:max-w-md px-4 py-2.5 shadow-sm text-sm font-medium',
                                    'bg-orange-600 text-white' => data_get($msg, 'is_mine'),
                                    'bg-white text-gray-800 border border-gray-200' => ! data_get($msg, 'is_mine'),
                                ])
                                style="border-radius: {{ data_get($msg, 'is_mine') ? '18px 18px 4px 18px' : '18px 18px 18px 4px' }};"
                            >
                                <p @class(['text-xs font-bold mb-0.5','text-white/90' => data_get($msg, 'is_mine'),'text-orange-600' => ! data_get($msg, 'is_mine')])>
                                    {{ data_get($msg, 'is_mine') ? 'Eu' : data_get($this->selectedUser, 'name', 'Contato') }}
                                </p>

                                @if($message = data_get($msg, 'message'))
                                    <p class="whitespace-pre-wrap break-words leading-relaxed">{{ $message }}</p>
                                @endif

                                @if(! empty(data_get($msg, 'attachments')))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach(data_get($msg, 'attachments', []) as $imageUrl)
                                            <a href="{{ $imageUrl }}" target="_blank" class="block border border-black/10 overflow-hidden shadow-sm hover:opacity-90 transition" style="border-radius:0.75rem;">
                                                <img src="{{ $imageUrl }}" alt="Anexo" class="max-w-[160px] max-h-40 object-cover" loading="lazy">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                @if($audio = data_get($msg, 'audio'))
                                    <audio controls src="{{ $audio }}" preload="metadata" class="mt-2 w-56 max-w-full"></audio>
                                    @if($transcript = data_get($msg, 'transcript'))
                                        <p @class(['mt-1 text-xs italic', 'text-white/70' => data_get($msg, 'is_mine'), 'text-gray-500' => ! data_get($msg, 'is_mine')])>
                                            "{{ $transcript }}"
                                        </p>
                                    @endif
                                @endif

                                @if(! empty(data_get($msg, 'documents')))
                                    <div class="mt-2 space-y-1.5">
                                        @foreach(data_get($msg, 'documents', []) as $doc)
                                            <a href="{{ data_get($doc, 'url') }}" target="_blank"
                                               @class(['flex items-center gap-2 px-3 py-2 border transition', 'border-white/20 hover:bg-white/10' => data_get($msg, 'is_mine'), 'border-gray-200 hover:bg-gray-50' => ! data_get($msg, 'is_mine')]) style="border-radius:0.5rem;">
                                                <x-heroicon-o-document-text class="w-5 h-5 shrink-0" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-xs font-bold truncate">{{ data_get($doc, 'name') }}</span>
                                                    <span class="block text-[10px] opacity-70">{{ data_get($doc, 'size') }}</span>
                                                </span>
                                                <x-heroicon-o-arrow-down-tray class="w-4 h-4 shrink-0 opacity-70" />
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                <div @class(['flex items-center justify-end gap-1.5 mt-1.5 text-[10px] font-semibold','text-white/70' => data_get($msg, 'is_mine'),'text-gray-400' => ! data_get($msg, 'is_mine')])>
                                    @if(data_get($msg, 'message'))
                                        <button type="button" @click="shareMessage(@js(data_get($msg, 'message')))" class="opacity-50 hover:opacity-100 transition" title="Compartilhar mensagem">
                                            <x-heroicon-m-share class="w-3.5 h-3.5" />
                                        </button>
                                    @endif
                                    <span>{{ data_get($msg, 'created_at') }}</span>
                                    @if(data_get($msg, 'is_mine'))
                                        @if(data_get($msg, 'is_read'))
                                            <span class="tracking-tighter text-sky-300" title="Lido">✓✓</span>
                                        @elseif(data_get($msg, 'is_delivered'))
                                            <span class="tracking-tighter text-white/70" title="Entregue">✓✓</span>
                                        @else
                                            <span class="tracking-tighter text-white/50" title="Enviado">✓</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-gray-400">
                            <div class="bg-white p-4 border border-gray-200 mb-3 shadow-sm" style="border-radius:9999px;">
                                <x-heroicon-o-chat-bubble-left-right class="w-8 h-8 opacity-50" />
                            </div>
                            <p class="font-bold text-sm uppercase tracking-widest">Nenhuma mensagem ainda</p>
                        </div>
                    @endforelse
                </div>

                <div wire:loading wire:target="temporaryImage" class="px-5 pb-1 shrink-0 bg-gray-50">
                    <span class="text-xs text-gray-500 animate-pulse">Enviando imagem...</span>
                </div>

                <div wire:loading wire:target="temporaryDocument" class="px-5 pb-1 shrink-0 bg-gray-50">
                    <span class="text-xs text-gray-500 animate-pulse">Enviando documento...</span>
                </div>

                <div x-show="isRecording" x-cloak class="px-5 pb-1 flex items-center gap-2 text-red-600 text-sm shrink-0 bg-gray-50">
                    <span class="w-2 h-2 bg-red-500 animate-pulse" style="border-radius:9999px;"></span>
                    <span x-text="`Gravando... ${recordingTime}s`"></span>
                </div>

                <div x-show="pendingOfflineCount > 0" x-cloak class="px-5 pb-1 flex items-center gap-2 text-amber-600 text-xs shrink-0 bg-gray-50">
                    <span class="w-1.5 h-1.5 bg-amber-500" style="border-radius:9999px;"></span>
                    <span x-text="pendingOfflineCount === 1 ? '1 mensagem aguardando conexão' : `${pendingOfflineCount} mensagens aguardando conexão`"></span>
                </div>

                <div class="p-3 sm:p-4 border-t border-gray-200 shrink-0 bg-gray-50">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 flex items-center gap-0.5 bg-white border border-gray-300 px-2 py-1 focus-within:ring-2 focus-within:ring-orange-500 focus-within:border-orange-500 transition" style="border-radius:9999px;">
                            <span class="flex items-center justify-center w-9 h-9 text-lg leading-none select-none shrink-0">😊</span>
                            <input type="text" x-model="draftMessage" x-on:input="hasText = $event.target.value.trim().length > 0" @keydown.enter="sendOrQueue()" :disabled="isRecording"
                                class="flex-1 bg-transparent text-gray-900 placeholder-gray-400 px-2 py-2 outline-none border-0 focus:ring-0 text-sm font-medium" placeholder="Digite uma mensagem...">
                            <label title="Anexar imagem" class="flex items-center justify-center w-9 h-9 cursor-pointer text-gray-500 hover:text-orange-600 hover:bg-gray-100 transition shrink-0" style="border-radius:9999px;">
                                <input type="file" wire:model="temporaryImage" accept="image/*" class="hidden">
                                <div wire:loading wire:target="temporaryImage" class="animate-spin h-5 w-5 border-2 border-orange-500 border-t-transparent" style="border-radius:9999px;"></div>
                                <x-heroicon-s-paper-clip class="w-5 h-5" wire:loading.remove wire:target="temporaryImage" />
                            </label>
                            <label title="Tirar foto" class="flex items-center justify-center w-9 h-9 cursor-pointer text-gray-500 hover:text-orange-600 hover:bg-gray-100 transition shrink-0" style="border-radius:9999px;">
                                <input type="file" wire:model="temporaryImage" accept="image/*" capture="environment" class="hidden">
                                <x-heroicon-s-camera class="w-5 h-5" />
                            </label>
                            <label title="Anexar documento" class="flex items-center justify-center w-9 h-9 cursor-pointer text-gray-500 hover:text-orange-600 hover:bg-gray-100 transition shrink-0" style="border-radius:9999px;">
                                <input type="file" wire:model="temporaryDocument" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" class="hidden">
                                <div wire:loading wire:target="temporaryDocument" class="animate-spin h-5 w-5 border-2 border-orange-500 border-t-transparent" style="border-radius:9999px;"></div>
                                <x-heroicon-s-document-text class="w-5 h-5" wire:loading.remove wire:target="temporaryDocument" />
                            </label>
                        </div>
                        <button type="button" x-show="hasText && !isRecording" @click="sendOrQueue()"
                            class="flex items-center justify-center w-12 h-12 bg-orange-600 hover:bg-orange-700 text-white shadow-lg transition shrink-0" style="border-radius:9999px;" title="Enviar">
                            <x-heroicon-s-paper-airplane class="w-5 h-5" />
                        </button>
                        {{-- Ícones sempre presentes no DOM (SVG, não x-text) --
                             se o Alpine demorar ou falhar ao inicializar num
                             celular, o usuário ainda vê o microfone/enviar em
                             vez de um círculo vazio (achado real 2026-08-12:
                             <span x-text="'🎤'"> fica sem conteúdo até o
                             Alpine popular o texto, diferente de x-show que
                             ao menos deixa o elemento visível por padrão). --}}
                        <button type="button" x-show="!hasText || isRecording" @click="toggleRecording()"
                            :class="isRecording ? 'bg-red-600 hover:bg-red-700' : 'bg-orange-600 hover:bg-orange-700'"
                            class="flex items-center justify-center w-12 h-12 bg-orange-600 hover:bg-orange-700 text-white shadow-lg transition shrink-0" style="border-radius:9999px;" title="Gravar áudio">
                            <x-heroicon-s-stop class="w-5 h-5" x-show="isRecording" x-cloak />
                            <x-heroicon-s-microphone class="w-5 h-5" x-show="!isRecording" />
                        </button>
                    </div>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-gray-400">
                    <div class="bg-white p-4 border border-gray-200 mb-3 shadow-sm" style="border-radius:9999px;">
                        <x-heroicon-o-chat-bubble-left-right class="w-8 h-8 opacity-50" />
                    </div>
                    <p class="font-bold text-sm uppercase tracking-widest">Selecione uma conversa</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        function chatComponent() {
            return {
                mobileView: 'list', isDesktop: window.innerWidth >= 768, search: '', hasText: false,
                isRecording: false, recordingTime: 0, mediaRecorder: null, audioChunks: [], recordingInterval: null,
                draftMessage: '', pendingOfflineCount: 0,
                init() {
                    this.isDesktop = window.innerWidth >= 768;
                    window.addEventListener('resize', () => { this.isDesktop = window.innerWidth >= 768; });
                    this.$wire.on('scroll-to-bottom', () => { this.$nextTick(() => this.scrollToBottom()); });
                    this.$nextTick(() => this.scrollToBottom());

                    // Fila offline (só disponível na rota /chat standalone,
                    // que carrega resources/js/chat-app.js -- dentro do
                    // painel/admin essa window global não existe, então
                    // sendOrQueue() cai direto no caminho normal).
                    this.refreshPendingOfflineCount();
                    window.addEventListener('chat-offline-synced', () => {
                        this.refreshPendingOfflineCount();
                        this.$wire.checkForNewMessages();
                    });
                },
                async refreshPendingOfflineCount() {
                    if (window.OravelChatOffline) {
                        this.pendingOfflineCount = await window.OravelChatOffline.pendingCount();
                    }
                },
                /**
                 * Tenta enviar a mensagem digitada. Se o navegador já
                 * reportar offline, enfileira direto sem tentar a rede. Se
                 * o navegador achar que está online mas a requisição falhar
                 * de verdade (wi-fi "morto", sem internet real por trás),
                 * cai pra fila do mesmo jeito -- não basta confiar só em
                 * navigator.onLine, que é notoriamente otimista demais.
                 */
                async sendOrQueue() {
                    const text = this.draftMessage.trim();
                    if (!text || this.isRecording) return;

                    this.hasText = false;
                    const recipientId = this.$wire.selectedUserId;

                    const queueLocally = async () => {
                        if (!window.OravelChatOffline || !recipientId) return false;
                        await window.OravelChatOffline.enqueueMessage(recipientId, text);
                        await this.refreshPendingOfflineCount();
                        this.$nextTick(() => this.scrollToBottom());
                        return true;
                    };

                    if (!navigator.onLine) {
                        this.draftMessage = '';
                        await queueLocally();
                        return;
                    }

                    this.draftMessage = '';
                    await this.$wire.set('newMessage', text);

                    try {
                        await this.$wire.sendMessage();
                    } catch (error) {
                        // Falha de rede real durante o request -- devolve o
                        // texto pra fila em vez de perder silenciosamente.
                        await queueLocally();
                    }
                },
                scrollToBottom() { const el = this.$refs.messagesContainer; if (el) { el.scrollTop = el.scrollHeight; } },
                async shareMessage(text) {
                    if (!text) return;
                    try {
                        if (navigator.share) { await navigator.share({ text: text }); }
                        else if (navigator.clipboard) { await navigator.clipboard.writeText(text); alert('Mensagem copiada para a área de transferência.'); }
                    } catch (e) {}
                },
                async toggleRecording() {
                    if (this.isRecording) { this.stopRecording(); return; }
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this.audioChunks = []; this.mediaRecorder = new MediaRecorder(stream);
                        this.mediaRecorder.ondataavailable = (e) => { if (e.data.size > 0) this.audioChunks.push(e.data); };
                        this.mediaRecorder.onstop = () => {
                            stream.getTracks().forEach(track => track.stop());
                            const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
                            const reader = new FileReader();
                            reader.onloadend = () => { this.$wire.sendAudioMessage(reader.result); };
                            reader.readAsDataURL(blob);
                        };
                        this.mediaRecorder.start(); this.isRecording = true; this.recordingTime = 0;
                        this.recordingInterval = setInterval(() => { this.recordingTime++; }, 1000);
                    } catch (err) { console.error('Erro ao acessar microfone:', err); alert('Não foi possível acessar o microfone. Verifique as permissões do navegador.'); }
                },
                stopRecording() {
                    if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') { this.mediaRecorder.stop(); }
                    this.isRecording = false; clearInterval(this.recordingInterval); this.recordingTime = 0;
                }
            }
        }
    </script>

    @push('styles')
        <style>
            [x-cloak] { display: none !important; }
            .chat-scroll::-webkit-scrollbar { width: 6px; }
            .chat-scroll::-webkit-scrollbar-track { background: transparent; }
            .chat-scroll::-webkit-scrollbar-thumb { background-color: rgba(75,85,99,0.6); border-radius: 9999px; }
        </style>
    @endpush
</div>
