@php($record = $getRecord())
<div class="w-full">
    <div class="mb-2 flex items-center justify-between gap-2">
        <span class="text-sm font-semibold text-gray-900 dark:text-white">Prévia</span>
        <a href="{{ route('central.acompanhamento.previa', $record) }}" target="_blank" rel="noopener"
           class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
            Abrir em tela cheia
        </a>
    </div>
    <iframe src="{{ route('central.acompanhamento.previa', $record) }}" title="Prévia: {{ $record->nome }}" loading="lazy"
            sandbox="allow-scripts allow-forms allow-popups"
            class="w-full rounded-lg border border-gray-200 dark:border-gray-700" style="height: 680px"></iframe>
</div>
