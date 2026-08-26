<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        {{-- Coluna: lista de clientes --}}
        <div class="lg:col-span-4 xl:col-span-3">
            <x-filament::section>
                <x-slot name="heading">
                    Clientes
                </x-slot>

                <x-slot name="headerEnd">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ $this->clients->count() }}
                    </span>
                </x-slot>

                <div class="mb-4 relative">
                    <x-filament::icon
                        icon="heroicon-m-magnifying-glass"
                        class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Buscar cliente..."
                        class="block w-full rounded-lg border border-gray-300 bg-white py-1.5 pl-9 pr-3 text-sm text-gray-950 shadow-sm outline-none placeholder:text-gray-400 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white dark:placeholder:text-gray-500"
                    />
                </div>

                <div class="space-y-1.5 max-h-[36rem] overflow-y-auto -mx-2 px-2">
                    @forelse ($this->clients as $client)
                        <button
                            type="button"
                            wire:click="selectClient('{{ $client->id }}')"
                            @class([
                                'w-full flex items-center gap-3 rounded-lg border p-3 text-left transition-colors',
                                'border-primary-600 bg-primary-50 ring-1 ring-primary-600 dark:bg-primary-500/10' => $this->selectedClientId === $client->id,
                                'border-gray-200 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5' => $this->selectedClientId !== $client->id,
                            ])
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                {{ Str::of($client->name)->substr(0, 2)->upper() }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                                        {{ $client->name }}
                                    </span>
                                    @if ($client->unread_count > 0)
                                        <x-filament::badge color="danger" size="sm">
                                            {{ $client->unread_count }}
                                        </x-filament::badge>
                                    @endif
                                </span>

                                @if ($client->pending_count > 0)
                                    <span class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-warning-600 dark:text-warning-400">
                                        <x-filament::icon icon="heroicon-m-exclamation-circle" class="h-3.5 w-3.5" />
                                        {{ $client->pending_count }} pendência{{ $client->pending_count > 1 ? 's' : '' }}
                                    </span>
                                @else
                                    <span class="mt-1 inline-block text-xs text-gray-400 dark:text-gray-500">
                                        Em dia
                                    </span>
                                @endif
                            </span>
                        </button>
                    @empty
                        <div class="py-8 text-center text-sm text-gray-400">
                            Nenhum cliente encontrado.
                        </div>
                    @endforelse
                </div>
            </x-filament::section>
        </div>

        {{-- Coluna: detalhe do cliente selecionado --}}
        <div class="lg:col-span-8 xl:col-span-9 space-y-6">
            @if ($this->selectedClient)
                <x-filament::section>
                    <x-slot name="heading">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-semibold text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                                {{ Str::of($this->selectedClient->name)->substr(0, 2)->upper() }}
                            </span>
                            {{ $this->selectedClient->name }}
                        </div>
                    </x-slot>

                    <x-slot name="headerEnd">
                        <x-filament::button
                            tag="a"
                            href="{{ route('client-management.print', ['client' => $this->selectedClient->id]) }}"
                            target="_blank"
                            color="gray"
                            icon="heroicon-o-printer"
                            size="sm"
                        >
                            Imprimir
                        </x-filament::button>
                    </x-slot>

                    {{-- Pendências --}}
                    @php $pending = $this->pendingRequests; @endphp
                    <div class="mb-6">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Solicitações Pendentes
                        </h3>

                        @if ($pending['solicitacoes']->isEmpty() && $pending['ordens']->isEmpty() && $pending['retiradas']->isEmpty())
                            <div class="flex items-center gap-2 rounded-lg bg-success-50 px-3 py-2 text-sm text-success-700 dark:bg-success-500/10 dark:text-success-400">
                                <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
                                Nenhuma pendência em aberto.
                            </div>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @foreach ($pending['solicitacoes'] as $s)
                                    <x-filament::badge color="warning" icon="heroicon-m-document-text">
                                        Solicitação de Equipamento — {{ $s->status_comercial }}
                                    </x-filament::badge>
                                @endforeach
                                @foreach ($pending['ordens'] as $o)
                                    <x-filament::badge color="warning" icon="heroicon-m-wrench-screwdriver">
                                        OS {{ $o->os_number }} — {{ $o->status }}
                                    </x-filament::badge>
                                @endforeach
                                @foreach ($pending['retiradas'] as $r)
                                    <x-filament::badge color="warning" icon="heroicon-m-truck">
                                        Retirada — {{ $r->status }}
                                    </x-filament::badge>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Mensagens --}}
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Mensagens
                    </h3>

                    <div class="mb-4 max-h-96 space-y-2 overflow-y-auto rounded-lg border border-gray-100 bg-gray-50/50 p-3 dark:border-white/5 dark:bg-white/[0.02]">
                        @forelse ($this->messages as $message)
                            <div @class([
                                'max-w-lg rounded-lg px-3 py-2 text-sm',
                                'ml-auto bg-primary-600 text-white' => ! $message->isFromClient(),
                                'bg-white shadow-sm dark:bg-gray-800' => $message->isFromClient(),
                            ])>
                                <div @class([
                                    'mb-0.5 text-xs',
                                    'text-primary-100' => ! $message->isFromClient(),
                                    'text-gray-400' => $message->isFromClient(),
                                ])>
                                    {{ $message->senderName() }} · {{ $message->created_at->format('d/m/Y H:i') }}
                                </div>

                                @if ($message->body)
                                    <div>{{ $message->body }}</div>
                                @endif

                                @foreach ($message->getMedia('anexos') as $media)
                                    <a
                                        href="{{ $media->getUrl() }}"
                                        target="_blank"
                                        @class([
                                            'mt-1 flex items-center gap-1 text-xs underline underline-offset-2',
                                            'text-primary-100' => ! $message->isFromClient(),
                                            'text-primary-600 dark:text-primary-400' => $message->isFromClient(),
                                        ])
                                    >
                                        <x-filament::icon icon="heroicon-m-paper-clip" class="h-3.5 w-3.5" />
                                        {{ $media->file_name }}
                                    </a>
                                @endforeach
                            </div>
                        @empty
                            <p class="py-6 text-center text-sm text-gray-400">Nenhuma mensagem ainda.</p>
                        @endforelse
                    </div>

                    <form wire:submit="reply" class="flex items-start gap-2">
                        <div class="flex-1">
                            {{ $this->replyForm }}
                        </div>
                        <x-filament::button type="submit" icon="heroicon-m-paper-airplane">
                            Enviar
                        </x-filament::button>
                    </form>
                </x-filament::section>
            @else
                <x-filament::section>
                    <div class="flex flex-col items-center justify-center gap-2 py-16 text-center">
                        <x-filament::icon icon="heroicon-o-user-group" class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                        <p class="text-sm text-gray-400">Selecione um cliente para ver mensagens e pendências.</p>
                    </div>
                </x-filament::section>
            @endif

            {{-- Comunicado em massa --}}
            <x-filament::section collapsible collapsed>
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-megaphone" class="h-5 w-5 text-gray-400" />
                        Enviar Comunicado
                    </div>
                </x-slot>

                <x-slot name="description">
                    Envie um aviso por e-mail para um cliente específico, vários selecionados, ou todos com acesso ao portal.
                </x-slot>

                <form wire:submit="sendCommunication" class="space-y-4">
                    {{ $this->communicationForm }}

                    <x-filament::button type="submit" icon="heroicon-m-paper-airplane">
                        Enviar Comunicado
                    </x-filament::button>
                </form>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
