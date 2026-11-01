<x-filament-panels::page>
    <div class="fi-section rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">Prévia do site novo</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Navegue pelas abas da prévia. Abaixo, registre acompanhamentos, status e pendências.</p>
            </div>
            <a href="{{ route('central.previa-site') }}" target="_blank" rel="noopener"
               class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                Abrir em tela cheia
            </a>
        </div>
        <iframe src="{{ route('central.previa-site') }}" title="Prévia do site novo da Oravel" loading="lazy"
                class="w-full rounded-lg border border-gray-200 dark:border-gray-700" style="height: 680px"></iframe>
    </div>
</x-filament-panels::page>
