@php
    $events = $solicitacao->timelineEvents();
    $recebida = $solicitacao->maintenanceOrders()->exists();
    $statusLabels = \App\Models\SolicitacaoLocacao::statusComercialLabels();
@endphp

<div class="rounded-lg border border-gray-200 dark:border-gray-800 overflow-hidden">
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-gray-200 dark:divide-gray-800 bg-gray-50 dark:bg-gray-800/50">
        <div class="p-3 text-center">
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Criada em</div>
            <div class="text-[13px] font-bold text-gray-900 dark:text-white">{{ $solicitacao->created_at?->format('d/m/Y') }}</div>
        </div>
        <div class="p-3 text-center">
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Manutenção recebeu?</div>
            <div class="text-[13px] font-bold {{ $recebida ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                {{ $recebida ? 'Sim' : 'Ainda não' }}
            </div>
        </div>
        <div class="p-3 text-center">
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Tempo decorrido</div>
            <div class="text-[13px] font-bold text-gray-900 dark:text-white">{{ $solicitacao->created_at?->diffForHumans(null, true) }}</div>
        </div>
        <div class="p-3 text-center">
            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Status atual</div>
            <div class="text-[13px] font-bold text-gray-900 dark:text-white">{{ $statusLabels[$solicitacao->status_comercial] ?? $solicitacao->status_comercial }}</div>
        </div>
    </div>

    <div class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
        @forelse($events as $event)
            <div class="flex items-start gap-3 px-3 py-2">
                <span class="text-[11px] font-mono text-gray-400 dark:text-gray-500 w-24 shrink-0 pt-0.5">{{ $event['at']->format('d/m/y H:i') }}</span>
                <div class="min-w-0">
                    <p class="text-[12.5px] font-semibold text-gray-800 dark:text-gray-200">{{ $event['title'] }}</p>
                    @if(!empty($event['body']))
                        <p class="text-[11px] text-gray-400 dark:text-gray-500">{{ $event['body'] }}</p>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-[11px] text-gray-400 italic px-3 py-4 text-center">Nenhum evento registrado ainda.</p>
        @endforelse
    </div>
</div>

<p class="text-[10.5px] text-gray-400 dark:text-gray-600 italic mt-1.5">
    Status atual é derivado das providências já tomadas (OS aberta, movimentação de logística, portaria) -- não é um
    campo digitado, é reconstruído a partir do que já aconteceu de verdade no sistema.
    <a href="{{ \App\Filament\Resources\SolicitacaoLocacaoResource::getUrl('timeline', ['record' => $solicitacao]) }}" class="text-primary-600 hover:underline dark:text-primary-400">Ver linha do tempo completa &rarr;</a>
</p>
