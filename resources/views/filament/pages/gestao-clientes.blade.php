<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 space-y-2">
            <h2 class="text-sm font-semibold text-gray-500 uppercase">Clientes</h2>
            @foreach ($this->clients as $client)
                <button
                    type="button"
                    wire:click="selectClient('{{ $client->id }}')"
                    @class([
                        'w-full text-left p-3 rounded-lg border',
                        'border-primary-500 bg-primary-50 dark:bg-primary-900' => $this->selectedClientId === $client->id,
                        'border-gray-200 dark:border-gray-700' => $this->selectedClientId !== $client->id,
                    ])
                >
                    <div class="flex justify-between items-center">
                        <span class="font-medium">{{ $client->name }}</span>
                        @if ($client->unread_count > 0)
                            <span class="text-xs bg-danger-500 text-white rounded-full px-2 py-0.5">{{ $client->unread_count }}</span>
                        @endif
                    </div>
                    @if ($client->pending_count > 0)
                        <span class="text-xs text-warning-600">{{ $client->pending_count }} pendência(s)</span>
                    @endif
                </button>
            @endforeach
        </div>

        <div class="lg:col-span-2">
            @if ($this->selectedClient)
                <div class="flex justify-between items-center mb-2">
                    <h2 class="text-lg font-semibold">{{ $this->selectedClient->name }}</h2>
                    <a href="{{ route('client-management.print', ['client' => $this->selectedClient->id]) }}" target="_blank">
                        <x-filament::button color="gray" icon="heroicon-o-printer">Imprimir</x-filament::button>
                    </a>
                </div>

                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Solicitações Pendentes</h3>
                    @php $pending = $this->pendingRequests; @endphp
                    <ul class="text-sm space-y-1">
                        @foreach ($pending['solicitacoes'] as $s)
                            <li>📄 Solicitação de Equipamento — {{ $s->status_comercial }}</li>
                        @endforeach
                        @foreach ($pending['ordens'] as $o)
                            <li>🛠️ OS {{ $o->os_number }} — {{ $o->status }}</li>
                        @endforeach
                        @foreach ($pending['retiradas'] as $r)
                            <li>🚚 Retirada — {{ $r->status }}</li>
                        @endforeach
                        @if ($pending['solicitacoes']->isEmpty() && $pending['ordens']->isEmpty() && $pending['retiradas']->isEmpty())
                            <li class="text-gray-400">Nenhuma pendência.</li>
                        @endif
                    </ul>
                </div>

                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Mensagens</h3>
                <div class="space-y-2 mb-4 max-h-96 overflow-y-auto">
                    @forelse ($this->messages as $message)
                        <div @class([
                            'p-2 rounded max-w-md text-sm',
                            'ml-auto bg-primary-100 dark:bg-primary-900' => ! $message->isFromClient(),
                            'bg-gray-100 dark:bg-gray-800' => $message->isFromClient(),
                        ])>
                            <div class="text-xs text-gray-500">{{ $message->senderName() }} — {{ $message->created_at->format('d/m/Y H:i') }}</div>
                            @if ($message->body)
                                <div>{{ $message->body }}</div>
                            @endif
                            @foreach ($message->getMedia('anexos') as $media)
                                <a href="{{ $media->getUrl() }}" target="_blank" class="text-xs underline block">📎 {{ $media->file_name }}</a>
                            @endforeach
                        </div>
                    @empty
                        <p class="text-gray-400 text-sm">Sem mensagens ainda.</p>
                    @endforelse
                </div>

                <form wire:submit="reply">
                    {{ $this->replyForm }}
                    <x-filament::button type="submit" class="mt-2">Responder</x-filament::button>
                </form>
            @else
                <p class="text-gray-400">Selecione um cliente para ver mensagens e pendências.</p>
            @endif
        </div>
    </div>

    <div class="mt-10 border-t pt-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase mb-2">Enviar Comunicado</h2>
        <form wire:submit="sendCommunication">
            {{ $this->communicationForm }}
            <x-filament::button type="submit" class="mt-4">Enviar Comunicado</x-filament::button>
        </form>
    </div>
</x-filament-panels::page>
