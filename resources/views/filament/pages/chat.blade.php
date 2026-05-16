<div class="w-full" style="margin-top: 1rem; box-sizing: border-box; font-family: system-ui, -apple-system, sans-serif;">
    
    {{-- LAYOUT PREMIUM COM POLLING AMARRADO AO REFRESH INTERNO --}}
    <div wire:poll.4s="autoRefresh" style="display: grid; grid-template-columns: 340px 1fr; background-color: #0e1013; border: 2px solid #f59e0b; border-radius: 2.5rem; min-height: 75vh; height: calc(100vh - 14rem); overflow: hidden; box-shadow: 0 30px 60px -15px rgba(0,0,0,0.9);">
        
        <div style="background-color: #1a1c21; border-right: 1px solid rgba(255, 255, 255, 0.05); display: flex; flex-direction: column; height: 100%; overflow: hidden;">
            
            <div style="padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.05); background-color: #111215; display: flex; flex-direction: column; gap: 1rem;">
                <h3 style="font-size: 15px; font-weight: 900; color: #f59e0b; margin: 0; text-transform: uppercase; letter-spacing: 1px;">Central Corporativa</h3>
                
                {{-- CAMPO DE BUSCA CORPORATIVA DE TERMOS BLINDADO --}}
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

        <div style="background-color: #0e1013; display: flex; flex-direction: column; height: 100%; overflow: hidden;">
            
            <div style="padding: 1rem 2rem; background-color: #1a1c21; border-bottom: 1px solid rgba(255, 255, 255, 0.05); display: flex; flex-direction: column; min-height: auto; box-sizing: border-box; gap: 0.8rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    @if($activeChatId)
                        @php 
                            // ISOLAMENTO ABSOLUTO: Puxa o usuário direto da base global para nunca sumir no cabeçalho
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
                            IMPRIMIR DOSSIÊ
                        </a>
                    @else
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 40px; height: 40px; border-radius: 50%; background-color: #27272a; display: flex; align-items: center; justify-content: center; color: #f59e0b;">
                                <x-heroicon-s-chat-bubble-left-right style="width: 20px; height: 20px;" />
                            </div>
                            <div>
                                <h4 style="font-size: 13px; font-weight: 900; color: #6b7280; margin: 0; text-transform: uppercase;">Aguardando Seleção</h4>
                                <p style="font-size: 10px; color: #4b5563; font-weight: 700; margin: 0; text-transform: uppercase;">Selecione um funcionário ao lado</p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- INJEÇÃO MÍNIMA 1: SELETOR DE ABAS OPERACIONAIS --}}
                @if($activeChatId)
                    <div style="display: flex; gap: 0.5rem; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 0.6rem;">
                        <button wire:click="setContext('geral', null)" style="padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 11px; font-weight: bold; cursor: pointer; border: none; transition: all 0.2s; {{ $contextType == 'geral' ? 'background:#f59e0b; color:#000000;' : 'background:#2a2d34; color:#ffffff;' }}">
                            💬 Conversa Livre
                        </button>
                        <button wire:click="setContext('os', null)" style="padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 11px; font-weight: bold; cursor: pointer; border: none; transition: all 0.2s; {{ $contextType == 'os' ? 'background:#f59e0b; color:#000000;' : 'background:#2a2d34; color:#ffffff;' }}">
                            🛠️ Ordens de Serviço
                        </button>
                        <button wire:click="setContext('asset', null)" style="padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 11px; font-weight: bold; cursor: pointer; border: none; transition: all 0.2s; {{ $contextType == 'asset' ? 'background:#f59e0b; color:#000000;' : 'background:#2a2d34; color:#ffffff;' }}">
                            🚜 Frota / Equipamentos
                        </button>
                        <button wire:click="setContext('material', null)" style="padding: 0.4rem 0.8rem; border-radius: 8px; font-size: 11px; font-weight: bold; cursor: pointer; border: none; transition: all 0.2s; {{ $contextType == 'material' ? 'background:#f59e0b; color:#000000;' : 'background:#2a2d34; color:#ffffff;' }}">
                            📦 Pedidos de Material
                        </button>
                    </div>

                    {{-- INJEÇÃO MÍNIMA 2: ESTEIRA HORIZONTAL DE DOCUMENTOS OPERACIONAIS REAIS DO BANCO --}}
                    <div style="background-color: #111215; padding: 0.5rem 1rem; border-radius: 10px; display: flex; gap: 0.5rem; overflow-x: auto; align-items: center; border: 1px solid rgba(255,255,255,0.02);">
                        @if($contextType == 'os')
                            <span style="font-size: 10px; color:#f59e0b; font-weight: 800; text-transform: uppercase; white-space: nowrap;">O.S. do Técnico:</span>
                            @forelse($this->activeOrders as $os)
                                <button wire:click="$set('contextId', '{{ $os->id }}')" style="padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 10px; font-weight: 700; border: 1px solid #f59e0b; cursor: pointer; white-space: nowrap; {{ $contextId == $os->id ? 'background:#f59e0b; color:black;' : 'background:transparent; color:white;' }}">
                                    #{{ $os->id }} - {{ Str::limit($os->title ?? $os->description, 18) }}
                                </button>
                            @empty
                                <span style="font-size: 11px; color:#6b7280; font-style: italic;">Nenhuma O.S. aberta atribuída.</span>
                            @endforelse
                        @elseif($contextType == 'asset')
                            <span style="font-size: 10px; color:#f59e0b; font-weight: 800; text-transform: uppercase; white-space: nowrap;">Equipamentos:</span>
                            @foreach($this->activeAssets as $asset)
                                <button wire:click="$set('contextId', '{{ $asset->id }}')" style="padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 10px; font-weight: 700; border: 1px solid #f59e0b; cursor: pointer; white-space: nowrap; {{ $contextId == $asset->id ? 'background:#f59e0b; color:black;' : 'background:transparent; color:white;' }}">
                                    {{ $asset->name }}
                                </button>
                            @endforeach
                        @elseif($contextType == 'material')
                            <span style="font-size: 10px; color:#f59e0b; font-weight: 800; text-transform: uppercase; white-space: nowrap;">Pedidos de Compras:</span>
                            @forelse($this->activeMaterialRequests as $req)
                                <button wire:click="$set('contextId', '{{ $req->id }}')" style="padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 10px; font-weight: 700; border: 1px solid #f59e0b; cursor: pointer; white-space: nowrap; {{ $contextId == $req->id ? 'background:#f59e0b; color:black;' : 'background:transparent; color:white;' }}">
                                    Req #{{ substr($req->id, 0, 6) }}... ({{ $req->provider_name ?? 'Almoxarifado' }})
                                </button>
                            @empty
                                <span style="font-size: 11px; color:#6b7280; font-style: italic;">Nenhum pedido ativo no banco.</span>
                            @endforelse
                        @else
                            <span style="font-size: 11px; color:#6b7280; font-style: italic;">Canal geral corporativo aberto (Sem amarrações).</span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="custom-chat-scrollbar" 
                 style="flex: 1; overflow-y: auto; padding: 2rem; display: flex; flex-direction: column; gap: 1.5rem; background-color: #0b0c0e;"
                 x-data="{}" x-init="$el.scrollTop = $el.scrollHeight" x-on:scroll-to-bottom.window="$nextTick(() => { $el.scrollTop = $el.scrollHeight; })">
                
                @if($activeChatId)
                    <div style="display: flex; justify-content: center; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <div style="height: 1px; width: 40px; background-color: rgba(255,255,255,0.06);"></div>
                        <span style="font-size: 10px; font-weight: 900; color: #f59e0b; text-transform: uppercase; letter-spacing: 4px;">HISTÓRICO DE MENSAGENS</span>
                        <div style="height: 1px; width: 40px; background-color: rgba(255,255,255,0.06);"></div>
                    </div>

                    @foreach($this->messages as $msg)
                        @php $isMine = $msg->user_id == auth()->id(); @endphp
                        <div style="display: flex; flex-direction: column; width: 100%; align-items: {{ $isMine ? 'flex-end' : 'flex-start' }};">
                            <div style="max-width: 70%;">
                                @if(!$isMine)
                                    <div style="font-size: 10px; font-weight: 900; color: #f59e0b; text-transform: uppercase; margin-bottom: 4px; margin-left: 12px; font-style: italic;">
                                        {{ $msg->user->name }}
                                    </div>
                                @endif

                                {{-- Destaca com borda dourada se a mensagem contiver o termo pesquisado --}}
                                @php
                                    $containsTerm = !empty($searchQuery) && str_contains(strtolower($msg->message), strtolower($searchQuery));
                                    $borderStyle = $containsTerm ? 'border: 2px solid #f59e0b; box-shadow: 0 0 15px rgba(245, 158, 11, 0.4);' : 'border: 1px solid;';
                                @endphp

                                <div style="padding: 0.8rem 1.5rem; {{ $borderStyle }} box-shadow: 0 8px 25px rgba(0,0,0,0.4);
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

                                        {{-- 1. MENSAGEM DE TEXTO TRADICIONAL --}}
                                        @if(!$isDownloadFile)
                                            <p style="margin: 0; font-size: 14px; font-weight: 500; line-height: 1.5; color: #ffffff;">{{ $msg->message }}</p>
                                        @endif

                                        {{-- 2. MINIATURA DE IMAGEM --}}
                                        @if($isDownloadFile && $isImage)
                                            <div style="margin-top: 0.5rem; border-radius: 1rem; overflow: hidden; border: 2px solid rgba(245,158,11,0.4); max-width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
                                                <a href="{{ asset('storage/' . $filepath) }}" target="_blank">
                                                    <img src="{{ asset('storage/' . $filepath) }}" style="max-width: 100%; max-height: 280px; object-fit: cover; display: block;" onerror="this.src='https://placehold.co/300x200/1a1c21/f59e0b?text=Erro+no+Link+do+Storage'">
                                                </a>
                                            </div>
                                        @endif

                                        {{-- 3. DOCUMENTO / PDF --}}
                                        @if($isDownloadFile && !$isImage)
                                            <div style="margin-top: 0.5rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 0.8rem; background: rgba(255,255,255,0.03); border-radius: 0.8rem; border: 1px solid rgba(245,158,11,0.2);">
                                                <div style="display: flex; align-items: center; gap: 0.5rem; min-width: 0;">
                                                    <x-heroicon-s-document-text style="width: 24px; height: 24px; color: #f59e0b; flex-shrink: 0;" />
                                                    <span style="font-size: 13px; color: #ffffff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $filename }}">
                                                        {{ $filename }}
                                                    </span>
                                                </div>
                                                <a href="{{ asset('storage/' . $filepath) }}" target="_blank" download style="font-size: 11px; font-weight: 900; background-color: #f59e0b; color: #000000; padding: 0.4rem 0.8rem; border-radius: 0.5rem; text-decoration: none; text-transform: uppercase; transition: background 0.2s;">
                                                    Baixar
                                                </a>
                                            </div>
                                        @endif

                                        {{-- AJUSTE DE HORA REATIVO TRAVADO EM SÃO PAULO --}}
                                        <div style="display: flex; justify-content: flex-end; align-items: center; width: 100%;">
                                            <span style="font-size: 9px; font-weight: 700; opacity: 0.6; white-space: nowrap; color: #f59e0b;">
                                                {{ \Carbon\Carbon::parse($msg->created_at)->timezone('America/Sao_Paulo')->format('H:i') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.6;">
                        <x-heroicon-o-chat-bubble-left-right style="width: 64px; height: 64px; color: #f59e0b; margin-bottom: 1rem;" />
                        <p style="font-size: 11px; font-weight: 900; color: #f59e0b; text-transform: uppercase; letter-spacing: 3px; text-align: center; max-w: 380px;">
                            Selecione um colaborador na lista à esquerda para carregar a central de interação
                        </p>
                    </div>
                @endif
            </div>

            <div style="padding: 1.5rem; background-color: #0e1013; border-top: 1px solid rgba(255, 255, 255, 0.03); box-sizing: border-box;">
                
                <div wire:loading wire:target="photo, document" style="font-size: 11px; color: #f59e0b; font-weight: bold; text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 1px;">
                    ⚡ Enviando anexo para a Central Oravel...
                </div>

                <div style="display: flex; gap: 0.8rem; align-items: center; background-color: #1a1c21; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 50px; padding: 0.4rem 0.8rem; box-shadow: 0 20px 50px rgba(0,0,0,0.6);"
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
                                this.recognition.interimResults = false;
                                
                                this.recognition.onresult = (event) => {
                                    const text = event.results[0][0].transcript;
                                    @this.set('newMessage', @this.get('newMessage') + ' ' + text);
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
                                    this.recording = false;
                                }
                            } catch (err) { this.recording = false; }
                        }
                     }">
                    
                    {{-- ANEXAR DOCUMENTOS --}}
                    <label style="cursor: {{ $activeChatId ? 'pointer' : 'not-allowed' }}; opacity: {{ $activeChatId ? '1' : '0.3' }}; display: flex; align-items: center; justify-content: center; padding: 0.5rem;" title="Anexar Documento">
                        <input type="file" wire:model="document" {{ !$activeChatId ? 'disabled' : '' }} style="display: none;">
                        <x-heroicon-s-paper-clip style="width: 22px; height: 22px; color: #9ca3af;" />
                    </label>

                    <input type="text" 
                           wire:model="newMessage" 
                           @keydown.enter="$wire.sendMessage()"
                           {{ !$activeChatId ? 'disabled' : '' }}
                           placeholder="{{ $activeChatId ? 'Escreva uma mensagem para o colaborador...' : 'Selecione um contato na lista lateral...' }}" 
                           style="flex: 1; background: transparent; border: none; font-size: 14px; color: #ffffff; outline: none; padding: 0 0.5rem; height: 44px; width: 100%; box-sizing: border-box; opacity: {{ !$activeChatId ? '0.2' : '1' }};">
                    
                    {{-- ABRIR CÂMERA NATIVA DO DISPOSITIVO / SELECIONAR FOTOS --}}
                    <label style="cursor: {{ $activeChatId ? 'pointer' : 'not-allowed' }}; opacity: {{ $activeChatId ? '1' : '0.3' }}; display: flex; align-items: center; justify-content: center; padding: 0.5rem;" title="Tirar Foto / Abrir Câmera">
                        <input type="file" accept="image/*" capture="environment" wire:model.live="photo" {{ !$activeChatId ? 'disabled' : '' }} style="display: none;">
                        <x-heroicon-s-camera style="width: 24px; height: 24px; color: #f59e0b;" />
                    </label>

                    {{-- DITADO INTELIGENTE (SPEECH-TO-TEXT) --}}
                    <button type="button"
                            {{ !$activeChatId ? 'disabled' : '' }}
                            x-on:click="toggleRecord()"
                            style="background: transparent; border: none; padding: 0.5rem; cursor: pointer; opacity: {{ $activeChatId ? '1' : '0.3' }};" 
                            title="Gravar e transformar em texto">
                        <x-heroicon-s-microphone x-bind:style="recording ? 'width: 24px; height: 24px; color: #ef4444; filter: drop-shadow(0 0 8px #ef4444);' : 'width: 24px; height: 24px; color: #f59e0b;'" />
                    </button>

                    <button wire:click="sendMessage" 
                            {{ !$activeChatId ? 'disabled' : '' }}
                            style="background-color: #f59e0b; border: none; border-radius: 50px; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: all 0.2s; opacity: {{ !$activeChatId ? '0.2' : '1' }};">
                        <x-heroicon-s-paper-airplane style="width: 18px; height: 18px; color: #000000; transform: rotate(45deg) translate(-1px, -1px);" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .custom-chat-scrollbar::-webkit-scrollbar { width: 3px; }
        .custom-chat-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-chat-scrollbar::-webkit-scrollbar-thumb { background: #f59e0b; border-radius: 50px; }
    </style>
</div>