<div>
    <div
        x-data="chatComponent()"
        x-init="init()"
        wire:poll.15s="checkForNewMessages"
        class="flex shadow-xl border"
        style="height: {{ request()->routeIs('chat.index') ? '100dvh' : 'calc(100vh - 8rem)' }}; min-height: 540px; border-radius: 1.5rem; overflow: hidden; border-color: #d1d7db;"
    >
        {{-- SIDEBAR --}}
        <div class="border-r flex-col shrink-0 flex" style="background-color:#ffffff; border-color:#e9edef;" :style="{ width: isDesktop ? '22rem' : '100%' }" x-show="isDesktop || mobileView === 'list'">
            <div class="px-5 pt-5 pb-4 shrink-0" style="background-color:#f0f2f5;">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-extrabold tracking-tight" style="color:#111b21;">Chat Interno</h2>
                    @if($avatarUrl = auth()->user()?->getFilamentAvatarUrl())
                        <img src="{{ $avatarUrl }}" class="w-10 h-10 rounded-full object-cover shadow" alt="">
                    @else
                        <div class="w-10 h-10 text-white flex items-center justify-center text-sm font-bold shadow" style="border-radius:9999px; background-color:#00a884;">
                            {{ Str::upper(Str::substr(auth()->user()?->name ?? '?', 0, 1)) }}
                        </div>
                    @endif
                </div>
                <p class="mt-1.5 text-xs font-medium" style="color:#667781;">
                    Você: <span class="font-bold" style="color:#008069;">{{ auth()->user()?->name }}</span>
                </p>
            </div>

            <div class="px-3 pt-2 pb-2 shrink-0" style="background-color:#ffffff;">
                <div class="relative">
                    <x-heroicon-m-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2" style="color:#667781;" />
                    <input type="text" x-model="search" placeholder="Pesquisar conversa..."
                        class="w-full text-sm pl-9 pr-3 py-2.5 border outline-none transition" style="border-radius:9999px; background-color:#f0f2f5; color:#111b21; border-color:#e9edef;">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto chat-scroll" style="background-color:#ffffff;">
                @forelse($this->users as $user)
                    <div
                        wire:click="selectUser('{{ data_get($user, 'id') }}')"
                        @click="mobileView = 'chat'"
                        wire:key="contact-{{ data_get($user, 'id') }}"
                        x-show="search === '' || @js(Str::lower(data_get($user, 'name', '') ?? '')).includes(search.toLowerCase())"
                        class="px-4 py-3 cursor-pointer flex items-center gap-3 transition-colors border-l-2 border-b"
                        style="border-bottom-color:#e9edef; {{ $this->selectedUserId === data_get($user, 'id') ? 'background-color:#f0f2f5; border-left-color:#00a884;' : 'background-color:#ffffff; border-left-color:transparent;' }}"
                    >
                        <div class="relative shrink-0">
                            @if(data_get($user, 'avatar_url'))
                                <img src="{{ data_get($user, 'avatar_url') }}" class="w-11 h-11 rounded-full object-cover" alt="">
                            @else
                                <div class="w-11 h-11 flex items-center justify-center text-base font-bold" style="border-radius:9999px; background-color:#d9fdd3; color:#008069;">
                                    {{ Str::upper(Str::substr(data_get($user, 'name', '?'), 0, 1)) }}
                                </div>
                            @endif
                            <span @class(['absolute bottom-0 right-0 w-3 h-3 border-2','bg-green-500' => data_get($user, 'is_online'),'bg-gray-400' => ! data_get($user, 'is_online')]) style="border-radius:9999px; border-color:#ffffff;"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="block text-sm font-bold truncate" style="color:#111b21;">{{ data_get($user, 'name', 'Usuário') }}</span>
                            <span class="block text-xs truncate" style="color:#667781;">{{ data_get($user, 'department') ?? 'Sem departamento' }}</span>
                        </div>
                        @if(data_get($user, 'unread') > 0)
                            <span class="ml-auto shrink-0 min-w-[20px] h-5 px-1.5 text-white text-[11px] font-bold flex items-center justify-center" style="border-radius:9999px; background-color:#00a884;">
                                {{ data_get($user, 'unread') }}
                            </span>
                        @endif
                    </div>
                @empty
                    <p class="p-4 text-sm" style="color:#667781;">Nenhum contato no momento.</p>
                @endforelse
            </div>

            <div class="shrink-0 border-t p-3" style="background-color:#f0f2f5; border-color:#e9edef;" x-data="{ openDept: false }">
                @php
                    $activeDep = $this->selectedDepartmentId
                        ? optional($this->departments->firstWhere('id', $this->selectedDepartmentId))->name
                        : null;
                @endphp
                <div x-show="openDept" x-cloak class="mb-2 max-h-44 overflow-y-auto chat-scroll space-y-1">
                    <button type="button" wire:click="filterDepartment" @click="openDept = false"
                        class="w-full text-left px-3 py-2 text-sm font-medium transition" style="border-radius:0.5rem; {{ blank($this->selectedDepartmentId) ? 'background-color:#00a884;color:#ffffff;' : 'color:#111b21;' }}">Todos</button>
                    @forelse($this->departments as $dep)
                        <button type="button" wire:click="filterDepartment('{{ $dep->id }}')" @click="openDept = false"
                            @class(['w-full text-left px-3 py-2 text-sm font-medium transition']) style="border-radius:0.5rem; {{ (string) $this->selectedDepartmentId === (string) $dep->id ? 'background-color:#00a884;color:#ffffff;' : 'color:#111b21;' }}">{{ $dep->name }}</button>
                    @empty
                        <p class="px-3 py-2 text-xs" style="color:#667781;">Nenhum departamento cadastrado.</p>
                    @endforelse
                </div>
                <button type="button" @click="openDept = !openDept"
                    class="w-full flex items-center justify-between gap-2 px-3 py-2.5 text-sm font-semibold transition" style="border-radius:0.75rem; background-color:#ffffff; color:#111b21; border:1px solid #e9edef;">
                    <span class="flex items-center gap-2 truncate">
                        <x-heroicon-o-building-office-2 class="w-5 h-5 shrink-0" style="color:#00a884;" />
                        <span class="truncate">Departamento: <span style="color:#008069;">{{ $activeDep ?? 'Todos' }}</span></span>
                    </span>
                    <x-heroicon-m-chevron-up-down class="w-4 h-4 shrink-0" style="color:#667781;" />
                </button>
            </div>
        </div>

        {{-- MAIN CHAT --}}
        <div class="flex-1 flex-col min-w-0 flex" style="background-color:#efeae2;" x-show="isDesktop || mobileView === 'chat'">
            @if($this->selectedUser)
                <div class="px-5 py-3 border-b flex items-center gap-3 shrink-0" style="background-color:#f0f2f5; border-color:#e9edef;">
                    <button @click="mobileView = 'list'" type="button" class="md:hidden -ml-1 mr-1 p-1.5 transition" style="border-radius:0.5rem; color:#54656f;" title="Voltar">
                        <x-heroicon-o-arrow-left class="w-5 h-5" />
                    </button>
                    <div class="relative shrink-0">
                        @if(data_get($this->selectedUser, 'avatar_url'))
                            <img src="{{ data_get($this->selectedUser, 'avatar_url') }}" class="w-10 h-10 rounded-full object-cover" alt="">
                        @else
                            <div class="w-10 h-10 flex items-center justify-center text-base font-bold" style="border-radius:9999px; background-color:#d9fdd3; color:#008069;">
                                {{ Str::upper(Str::substr(data_get($this->selectedUser, 'name', '?'), 0, 1)) }}
                            </div>
                        @endif
                        <span @class(['absolute bottom-0 right-0 w-3 h-3 border-2','bg-green-500' => data_get($this->selectedUser, 'is_online'),'bg-gray-400' => ! data_get($this->selectedUser, 'is_online')]) style="border-radius:9999px; border-color:#f0f2f5;"></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-bold truncate" style="color:#111b21;">{{ data_get($this->selectedUser, 'name', 'Usuário') }}</h3>
                        <p class="text-[11px] font-semibold" style="color: {{ data_get($this->selectedUser, 'is_online') ? '#008069' : '#667781' }};">{{ data_get($this->selectedUser, 'is_online') ? 'Online' : 'Offline' }}</p>
                    </div>
                    @if($this->chatRoom)
                        <a href="{{ route('chat.history.pdf', ['room' => $this->chatRoom->id]) }}" target="_blank"
                           title="Exportar conversa em PDF"
                           class="flex items-center justify-center w-9 h-9 transition shrink-0" style="border-radius:9999px; color:#54656f;">
                            <x-heroicon-o-arrow-down-tray class="w-5 h-5" />
                        </a>
                    @endif
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-4 chat-scroll" x-ref="messagesContainer" style="background-color:#efeae2;">
                    @forelse($this->chatMessages as $msg)
                        <div wire:key="msg-{{ data_get($msg, 'id') }}" @class(['flex w-full','justify-end' => data_get($msg, 'is_mine'),'justify-start' => ! data_get($msg, 'is_mine')])>
                            <div
                                class="max-w-[78%] lg:max-w-md px-4 py-2.5 shadow-sm text-sm font-medium"
                                style="border-radius: {{ data_get($msg, 'is_mine') ? '8px 8px 0 8px' : '8px 8px 8px 0' }}; background-color: {{ data_get($msg, 'is_mine') ? '#d9fdd3' : '#ffffff' }}; color:#111b21;"
                            >
                                <p class="text-xs font-bold mb-0.5" style="color:#008069;">
                                    {{ data_get($msg, 'is_mine') ? 'Eu' : data_get($this->selectedUser, 'name', 'Contato') }}
                                </p>

                                @if($message = data_get($msg, 'message'))
                                    <p class="whitespace-pre-wrap break-words leading-relaxed">{{ $message }}</p>
                                @endif

                                @if(! empty(data_get($msg, 'attachments')))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach(data_get($msg, 'attachments', []) as $imageUrl)
                                            <a href="{{ $imageUrl }}" target="_blank" class="block overflow-hidden shadow-sm hover:opacity-90 transition" style="border-radius:0.75rem; border:1px solid rgba(0,0,0,0.08);">
                                                <img src="{{ $imageUrl }}" alt="Anexo" class="max-w-[160px] max-h-40 object-cover" loading="lazy">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                @if($audio = data_get($msg, 'audio'))
                                    <audio controls src="{{ $audio }}" preload="metadata" class="mt-2 w-56 max-w-full"></audio>
                                    @if($transcript = data_get($msg, 'transcript'))
                                        <p class="mt-1 text-xs italic" style="color:#667781;">
                                            "{{ $transcript }}"
                                        </p>
                                    @endif
                                @endif

                                @if(! empty(data_get($msg, 'documents')))
                                    <div class="mt-2 space-y-1.5">
                                        @foreach(data_get($msg, 'documents', []) as $doc)
                                            <a href="{{ data_get($doc, 'url') }}" target="_blank"
                                               class="flex items-center gap-2 px-3 py-2 transition hover:opacity-80" style="border-radius:0.5rem; border:1px solid rgba(0,0,0,0.08); background-color:rgba(0,0,0,0.02);">
                                                <x-heroicon-o-document-text class="w-5 h-5 shrink-0" style="color:#54656f;" />
                                                <span class="min-w-0 flex-1">
                                                    <span class="block text-xs font-bold truncate" style="color:#111b21;">{{ data_get($doc, 'name') }}</span>
                                                    <span class="block text-[10px]" style="color:#667781;">{{ data_get($doc, 'size') }}</span>
                                                </span>
                                                <x-heroicon-o-arrow-down-tray class="w-4 h-4 shrink-0" style="color:#667781;" />
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex items-center justify-end gap-1.5 mt-1.5 text-[10px] font-semibold" style="color:#667781;">
                                    @if(data_get($msg, 'message'))
                                        <button type="button" @click="shareMessage(@js(data_get($msg, 'message')))" class="opacity-50 hover:opacity-100 transition" title="Compartilhar mensagem">
                                            <x-heroicon-m-share class="w-3.5 h-3.5" />
                                        </button>
                                    @endif
                                    <span>{{ data_get($msg, 'created_at') }}</span>
                                    @if(data_get($msg, 'is_mine'))
                                        @if(data_get($msg, 'is_read'))
                                            <span class="tracking-tighter" style="color:#53bdeb;" title="Lido">✓✓</span>
                                        @elseif(data_get($msg, 'is_delivered'))
                                            <span class="tracking-tighter" style="color:#667781;" title="Entregue">✓✓</span>
                                        @else
                                            <span class="tracking-tighter" style="color:#667781;" title="Enviado">✓</span>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center" style="color:#8696a0;">
                            <div class="p-4 mb-3" style="border-radius:9999px; background-color:rgba(255,255,255,0.6);">
                                <x-heroicon-o-chat-bubble-left-right class="w-8 h-8 opacity-60" />
                            </div>
                            <p class="font-bold text-sm uppercase tracking-widest">Nenhuma mensagem ainda</p>
                        </div>
                    @endforelse
                </div>

                <div wire:loading wire:target="temporaryImage" class="px-5 pb-1 shrink-0" style="background-color:#f0f2f5;">
                    <span class="text-xs animate-pulse" style="color:#667781;">Enviando imagem...</span>
                </div>

                <div wire:loading wire:target="temporaryDocument" class="px-5 pb-1 shrink-0" style="background-color:#f0f2f5;">
                    <span class="text-xs animate-pulse" style="color:#667781;">Enviando documento...</span>
                </div>

                <div x-show="isRecording" x-cloak class="px-5 pb-1 flex items-center gap-2 text-sm shrink-0" style="background-color:#f0f2f5; color:#e63946;">
                    <span class="w-2 h-2 animate-pulse" style="border-radius:9999px; background-color:#e63946;"></span>
                    <span x-text="`Gravando... ${recordingTime}s`"></span>
                </div>

                <div x-show="pendingOfflineCount > 0" x-cloak class="px-5 pb-1 flex items-center gap-2 text-xs shrink-0" style="background-color:#f0f2f5; color:#a17d1a;">
                    <span class="w-1.5 h-1.5" style="border-radius:9999px; background-color:#e0a721;"></span>
                    <span x-text="pendingOfflineCount === 1 ? '1 mensagem aguardando conexão' : `${pendingOfflineCount} mensagens aguardando conexão`"></span>
                </div>

                <div class="p-3 sm:p-4 border-t shrink-0" style="background-color:#f0f2f5; border-color:#e9edef;">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 flex items-center gap-0.5 border px-2 py-1 focus-within:ring-2 transition" style="border-radius:9999px; background-color:#ffffff; border-color:#e9edef;">
                            <span class="flex items-center justify-center w-9 h-9 text-lg leading-none select-none shrink-0">😊</span>
                            <input type="text" id="chat-draft-message" x-model="draftMessage" x-on:input="hasText = $event.target.value.trim().length > 0" @keydown.enter="sendOrQueue()" :disabled="isRecording"
                                class="flex-1 min-w-0 bg-transparent px-2 py-2 outline-none border-0 focus:ring-0 text-sm font-medium" style="color:#111b21;" placeholder="Digite uma mensagem...">
                            <label title="Anexar imagem" class="flex items-center justify-center w-9 h-9 cursor-pointer transition shrink-0" style="border-radius:9999px; color:#54656f;">
                                <input type="file" wire:model="temporaryImage" accept="image/*" class="hidden">
                                <div wire:loading wire:target="temporaryImage" class="animate-spin h-5 w-5 border-2 border-t-transparent" style="border-radius:9999px; border-color:#00a884;"></div>
                                <x-heroicon-s-paper-clip class="w-5 h-5" wire:loading.remove wire:target="temporaryImage" />
                            </label>
                            <label title="Tirar foto" class="flex items-center justify-center w-9 h-9 cursor-pointer transition shrink-0" style="border-radius:9999px; color:#54656f;">
                                <input type="file" wire:model="temporaryImage" accept="image/*" capture="environment" class="hidden">
                                <x-heroicon-s-camera class="w-5 h-5" />
                            </label>
                            <label title="Anexar documento" class="flex items-center justify-center w-9 h-9 cursor-pointer transition shrink-0" style="border-radius:9999px; color:#54656f;">
                                <input type="file" wire:model="temporaryDocument" accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.csv" class="hidden">
                                <div wire:loading wire:target="temporaryDocument" class="animate-spin h-5 w-5 border-2 border-t-transparent" style="border-radius:9999px; border-color:#00a884;"></div>
                                <x-heroicon-s-document-text class="w-5 h-5" wire:loading.remove wire:target="temporaryDocument" />
                            </label>
                        </div>
                        <button type="button" x-show="dictationSupported && !isRecording" x-cloak class="oravel-mic-btn flex items-center justify-center w-11 h-11 border shadow-sm transition shrink-0" data-mic-target="chat-draft-message"
                            :style="{ color: isDictating ? '#e63946' : '#54656f', borderColor: isDictating ? '#e63946' : '#e9edef' }" :title="isDictating ? 'Parar ditado' : 'Ditar mensagem por voz'" style="border-radius:9999px; background-color:#ffffff;">
                            <span x-text="isDictating ? '⏹' : '🎙️'"></span>
                        </button>
                        <button type="button" x-show="hasText && !isRecording" x-cloak @click="sendOrQueue()"
                            class="flex items-center justify-center w-12 h-12 text-white shadow-lg transition shrink-0" style="border-radius:9999px; background-color:#00a884;" title="Enviar">
                            <x-heroicon-s-paper-airplane class="w-5 h-5" />
                        </button>
                        <button type="button" x-show="!hasText || isRecording" x-cloak @click="toggleRecording()" :disabled="isDictating"
                            class="flex items-center justify-center w-12 h-12 text-white shadow-lg transition shrink-0 text-xl leading-none disabled:opacity-40" style="border-radius:9999px;" :style="{ backgroundColor: isRecording ? '#e63946' : '#00a884' }" title="Gravar áudio">
                            <span x-text="isRecording ? '⏹️' : '🎤'"></span>
                        </button>
                    </div>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center" style="background-color:#f0f2f5; color:#8696a0;">
                    <div class="p-4 mb-3" style="border-radius:9999px; background-color:#ffffff;">
                        <x-heroicon-o-chat-bubble-left-right class="w-8 h-8 opacity-60" />
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
                isDictating: false, dictationSupported: !!(window.webkitSpeechRecognition || window.SpeechRecognition),
                init() {
                    this.isDesktop = window.innerWidth >= 768;
                    window.addEventListener('resize', () => { this.isDesktop = window.innerWidth >= 768; });
                    this.$wire.on('scroll-to-bottom', () => { this.$nextTick(() => this.scrollToBottom()); });
                    this.$nextTick(() => this.scrollToBottom());

                    // Ditado por voz do campo de mensagem, reaproveitando o
                    // mesmo mecanismo (Web Speech API nativa do navegador)
                    // já usado em step-damages.blade.php (wizard de OS em
                    // campo) -- diferente do botão de microfone abaixo, que
                    // grava áudio (MediaRecorder) e envia como anexo.
                    window.addEventListener('chat-dictation-result', (e) => {
                        const transcript = e.detail.transcript;
                        this.draftMessage = this.draftMessage ? this.draftMessage.trim() + ' ' + transcript : transcript;
                        this.hasText = this.draftMessage.trim().length > 0;
                    });
                    window.addEventListener('chat-dictation-state', (e) => { this.isDictating = e.detail.listening; });

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

    {{--
        Ditado por voz do campo de mensagem -- mesmo padrão de delegação de
        evento (.oravel-mic-btn + data-mic-target) usado em
        step-damages.blade.php (wizard de OS em campo), reaproveitado aqui.
        Diferença: lá o campo é controlado por wire:model, então o script
        escreve direto em field.value + dispatchEvent('input'). Aqui o campo
        de mensagem é Alpine puro (x-model="draftMessage"), então em vez de
        tocar no DOM o script dispara eventos customizados que o componente
        Alpine (chatComponent(), acima) escuta e usa pra atualizar seu
        próprio estado.
    --}}
    @script
        <script>
            (function () {
                if (window.__oravelMicDelegated) return;
                window.__oravelMicDelegated = true;

                const SpeechRecognition = window.webkitSpeechRecognition || window.SpeechRecognition;
                let recognition = null;
                let isListening = false;
                let activeTargetId = null;

                function setListening(listening) {
                    window.dispatchEvent(new CustomEvent('chat-dictation-state', { detail: { listening } }));
                }

                function stopListening() {
                    isListening = false;
                    activeTargetId = null;
                    setListening(false);
                }

                document.addEventListener('click', function (e) {
                    const button = e.target.closest('.oravel-mic-btn');
                    if (!button) return;

                    e.preventDefault();

                    const targetId = button.dataset.micTarget;

                    if (!SpeechRecognition) {
                        button.disabled = true;
                        return;
                    }

                    if (isListening) {
                        recognition?.stop();
                        return;
                    }

                    recognition = new SpeechRecognition();
                    recognition.lang = 'pt-BR';
                    recognition.continuous = false;
                    recognition.interimResults = true;

                    recognition.onresult = function (event) {
                        const last = event.results[event.results.length - 1];
                        if (!last.isFinal) return;

                        let transcript = '';
                        for (let i = event.resultIndex; i < event.results.length; i++) {
                            transcript += event.results[i][0].transcript + ' ';
                        }
                        transcript = transcript.trim();
                        if (!transcript) return;

                        window.dispatchEvent(new CustomEvent('chat-dictation-result', { detail: { transcript } }));
                    };

                    recognition.onerror = function () {
                        stopListening();
                    };

                    recognition.onend = function () {
                        stopListening();
                    };

                    document.getElementById(targetId)?.focus();
                    recognition.start();
                    isListening = true;
                    activeTargetId = targetId;
                    setListening(true);
                });
            })();
        </script>
    @endscript
</div>
