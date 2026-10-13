<div class="space-y-4 text-sm">
    <div>
        <p class="text-xs font-semibold uppercase text-gray-500">{{ \App\Models\ClientFeedback::typeLabels()[$registro->type] ?? $registro->type }} · {{ \App\Models\ClientFeedback::statusLabels()[$registro->status] ?? $registro->status }}</p>
        <p class="mt-1 font-semibold text-gray-900 dark:text-white">{{ $registro->subject }}</p>
        <p class="mt-1 whitespace-pre-wrap text-gray-700 dark:text-gray-300">{{ $registro->description }}</p>
    </div>

    @if (filled($registro->response))
        <div class="rounded-lg bg-blue-50 p-3 dark:bg-blue-900/30">
            <p class="text-xs font-semibold uppercase text-blue-700 dark:text-blue-300">Resposta da locadora{{ $registro->responded_at ? ' · '.$registro->responded_at->format('d/m/Y H:i') : '' }}</p>
            <p class="mt-1 whitespace-pre-wrap text-gray-800 dark:text-gray-100">{{ $registro->response }}</p>
        </div>
    @else
        <p class="rounded-lg bg-gray-50 p-3 text-gray-600 dark:bg-gray-800 dark:text-gray-300">Ainda sem resposta. Assim que a locadora responder, ela aparece aqui.</p>
    @endif
</div>
