<x-filament-panels::page>
    @php
        $messages = $this->getMessagesQuery()->get();
        $active = $this->activeMessageId ? \App\Models\EmailMessage::with(['fromUser', 'recipients', 'related', 'media'])->find($this->activeMessageId) : null;
        $folders = [
            'recebidos' => ['label' => 'Recebidos', 'icon' => 'M2.25 15.75l3.795-3.795c.399-.399.94-.622 1.503-.622h8.904c.563 0 1.104.223 1.503.622l3.795 3.795M2.25 15.75v3.5c0 1.106.894 2 2 2h15.5c1.106 0 2-.894 2-2v-3.5M2.25 15.75L6.66 6.522A2.25 2.25 0 018.61 5.25h6.78a2.25 2.25 0 011.95 1.272l4.41 9.228'],
            'enviados' => ['label' => 'Enviados', 'icon' => 'M3.4 20.6l17.45-8.5a1 1 0 000-1.8L3.4 1.8a1 1 0 00-1.44 1.1L4.3 11 1.96 18.9a1 1 0 001.44 1.1z'],
            'rascunhos' => ['label' => 'Rascunhos', 'icon' => 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10'],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-[200px_300px_1fr]">
        {{-- Trilho de pastas --}}
        <div class="space-y-3">
            <x-filament::button
                wire:click="newDraft"
                icon="heroicon-o-pencil-square"
                class="w-full justify-center"
            >
                Novo E-mail
            </x-filament::button>

            <nav class="flex flex-col gap-0.5 rounded-xl border border-gray-200 bg-white p-2 dark:border-white/10 dark:bg-gray-900">
                @foreach($folders as $key => $folder)
                    <button
                        type="button"
                        wire:click="setFolder('{{ $key }}')"
                        class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors {{ $this->folder === $key ? 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400' : 'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5' }}"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4 shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $folder['icon'] }}" />
                        </svg>
                        {{ $folder['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        {{-- Lista de mensagens da pasta --}}
        <div class="divide-y divide-gray-100 self-start rounded-xl border border-gray-200 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-gray-900">
            @forelse($messages as $message)
                @php
                    $isUnread = $this->folder === 'recebidos' && optional($message->recipients->firstWhere('id', auth()->id()))->pivot?->read_at === null;
                    $counterpart = $this->folder === 'enviados'
                        ? ($message->recipients->pluck('name')->implode(', ') ?: collect($message->to_external ?? [])->implode(', '))
                        : $message->fromUser?->name;
                @endphp
                <button
                    type="button"
                    wire:click="selectMessage('{{ $message->id }}')"
                    wire:key="email-row-{{ $message->id }}"
                    class="block w-full px-3 py-2.5 text-left text-sm transition-colors hover:bg-gray-50 dark:hover:bg-white/5 {{ $this->activeMessageId === $message->id ? 'bg-gray-50 dark:bg-white/5' : '' }}"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate {{ $isUnread ? 'font-bold text-gray-950 dark:text-white' : 'font-medium text-gray-700 dark:text-gray-300' }}">
                            {{ $counterpart ?: 'Sem destinatário' }}
                        </span>
                        <span class="shrink-0 text-[10px] text-gray-400">{{ $message->created_at->format('d/m H:i') }}</span>
                    </div>
                    <p class="truncate text-xs {{ $isUnread ? 'font-semibold text-gray-700 dark:text-gray-200' : 'text-gray-500' }}">
                        {{ $message->subject ?: '(sem assunto)' }}
                    </p>
                </button>
            @empty
                <div class="p-6 text-center text-xs uppercase tracking-wide text-gray-400">Nada por aqui</div>
            @endforelse
        </div>

        {{-- Detalhe / composição --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
            @if($this->isComposing)
                <form wire:submit.prevent="sendMessage" class="space-y-4">
                    {{ $this->form }}

                    <div class="flex items-center gap-2">
                        <x-filament::button type="submit" icon="heroicon-o-paper-airplane">
                            Enviar
                        </x-filament::button>
                        <x-filament::button type="button" color="gray" wire:click="saveDraft">
                            Salvar Rascunho
                        </x-filament::button>
                        @if($active)
                            <x-filament::button
                                type="button"
                                color="danger"
                                outlined
                                wire:click="deleteDraft('{{ $active->id }}')"
                                wire:confirm="Apagar este rascunho?"
                            >
                                Apagar
                            </x-filament::button>
                        @endif
                    </div>
                </form>
            @elseif($active)
                <div class="space-y-4">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-4 dark:border-white/5">
                        <div>
                            <h2 class="text-base font-bold text-gray-950 dark:text-white">{{ $active->subject ?: '(sem assunto)' }}</h2>
                            <p class="text-xs text-gray-500">
                                De: <strong>{{ $active->fromUser?->name }}</strong>
                                @if($active->recipients->isNotEmpty())
                                    · Para: {{ $active->recipients->pluck('name')->implode(', ') }}
                                @endif
                                @if(!empty($active->to_external))
                                    · Para (externo): {{ collect($active->to_external)->implode(', ') }}
                                @endif
                            </p>
                            <p class="text-[11px] text-gray-400">{{ $active->created_at->format('d/m/Y H:i') }}</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            @if($active->status === \App\Models\EmailMessage::STATUS_FALHOU)
                                <span class="rounded-full bg-danger-50 px-2 py-1 text-[10px] font-bold uppercase text-danger-600 dark:bg-danger-500/10 dark:text-danger-400">Falhou</span>
                            @endif
                            <x-filament::button size="sm" color="gray" wire:click="replyTo('{{ $active->id }}')" icon="heroicon-o-arrow-uturn-left">
                                Responder
                            </x-filament::button>
                        </div>
                    </div>

                    <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $active->body }}</p>

                    @if($active->getMedia('anexos')->isNotEmpty())
                        <div class="border-t border-gray-100 pt-4 dark:border-white/5">
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-wide text-gray-400">Anexos</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($active->getMedia('anexos') as $media)
                                    <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-primary-600 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5">
                                        📎 {{ $media->file_name }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($active->related)
                        <div class="border-t border-gray-100 pt-4 dark:border-white/5">
                            @if($active->related_type === \App\Models\Client::class)
                                <a href="{{ \App\Filament\Resources\ClientResource::getUrl('edit', ['record' => $active->related_id]) }}" class="text-xs font-bold uppercase text-primary-600 hover:text-primary-500">Ver Cliente</a>
                            @elseif($active->related_type === \App\Models\CrmLead::class)
                                <a href="{{ \App\Filament\Resources\CrmLeadResource::getUrl('edit', ['record' => $active->related_id]) }}" class="text-xs font-bold uppercase text-primary-600 hover:text-primary-500">Ver Lead</a>
                            @endif
                        </div>
                    @endif
                </div>
            @else
                <div class="flex h-full min-h-[200px] items-center justify-center text-sm text-gray-400">
                    Selecione um e-mail ou clique em "Novo E-mail".
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
