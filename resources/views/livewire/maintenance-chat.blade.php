<div class="flex flex-col h-full w-full bg-white dark:bg-gray-950 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800 overflow-hidden">
    
    <div class="px-5 py-4 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 flex justify-between items-center shrink-0">
        <div class="flex items-center gap-3">
            <div class="bg-primary-500/10 dark:bg-primary-500/20 text-primary-600 dark:text-primary-400 p-2 rounded-lg">
                <x-heroicon-s-wrench-screwdriver class="w-5 h-5" />
            </div>
            <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white uppercase tracking-tight">Dossiê Técnico & Evidências</h3>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('maintenance.chat.print', ['record' => $maintenance_order_id]) }}" 
               target="_blank" 
               class="flex items-center gap-2 px-3 py-1.5 bg-white dark:bg-gray-800 hover:bg-primary-50 dark:hover:bg-primary-900/20 text-primary-600 dark:text-primary-400 rounded-lg text-[11px] font-black border border-primary-200 dark:border-primary-800 transition shadow-sm whitespace-nowrap">
                <x-heroicon-m-printer class="w-4 h-4" />
                IMPRIMIR HISTÓRICO
            </a>

            <span class="inline-flex items-center gap-1.5 py-1 px-3 rounded-full text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 border border-green-200 dark:border-green-800">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span> Online
            </span>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-5 space-y-5 bg-gray-50/30 dark:bg-gray-950 relative" id="chat-container">
        @forelse($messages as $msg)
            @php $isMine = $msg->user_id === auth()->id(); @endphp
            <div class="flex flex-col {{ $isMine ? 'items-end' : 'items-start' }} w-full">
                <div class="max-w-[85%] md:max-w-[75%]">
                    @if(!$isMine)
                        <p class="text-[10px] font-bold text-gray-500 dark:text-gray-400 mb-1 ml-1">{{ $msg->user->name ?? 'Usuário' }}</p>
                    @endif

                    <div class="p-3.5 shadow-sm text-sm font-medium {{ $isMine ? 'bg-primary-600 text-white rounded-2xl rounded-tr-sm' : 'bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 rounded-2xl rounded-tl-sm border border-gray-100 dark:border-gray-700' }}">
                        @if(!empty($msg->message))
                            <p class="whitespace-pre-wrap">{{ $msg->message }}</p>
                        @endif

                        @if($msg->hasMedia('chat_attachments'))
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach($msg->getMedia('chat_attachments') as $media)
                                    @if(str_starts_with($media->mime_type, 'image/'))
                                        <a href="{{ $media->getUrl() }}" target="_blank" class="block border border-black/10 dark:border-white/10 rounded-xl overflow-hidden shadow-sm hover:opacity-90 transition">
                                            <img src="{{ $media->getUrl() }}" alt="Evidência" class="max-w-[180px] max-h-[180px] object-cover rounded-lg">
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                    
                    {{-- DATA E HORA NAS MENSAGENS --}}
                    <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 mt-1 {{ $isMine ? 'text-right mr-1' : 'ml-1' }}">
                        <x-heroicon-m-clock class="w-3 h-3 inline-block -mt-0.5" />
                        {{ $msg->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>
            </div>
        @empty
            <div class="absolute inset-0 flex flex-col items-center justify-center text-gray-400 dark:text-gray-600">
                <div class="bg-gray-100 dark:bg-gray-800/50 p-4 rounded-full mb-3 border border-gray-200 dark:border-gray-700">
                    <x-heroicon-o-camera class="w-8 h-8 opacity-50" />
                </div>
                <p class="font-bold text-sm uppercase tracking-widest">Nenhuma evidência registrada</p>
            </div>
        @endforelse
    </div>

    <div class="p-4 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 shrink-0">
        @if(!empty($photos))
            <div class="mb-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 flex flex-wrap gap-3">
                <span class="text-xs font-bold text-primary-600 dark:text-primary-400 flex items-center w-full mb-1">
                    <x-heroicon-s-check-circle class="w-4 h-4 mr-1" /> {{ count($photos) }} anexo(s) pronto(s) para enviar
                </span>
                @foreach($photos as $photo)
                    <div class="h-14 w-14 rounded-lg border border-gray-300 dark:border-gray-600 shadow-sm bg-cover bg-center" style="background-image: url('{{ $photo->temporaryUrl() }}')"></div>
                @endforeach
            </div>
        @endif

        <div class="flex gap-2 items-center">
            <label title="Tirar Foto" class="flex items-center justify-center w-11 h-11 cursor-pointer bg-primary-50 hover:bg-primary-100 dark:bg-primary-900/30 dark:hover:bg-primary-900/50 text-primary-600 dark:text-primary-400 rounded-xl shadow-sm transition border border-primary-200 dark:border-primary-800 shrink-0">
                <input type="file" wire:model.live="photos" multiple class="hidden" accept="image/*" capture="environment">
                <div wire:loading wire:target="photos" class="animate-spin h-5 w-5 border-2 border-primary-500 border-t-transparent rounded-full"></div>
                <x-heroicon-s-camera class="w-5 h-5" wire:loading.remove wire:target="photos" />
            </label>

            <label title="Anexar Arquivo" class="flex items-center justify-center w-11 h-11 cursor-pointer bg-gray-100 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-xl shadow-sm transition border border-gray-200 dark:border-gray-700 shrink-0">
                <input type="file" wire:model.live="photos" multiple class="hidden" accept="image/*,audio/*,application/pdf">
                <x-heroicon-s-paper-clip class="w-5 h-5" />
            </label>

            <input 
                type="text" 
                wire:model="message" 
                wire:keydown.enter="sendMessage"
                class="flex-1 bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-white border border-gray-300 dark:border-gray-700 rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 font-medium shadow-sm outline-none" 
                placeholder="Digite ou tire uma foto..."
            >

            <button wire:click="sendMessage" wire:loading.attr="disabled" class="flex items-center justify-center px-4 h-11 bg-primary-600 hover:bg-primary-700 text-white font-bold rounded-xl shadow-sm transition disabled:opacity-50 gap-2 shrink-0">
                <x-heroicon-s-paper-airplane class="w-4 h-4" />
                <span class="hidden sm:inline">Enviar</span>
            </button>
        </div>
    </div>
</div>