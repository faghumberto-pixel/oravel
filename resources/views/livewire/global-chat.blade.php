<div>
    <div
        x-data="chatComponent()"
        x-init="init()"
        wire:poll.15s
        class="flex bg-gray-950 shadow-xl border border-gray-800"
        style="height: calc(100vh - 8rem); min-height: 540px; border-radius: 1.5rem; overflow: hidden;"
    >
        {{-- SIDEBAR --}}
        <div class="border-r border-gray-800 flex-col bg-gray-900 shrink-0 flex" :style="isDesktop ? 'width: 22rem' : 'width: 100%'" x-show="isDesktop || mobileView === 'list'">
            <div class="px-5 pt-5 pb-4 shrink-0">
                <div class="flex items-center justify-between">
                    <h2 class="text-2xl font-extrabold tracking-tight text-white">Chat Interno</h2>
                    <div class="w-10 h-10 bg-primary-500 text-white flex items-center justify-center text-sm font-bold shadow" style="border-radius:9999px;">
                        {{ Str::upper(Str::substr(auth()->user()?->name ?? '?', 0, 1)) }}
                    </div>
                </div>
                <p class="mt-1.5 text-xs font-medium text-gray-400">
                    Você: <span class="font-bold text-primary-400">{{ auth()->user()?->name }}</span>
                </p>
            </div>

            <div class="px-4 pt-1 pb-4 shrink-0">
                <div class="relative">
                    <x-heroicon-m-magnifying-glass class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
                    <input type="text" x-model="search" placeholder="Pesquisar conversa..."
                        class="w-full bg-gray-800 text-sm text-white placeholder-gray-500 pl-9 pr-3 py-2.5 border border-gray-700 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none transition" style="border-radius:9999px;">
                </div>
            </div>

            <div class="flex-1 overflow-y-auto chat-scroll">
                @forelse($this->users as $user)
                    <div
                        wire:click="selectUser({{ data_get($user, 'id') }})"
                        @click="mobileView = 'chat'"
                        wire:key="contact-{{ data_get($user, 'id') }}"
                        x-show="search === '' || @js(Str::lower(data_get($user, 'name', '') ?? '')).includes(search.toLowerCase())"
                        @class([
                            'px-4 py-3 cursor-pointer flex items-center gap-3 transition-colors border-l-2',
                            'bg-primary-900/20 border-primary-500' => $this->selectedUserId === data_get($user, 'id'),
                            'border-transparent hover:bg-gray-800/60' => $this->selectedUserId !== data_get($user, 'id'),
                        ])
                    >
                        <div class="relative shrink-0">
                            <div class="w-11 h-11 bg-primary-900/40 text-primary-300 flex items-center justify-center text-base font-bold" style="border-radius:9999px;">
                                {{ Str::upper(Str::substr(data_get($user, 'name', '?'), 0, 1)) }}
                            </div>
                            <span @class(['absolute bottom-0 right-0 w-3 h-3 border-2 border-gray-900','bg-green-500' => data_get($user, 'is_online'),'bg-gray-600' => ! data_get($user, 'is_online')]) style="border-radius:9999px;"></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-gray-100 truncate">{{ data_get($user, 'name', 'Usuário') }}</span>
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

            <div class="shrink-0 border-t border-gray-800/70 p-3" x-data="{ openDept: false }">
                @php
                    $activeDep = $this->selectedDepartmentId
                        ? optional($this->departments->firstWhere('id', $this->selectedDepartmentId))->name
                        : null;
                @endphp
                <div x-show="openDept" x-cloak class="mb-2 max-h-44 overflow-y-auto chat-scroll space-y-1">
                    <button type="button" wire:click="filterDepartment" @click="openDept = false"
                        @class(['w-full text-left px-3 py-2 text-sm font-medium transition','bg-primary-500 text-white' => blank($this->selectedDepartmentId),'text-gray-300 hover:bg-gray-800' => filled($this->selectedDepartmentId)]) style="border-radius:0.5rem;">Todos</button>
                    @forelse($this->departments as $dep)
                        <button type="button" wire:click="filterDepartment('{{ $dep->id }}')" @click="openDept = false"
                            @class(['w-full text-left px-3 py-2 text-sm font-medium transition','bg-primary-500 text-white' => (string) $this->selectedDepartmentId === (string) $dep->id,'text-gray-300 hover:bg-gray-800' => (string) $this->selectedDepartmentId !== (string) $dep->id]) style="border-radius:0.5rem;">{{ $dep->name }}</button>
                    @empty
                        <p class="px-3 py-2 text-xs text-gray-500">Nenhum departamento cadastrado.</p>
                    @endforelse
                </div>
                <button type="button" @click="openDept = !openDept"
                    class="w-full flex items-center justify-between gap-2 px-3 py-2.5 bg-gray-800 text-sm font-semibold text-gray-200 hover:bg-gray-700 transition" style="border-radius:0.75rem;">
                    <span class="flex items-center gap-2 truncate">
                        <x-heroicon-o-building-office-2 class="w-5 h-5 text-primary-400 shrink-0" />
                        <span class="truncate">Departamento: <span class="text-primary-400">{{ $activeDep ?? 'Todos' }}</span></span>
                    </span>
                    <x-heroicon-m-chevron-up-down class="w-4 h-4 shrink-0" />
                </button>
            </div>
        </div>

        {{-- MAIN CHAT --}}
        <div class="flex-1 flex-col min-w-0 flex bg-gray-950" x-show="isDesktop || mobileView === 'chat'">
            @if($this->selectedUser)
                <div class="px-5 py-4 bg-gray-900 border-b border-gray-800 flex items-center gap-3 shrink-0">
                    <button @click="mobileView = 'list'" type="button" class="md:hidden -ml-1 mr-1 p-1.5 text-gray-300 hover:bg-gray-800 transition" style="border-radius:0.5rem;" title="Voltar">
                        <x-heroicon-o-arrow-left class="w-5 h-5" />
                    </button>
                    <div class="relative shrink-0">
                        <div class="w-10 h-10 bg-primary-900/40 text-primary-300 flex items-center justify-center text-base font-bold" style="border-radius:9999px;">
                            {{ Str::upper(Str::substr(data_get($this->selectedUser, 'name', '?'), 0, 1)) }}
                        </div>
                        <span @class(['absolute bottom-0 right-0 w-3 h-3 border-2 border-gray-900','bg-green-500' => data_get($this->selectedUser, 'is_online'),'bg-gray-600' => ! data_get($this->selectedUser, 'is_online')]) style="border-radius:9999px;"></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-sm font-bold text-white truncate">{{ data_get($this->selectedUser, 'name', 'Usuário') }}</h3>
                        <p @class(['text-[11px] font-semibold','text-green-400' => data_get($this->selectedUser, 'is_online'),'text-gray-500' => ! data_get($this->selectedUser, 'is_online')])>{{ data_get($this->selectedUser, 'is_online') ? 'Online' : 'Offline' }}</p>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-5 space-y-4 chat-scroll" x-ref="messagesContainer" style="background-color:#0b141a;">
                    @forelse($this->chatMessages as $msg)
                        <div wire:key="msg-{{ data_get($msg, 'id') }}" @class(['flex w-full','justify-end' => data_get($msg, 'is_mine'),'justify-start' => ! data_get($msg, 'is_mine')])>
                            <div
                                @class([
                                    'max-w-[78%] lg:max-w-md px-4 py-2.5 shadow-sm text-sm font-medium',
                                    'bg-primary-600 text-white' => data_get($msg, 'is_mine'),
                                    'bg-gray-800 text-gray-100 border border-gray-700' => ! data_get($msg, 'is_mine'),
                                ])
                                style="border-radius: {{ data_get($msg, 'is_mine') ? '18px 18px 4px 18px' : '18px 18px 18px 4px' }};"
                            >
                                <p @class(['text-xs font-bold mb-0.5','text-white/90' => data_get($msg, 'is_mine'),'text-primary-400' => ! data_get($msg, 'is_mine')])>
                                    {{ data_get($msg, 'is_mine') ? 'Eu' : data_get($this->selectedUser, 'name', 'Contato') }}
                                </p>

                                @if($message = data_get($msg, 'message'))
                                    <p class="whitespace-pre-wrap break-words leading-relaxed">{{ $message }}</p>
                                @endif

                                @if(! empty(data_get($msg, 'attachments')))
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach(data_get($msg, 'attachments', []) as $imageUrl)
                                            <a href="{{ $imageUrl }}" target="_blank" class="block border border-white/10 overflow-hidden shadow-sm hover:opacity-90 transition" style="border-radius:0.75rem;">
                                                <img src="{{ $imageUrl }}" alt="Anexo" class="max-w-[160px] max-h-40 object-cover" loading="lazy">
                                            </a>
                                        @endforeach
                                    </div>
                                @endif

                                @if($audio = data_get($msg, 'audio'))
                                    <audio controls src="{{ $audio }}" preload="metadata" class="mt-2 w-56 max-w-full"></audio>
                                @endif

                                <div @class(['flex items-center justify-end gap-1.5 mt-1.5 text-[10px] font-semibold','text-white/70' => data_get($msg, 'is_mine'),'text-gray-400' => ! data_get($msg, 'is_mine')])>
                                    @if(data_get($msg, 'message'))
                                        <button type="button" @click="shareMessage(@js(data_get($msg, 'message')))" class="opacity-50 hover:opacity-100 transition" title="Compartilhar mensagem">
                                            <x-heroicon-m-share class="w-3.5 h-3.5" />
                                        </button>
                                    @endif
                                    <span>{{ data_get($msg, 'created_at') }}</span>
                                    @if(data_get($msg, 'is_mine'))
                                        <span @class(['tracking-tighter','text-sky-300' => data_get($msg, 'is_read'),'text-white/50' => ! data_get($msg, 'is_read')]) title="{{ data_get($msg, 'is_read') ? 'Lido' : 'Enviado' }}">✓✓</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="h-full flex flex-col items-center justify-center text-gray-600">
                            <div class="bg-gray-800/50 p-4 border border-gray-700 mb-3" style="border-radius:9999px;">
                                <x-heroicon-o-chat-bubble-left-right class="w-8 h-8 opacity-50" />
                            </div>
                            <p class="font-bold text-sm uppercase tracking-widest">Nenhuma mensagem ainda</p>
                        </div>
                    @endforelse
                </div>

                <div wire:loading wire:target="temporaryImage" class="px-5 pb-1 shrink-0" style="background-color:#0b141a;">
                    <span class="text-xs text-gray-400 animate-pulse">Enviando imagem...</span>
                </div>

                <div x-show="isRecording" x-cloak class="px-5 pb-1 flex items-center gap-2 text-red-400 text-sm shrink-0" style="background-color:#0b141a;">
                    <span class="w-2 h-2 bg-red-500 animate-pulse" style="border-radius:9999px;"></span>
                    <span x-text="`Gravando... ${recordingTime}s`"></span>
                </div>

                <div class="p-3 sm:p-4 border-t border-gray-800/70 shrink-0" style="background-color:#0b141a;">
                    <div class="flex items-center gap-2">
                        <div class="flex-1 flex items-center gap-0.5 bg-gray-800 border border-gray-700 px-2 py-1 focus-within:ring-2 focus-within:ring-primary-500 transition" style="border-radius:9999px;">
                            <span class="flex items-center justify-center w-9 h-9 text-lg leading-none select-none shrink-0">😊</span>
                            <input type="text" wire:model="newMessage" x-on:input="hasText = $event.target.value.trim().length > 0" wire:keydown.enter="sendMessage" @keydown.enter="hasText = false" :disabled="isRecording"
                                class="flex-1 bg-transparent text-white placeholder-gray-500 px-2 py-2 outline-none border-0 focus:ring-0 text-sm font-medium" placeholder="Digite uma mensagem...">
                            <label title="Anexar imagem" class="flex items-center justify-center w-9 h-9 cursor-pointer text-gray-400 hover:text-primary-400 transition shrink-0" style="border-radius:9999px;">
                                <input type="file" wire:model="temporaryImage" accept="image/*" class="hidden">
                                <div wire:loading wire:target="temporaryImage" class="animate-spin h-5 w-5 border-2 border-primary-500 border-t-transparent" style="border-radius:9999px;"></div>
                                <x-heroicon-s-paper-clip class="w-5 h-5" wire:loading.remove wire:target="temporaryImage" />
                            </label>
                            <label title="Tirar foto" class="flex items-center justify-center w-9 h-9 cursor-pointer text-gray-400 hover:text-primary-400 transition shrink-0" style="border-radius:9999px;">
                                <input type="file" wire:model="temporaryImage" accept="image/*" capture="environment" class="hidden">
                                <x-heroicon-s-camera class="w-5 h-5" />
                            </label>
                        </div>
                        <button type="button" x-show="hasText && !isRecording" x-cloak @click="$wire.sendMessage(); hasText = false"
                            class="flex items-center justify-center w-12 h-12 bg-primary-600 hover:bg-primary-700 text-white shadow-lg transition shrink-0" style="border-radius:9999px;" title="Enviar">
                            <x-heroicon-s-paper-airplane class="w-5 h-5" />
                        </button>
                        <button type="button" x-show="!hasText || isRecording" x-cloak @click="toggleRecording()"
                            :class="isRecording ? 'bg-red-600 hover:bg-red-700' : 'bg-primary-600 hover:bg-primary-700'"
                            class="flex items-center justify-center w-12 h-12 text-white shadow-lg transition shrink-0 text-xl leading-none" style="border-radius:9999px;" title="Gravar áudio">
                            <span x-text="isRecording ? '⏹️' : '🎤'"></span>
                        </button>
                    </div>
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center text-gray-600" style="background-color:#0b141a;">
                    <div class="bg-gray-800/50 p-4 border border-gray-700 mb-3" style="border-radius:9999px;">
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
                init() {
                    this.isDesktop = window.innerWidth >= 768;
                    window.addEventListener('resize', () => { this.isDesktop = window.innerWidth >= 768; });
                    this.$wire.on('scroll-to-bottom', () => { this.$nextTick(() => this.scrollToBottom()); });
                    this.$nextTick(() => this.scrollToBottom());
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
