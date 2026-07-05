<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                    Relatório Analítico de Manutenção
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Ordens de serviço do seu tenant agrupadas por status, com tempo médio em cada etapa.
                    Abre em uma nova aba, pronto para impressão.
                </p>
            </div>

            <a
                href="{{ route('maintenance.report') }}"
                target="_blank"
                class="fi-btn inline-flex shrink-0 items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
            >
                <x-heroicon-o-document-chart-bar class="h-4 w-4" />
                Abrir relatório
            </a>
        </div>
    </div>
</x-filament-panels::page>
