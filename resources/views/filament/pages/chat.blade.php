<div class="w-full" style="margin-top: 1rem; box-sizing: border-box; font-family: system-ui, -apple-system, sans-serif;">
    
    {{-- LAYOUT PREMIUM COM POLLING AMARRADO AO REFRESH INTERNO E COMPONENTE OFFLINE ALPINE --}}
    <div wire:poll.4s="autoRefresh" 
         x-data="{ 
            isOnline: navigator.onLine,
            offlineQueue: $persist([]),
            localMessage: '',
            init() {
                window.addEventListener('online', () => { 
                    this.isOnline = true; 
                    this.flushOfflineQueue();
                });
                window.addEventListener('offline', () => { this.isOnline = false; });
            },
            triggerSend() {
                if (!this.localMessage.trim()) return;
                
                if (this.isOnline) {
                    @this.set('newMessage', this.localMessage);
                    @this.sendMessage();
                    this.localMessage = '';
                } else {
                    // Guarda o texto localmente se estiver sem sinal
                    this.offlineQueue.push({
                        text: this.localMessage,
                        context_type: @this.get('contextType'),
                        context_id: @this.get('contextId'),
                        timestamp: new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'})
                    });
                    this.localMessage = '';
                    alert('Modo Offline: Mensagem salva no dispositivo. Será enviada assim que a conexão retornar.');
                }
            },
            flushOfflineQueue() {
                if (this.offlineQueue.length === 0) return;
                this.offlineQueue.forEach(msg => {
                    @this.set('contextType', msg.context_type);
                    @this.set('contextId', msg.context_id);
                    @this.set('newMessage', msg.text);
                    @this.sendMessage();
                });
                this.offlineQueue = [];
            }
         }"
         class="oravel-chat-grid">
        
        {{-- BARRA LATERAL: CONTATOS CORPORATIVOS --}}
        <div style="background-color: #1a1c21; border-right: 1px solid rgba(255, 255, 255, 0.05); display: flex; flex-direction: column; height: 100%; overflow: hidden;">
            
            <div style="padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05); background-color: #111215; display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="font-size: 15px; font-weight: 900; color: #f59e0b; margin: 0; text-transform: uppercase; letter-spacing: 1px;">Central Corporativa</h3>
                    {{-- INDICADOR DE CONEXÃO DO CHÃO DE FÁBRICA --}}
                    <span x-text="isOnline ? '🌐 ONLINE' : '🚨 OFFLINE'" 
                          x-bind:style="isOnline ? 'color: #10b981' : 'color: #ef4444'" 
                          style="font-size: 9px; font-weight: 900; letter-spacing: 1px;"></span>
                </div>
                
                <div style="position: relative; display: flex; align-items: center;">
                    <input type="text" 
                           wire:model="searchQuery"
                           @keydown.enter="$wire.autoRefresh()"
                           placeholder="Pesquisar termo e dar Enter..." 
                           style="width: 100%; background-color: #1a1c21; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 12px; padding: 0.6rem 1rem 0.6rem 2.5rem; font-size: 12px; color: #ffffff; outline: none; box-sizing: border-box; transition: all 0.2s;"
                           onfocus="this.style.borderColor='#f59e0b'"
                           onblur="this.style.borderColor='rgba(245, 158, 11, 0.3)'">
                    <x-heroicon-m-magnifying-glass style="position: absolute; left: 10px; width: 16px; height: 16px; color: #6b7280;" />
                    
                    @if(!empty($searchQuery))
                        <button wire:click="$set('searchQuery', ''); $wire.autoRefresh();" style="position: absolute; right: 10px; background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 11px; font-weight: bold;">✕</button>
                    @endif
                </div>
            </div>
            
            <div class="custom-chat-scrollbar" style="flex: 1; overflow-y: auto; padding: 0.5rem 0;">
                @if($this->chats && count($this->chats) > 0)
                    @foreach($this->chats as $userItem)
                        @php 
                            $isOnline = \Illuminate\Support\Facades\Cache::has('user-online-' . $userItem->id);
                        @endphp
                        <div wire:click="selectChat({{ $userItem->id }})" 
                             style="padding: 1rem 1.5rem; cursor: pointer; display: flex; align-items: center; gap: 1rem; position: relative; border-bottom: 1px solid rgba(255, 255, 255, 0.02); transition: all 0.2s;
                                    {{ $activeChatId == $userItem->id ? 'background-color: rgba(245, 158, 11, 0.08);' : '' }}"
                             onmouseover="this.style.backgroundColor='rgba(255,255,255,0.02)'" 
                             onmouseout="this.style.backgroundColor='{{ $activeChatId == $userItem->id ? 'rgba(245, 158, 11, 0.08)' : 'transparent' }}'">
                            
                            <div style="position: relative; flex-shrink: 0;">
                                <div style="width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;
                                            {{ $activeChatId == $userItem->id ? 'background-color: #f59e0b; color: #000000;' : 'background-color: #2a2d34; color: #f59e0b;' }}">
                                    {{ strtoupper(substr($userItem->name, 0, 2)) }}
                                </div>
                                <span style="position: absolute; bottom: 0; right: 0; width: 11px; height: 11px; border-radius: 50%; border: 2px solid #1a1c21;
                                             background-color: {{ $isOnline ? '#10b981' : '#ef4444' }}; transition: background-color 0.3s ease;"></span>
                            </div>

                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 13px; font-weight: 700; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #f59e0b;">
                                    {{ $userItem->name }}
                                </div>
                                <div style="font-size: 10px; color: {{ $isOnline ? '#10b981' : '#6b7280' }}; text-transform: uppercase; margin-top: 2px; font-weight: 600;">
                                    {{ $isOnline ? 'Online' : 'Offline' }}
                                </div>
                            </div>

                            @if($activeChatId == $userItem->id)
                                <div style="position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background-color: #f59e0b; box-shadow: 4px 0 15px #f59e0b; border-radius: 0 4px 4px 0;"></div>
                            @endif
                        </div>
                    @endforeach
                @else
                    <div style="padding: 2rem; text-align: center; font-size: 10px; color: #4b5563; font-weight: 700; text-transform: uppercase;">Nenhum contato correspondente</div>
                @endif
            </div>
        </div>

        {{-- PAINEL DA CONVERSA ACTIVA --}}
        <div style="background-color: #0e1013; display: flex; flex-direction: column; height: 100%; overflow: hidden;">
            
            <div style="padding: 1rem 2rem; background-color: #1a1c21; border-bottom: 1px solid rgba(255, 255, 255, 0.05); display: flex; flex-direction: column; min-height: auto; box-sizing: border-box; gap: 0.8rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    @if($activeChatId)
                        @php 
                            $activeUser = \App\Models\User::find($activeChatId);
                            $activeUserOnline = \Illuminate\Support\Facades\Cache::has('user-online-' . $activeChatId);
                        @endphp
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="position: relative; width: 40px; height: 40px; border-radius: 50%; background-color: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); display: flex; align-items: center; justify-content: center; font-weight: 800; color: #f59e0b;">
                                {{ strtoupper(substr($activeUser->name ?? 'US', 0, 2)) }}
                                <span style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; border-radius: 50%; border: 2px solid #1a1c21; background-color: {{ $activeUserOnline ? '#10b981' : '#ef4444' }};"></span>
                            </div>
                            <div>
                                <h4 style="font-size: 14px; font-weight: 900; color: #f59e0b; margin: 0; text-transform: uppercase;">{{ $activeUser->name ?? 'Usuário' }}</h4>
                                <p style="font-size: 10px; color: {{ $activeUserOnline ? '#10b981' : '#ef4444' }}; font-weight: 700; margin: 0; text-transform: uppercase;">
                                    {{ $activeUserOnline ? 'Conectado' : 'Ausente' }}
                                </p>
                            </div>
                        </div>

                        <a href="/admin/{{ \Filament\Facades\Filament::getTenant()?->slug ?? \Filament\Facades\Filament::getTenant()?->id }}/chat/print/{{ $activeChatId }}" 
                           target="_blank" 
                           style="display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.2rem; background-color: #f59e0b; color: #000000; border-radius: 2rem; font-size: 11px; font-weight: 900; text-decoration: none; box-shadow: 0 10px 20px rgba(245, 158, 11, 0.2); border: none;">
                            <x-heroicon-m-printer style="width: 16px; height: 16px;" />
                            IMPRIMIR
                        </a>
                    @else
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background-color: #27272a; display: flex; align-items: center; justify-content: center; color: #f59e0b;">
                                <x-heroicon-s-chat-bubble-left-right style="width: 20px; height: 20px;" />
                            </div>
                            <div>
                                <h4 style="font-size: 13px; font-weight: 900; color: #6b7280; margin: 0; text-transform: uppercase;">Aguardando Seleção</h4>
                                <p style="font-size: 10px; color: #4b5563; font-weight: 700; margin: 0; text-transform: uppercase;">Selecione um funcionário</p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- SELETOR DE ABAS OPERACIONAIS --}}
                @if($activeChatId)
                    <div style="display: flex; gap: 0.5rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 0.6rem; overflow-x: auto; white-space: nowrap;">
                        <button wire:click="setContext('geral', null)" style="padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 11px; font-weight: bold; cursor: pointer; border: none; flex-shrink: 0; {{ $contextType == 'geral' ? 'background:#f59e0b; color:#000000;' : 'background:#2a2d34; color:#ffffff;' }}">💬 Geral</button>
                        <button wire:click="setContext('os', null)" style="padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 11px; font-weight: bold; cursor: pointer; border: none; flex-shrink: 0; {{ $contextType == 'os' ? 'background:#f59e0b; color:#000000;' : 'background:#2a2d34; color:#ffffff;' }}">🛠️ O.S.</button>
                        <button wire:click="setContext('asset', null)" style="padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 11px; font-weight: bold; cursor: pointer; border: none; flex-shrink: 0; {{ $contextType == 'asset' ? 'background:#f59e0b; color:#000000;' : 'background:#2a2d34; color:#ffffff;' }}">🚜 Frota</button>
                        <button wire:click="setContext('material', null)" style="padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 11px; font-weight: bold; cursor: pointer; border: none; flex-shrink: 0; {{ $contextType == 'material' ? 'background:#f59e0b; color:#000000;' : 'background:#2a2d34; color:#ffffff;' }}">📦 Materiais</button>
                    </div>

                    {{-- ESTEIRA HORIZONTAL DE DOCUMENTOS --}}
                    <div style="background-color: #111215; padding: 0.5rem 1rem; border-radius: 10px; display: flex; gap: 0.5rem; overflow-x: auto; align-items: center; border: 1px solid rgba(255,255,255,0.02);">
                        @if($contextType == 'os')
                            <span style="font-size: 10px; color:#f59e0b; font-weight: 800; text-transform: uppercase; white-space: nowrap;">O.S.:</span>
                            @forelse($this->activeOrders as $os)
                                <button wire:click="$set('contextId', '{{ $os->id }}')" style="padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 10px; font-weight: 700; border: 1px solid #f59e0b; cursor: pointer; white-space: nowrap; {{ $contextId == $os->id ? 'background:#f59e0b; color:black;' : 'background:transparent; color:white;' }}">#{{ $os->id }}</button>
                            @empty
                                <span style="font-size: 11px; color:#6b7280; font-style: italic;">⚠ Nenhum evento relacionado</span>
                            @endforelse
                        @elseif($contextType == 'asset')
                            <span style="font-size: 10px; color:#f59e0b; font-weight: 800; text-transform: uppercase; white-space: nowrap;">Ativos:</span>
                            @forelse($this->activeAssets as $asset)
                                <button wire:click="$set('contextId', '{{ $asset->id }}')" style="padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 10px; font-weight: 700; border: 1px solid #f59e0b; cursor: pointer; white-space: nowrap; {{ $contextId == $asset->id ? 'background:#f59e0b; color:black;' : 'background:transparent; color:white;' }}">{{ $asset->name }}</button>
                            @empty
                                <span style="font-size: 11px; color:#6b7280; font-style: italic;">⚠ Nenhum evento relacionado</span>
                            @endforelse
                        @elseif($contextType == 'material')
                            <span style="font-size: 10px; color:#f59e0b; font-weight: 800; text-transform: uppercase; white-space: nowrap;">Pedidos:</span>
                            @forelse($this->activeMaterialRequests as $req)
                                <button wire:click="$set('contextId', '{{ $req->id }}')" style="padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 10px; font-weight: 700; border: 1px solid #f59e0b; cursor: pointer; white-space: nowrap; {{ $contextId == $req->id ? 'background:#f59e0b; color:black;' : 'background:transparent; color:white;' }}">Req #{{ substr($req->id, 0, 6) }}</button>
                            @empty
                                <span style="font-size: 11px; color:#6b7280; font-style: italic;">⚠ Nenhum evento relacionado</span>
                            @endforelse
                        @else
                            <span style="font-size: 11px; color:#6b7280; font-style: italic;">Canal livre.</span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- HISTÓRICO DE MENSAGENS --}}
            <div class="custom-chat-scrollbar" 
                 style="flex: 1; overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem; background-color: #0b0c0e;"
                 x-data="{}" x-init="$el.scrollTop = $el.scrollHeight" x-on:scroll-to-bottom.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight; })">
                
                @if($activeChatId)
                    {{-- MOSTRA MENSAGENS SALVAS NA ESTEIRA LOCAL OFFLINE SE HOUVER --}}
                    <template x-for="msg in offlineQueue" :key="msg.timestamp">
                        <div style="display: flex; flex-direction: column; width: 100%; align-items: flex-end; opacity: 0.6;">
                            <div style="max-width: 70%;">
                                <div style="padding: 0.8rem 1.5rem; background-color: #2e323b; border: 1px dashed #f59e0b; border-radius: 2rem 2rem 0px 2rem; box-shadow: 0 8px 25px rgba(0,0,0,0.4);">
                                    <p style="margin: 0; font-size: 14px; font-weight: 500; line-height: 1.5; color: #ffffff;" x-text="msg.text"></p>
                                    <div style="display: flex; justify-content: flex-end; align-items: center; gap: 4px; margin-top: 4px;">
                                        <span style="font-size: 9px; color: #f59e0b; font-weight: 700;">Aguardando Conexão...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    @foreach($this->messages as $msg)
                        @php $isMine = $msg->user_id == auth()->id(); @endphp
                        <div style="display: flex; flex-direction: column; width: 100%; align-items: {{ $isMine ? 'flex-end' : 'flex-start' }};">
                            <div style="max-width: 75%;">
                                @if(!$isMine)
                                    <div style="font-size: 10px; font-weight: 900; color: #f59e0b; text-transform: uppercase; margin-bottom: 4px; margin-left: 12px; font-style: italic;">
                                        {{ $msg->user->name }}
                                    </div>
                                @endif

                                @php
                                    $containsTerm = !empty($searchQuery) && str_contains(strtolower($msg->message), strtolower($searchQuery));
                                    $borderStyle = $containsTerm ? 'border: 2px solid #f59e0b; box-shadow: 0 0 15px rgba(245, 158, 11, 0.4);' : 'border: 1px solid;';
                                @endphp

                                <div style="padding: 0.8rem 1.2rem; {{ $borderStyle }} box-shadow: 0 8px 25px rgba(0,0,0,0.4);
                                            {{ $isMine 
                                                ? 'background-color: #1f2228; border-color: rgba(245, 158, 11, 0.35); border-radius: 2rem 2rem 0px 2rem;' 
                                                : 'background-color: #1a1c21; border-color: rgba(255, 255, 255, 0.08); border-radius: 2rem 2rem 2rem 0px;' }}">
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        
                                        @php
                                            $isDownloadFile = str_contains($msg->message, 'FILE_DOWNLOAD|');
                                            $isImage = false;
                                            $filename = '';
                                            $filepath = '';

                                            if ($isDownloadFile) {
                                                $parts = explode('|', $msg->message);
                                                $filename = $parts[1] ?? '';
                                                $filepath = $parts[2] ?? '';
                                                
                                                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                                                    $isImage = true;
                                                }
                                            }
                                        @endphp

                                        @if(!$isDownloadFile)
                                            <p style="margin: 0; font-size: 14px; font-weight: 500; line-height: 1.5; color: #ffffff;">{{ $msg->message }}</p>
                                        @endif

                                        @if($isDownloadFile && $isImage)
                                            <div style="margin-top: 0.5rem; border-radius: 1rem; overflow: hidden; border: 2px solid rgba(245,158,11,0.4); max-width: 100%;">
                                                <a href="{{ asset('storage/' . $filepath) }}" target="_blank">
                                                    <img src="{{ asset('storage/' . $filepath) }}" style="max-width: 100%; max-height: 200px; object-fit: cover; display: block;">
                                                </a>
                                            </div>
                                        @endif

                                        @if($isDownloadFile && !$isImage)
                                            <div style="margin-top: 0.5rem; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.6rem; background: rgba(255,255,255,0.03); border-radius: 0.8rem; border: 1px solid rgba(245,158,11,0.2);">
                                                <span style="font-size: 12px; color: #ffffff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px;" title="{{ $filename }}">{{ $filename }}</span>
                                                <a href="{{ asset('storage/' . $filepath) }}" target="_blank" download style="font-size: 10px; font-weight: 900; background-color: #f59e0b; color: #000000; padding: 0.3rem 0.6rem; border-radius: 0.5rem; text-decoration: none;">Baixar</a>
                                            </div>
                                        @endif

                                        <div style="display: flex; justify-content: flex-end; align-items: center; width: 100%;">
                                            <span style="font-size: 9px; font-weight: 700; opacity: 0.6; color: #f59e0b;">
                                                {{ \Carbon\Carbon::parse($msg->created_at)->timezone('America/Sao_Paulo')->format('H:i') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.6; text-align: center;">
                        <x-heroicon-o-chat-bubble-left-right style="width: 48px; height: 48px; color: #f59e0b; margin-bottom: 1rem;" />
                        <p style="font-size: 11px; font-weight: 900; color: #f59e0b; text-transform: uppercase; letter-spacing: 2px;">Selecione um colaborador para iniciar</p>
                    </div>
                @endif
            </div>

            {{-- RODAPÉ DE DIGITAÇÃO E COMANDOS NATIVOS MÓVEIS --}}
            <div style="padding: 1rem; background-color: #0e1013; border-top: 1px solid rgba(255, 255, 255, 0.03); box-sizing: border-box;">
                
                <div wire:loading wire:target="photo, document" style="font-size: 11px; color: #f59e0b; font-weight: bold; text-transform: uppercase; margin-bottom: 0.5rem;">
                    ⚡ Enviando anexo para a Central Oravel...
                </div>

                <div style="display: flex; gap: 0.5rem; align-items: center; background-color: #1a1c21; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 30px; padding: 0.3rem 0.6rem; box-shadow: 0 20px 50px rgba(0,0,0,0.6);"
                     x-data="{ 
                        recording: false,
                        recognition: null,
                        initSpeech() {
                            try {
                                const SpeechClass = window.SpeechRecognition || window.webkitSpeechRecognition;
                                if (!SpeechClass) return;
                                this.recognition = new SpeechClass();
                                this.recognition.lang = 'pt-BR';
                                this.recognition.continuous = false;
                                
                                this.recognition.onresult = (event) => {
                                    localMessage = localMessage + ' ' + event.results[0][0].transcript;
                                };
                                this.recognition.onend = () => { this.recording = false; };
                                this.recognition.onerror = () => { this.recording = false; };
                            } catch (e) {}
                        },
                        toggleRecord() {
                            if(!this.recognition) this.initSpeech();
                            if(!this.recognition) return;
                            try {
                                if(!this.recording) {
                                    this.recording = true;
                                    this.recognition.start();
                                } else {
                                    this.recognition.stop();
                                }
                            } catch (err) { this.recording = false; }
                        }
                     }">
                    
                    {{-- ANEXAR DOCUMENTOS --}}
                    <label style="cursor: pointer; padding: 0.4rem; display: flex; align-items: center;" title="Anexar Documento">
                        <input type="file" wire:model="document" {{ !$activeChatId ? 'disabled' : '' }} style="display: none;">
                        <x-heroicon-s-paper-clip style="width: 20px; height: 20px; color: #9ca3af;" />
                    </label>

                    {{-- CAIXA DE TEXTO CONECTADA À ESTEIRA OFFLINE LOCAL --}}
                    <input type="text" 
                           x-model="localMessage"
                           @keydown.enter="triggerSend()"
                           {{ !$activeChatId ? 'disabled' : '' }}
                           placeholder="{{ $activeChatId ? 'Mensagem...' : 'Selecione um contato...' }}" 
                           style="flex: 1; background: transparent; border: none; font-size: 14px; color: #ffffff; outline: none; padding: 0 0.2rem; height: 40px; min-width: 0; box-sizing: border-box;">
                    
                    {{-- CÂMERA INTEGRADA DO DISPOSITIVO MÓVEL --}}
                    <label style="cursor: pointer; padding: 0.4rem; display: flex; align-items: center;" title="Câmera">
                        <input type="file" accept="image/*" capture="environment" wire:model.live="photo" {{ !$activeChatId ? 'disabled' : '' }} style="display: none;">
                        <x-heroicon-s-camera style="width: 22px; height: 22px; color: #f59e0b;" />
                    </label>

                    {{-- DITADO INTELIGENTE --}}
                    <button type="button"
                            {{ !$activeChatId ? 'disabled' : '' }}
                            x-on:click="toggleRecord()"
                            style="background: transparent; border: none; padding: 0.4rem; cursor: pointer;">
                        <x-heroicon-s-microphone x-bind:style="recording ? 'width: 22px; height: 22px; color: #ef4444; filter: drop-shadow(0 0 8px #ef4444);' : 'width: 22px; height: 22px; color: #f59e0b;'" />
                    </button>

                    {{-- BOTÃO DE ENVIO INTELIGENTE (INTERCEPTA OFFLINE) --}}
                    <button x-on:click="triggerSend()" 
                            {{ !$activeChatId ? 'disabled' : '' }}
                            style="background-color: #f59e0b; border: none; border-radius: 50%; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0;">
                        <x-heroicon-s-paper-airplane style="width: 16px; height: 16px; color: #000000; transform: rotate(45deg) translate(-1px, -1px);" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- MEDIA QUERIES EMBUTIDAS PARA ADAPTAÇÃO AUTOMÁTICA EM DISPOSITIVOS MÓVEIS --}}
    <style>
        .oravel-chat-grid {
            display: grid; 
            grid-template-columns: 340px 1fr; 
            background-color: #0e1013; 
            border: 2px solid #f59e0b; 
            border-radius: 2.5rem; 
            min-height: 75vh; 
            height: calc(100vh - 14rem); 
            overflow: hidden; 
            box-shadow: 0 30px 60px -15px rgba(0,0,0,0.9);
        }

        .custom-chat-scrollbar::-webkit-scrollbar { width: 3px; }
        .custom-chat-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-chat-scrollbar::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 50px; }

        @media (max-width: 1023px) {
            .oravel-chat-grid {
                grid-template-columns: 1fr !important;
                grid-template-rows: auto 1fr !important;
                height: calc(100vh - 12rem) !important;
                border-radius: 1.5rem !important;
            }
            .oravel-chat-grid > div:first-child {
                max-height: 200px !important;
                border-right: none !important;
                border-bottom: 1px solid rgba(255, 255, 255, 0.05) !important;
            }
        }
    </style>
</div>