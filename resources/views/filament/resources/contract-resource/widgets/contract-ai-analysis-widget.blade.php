<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                🤖 Análise de IA
            </h3>
            <button
                wire:click="loadAnalysis"
                wire:loading.attr="disabled"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors text-sm font-medium"
            >
                <span wire:loading.remove>Analisar</span>
                <span wire:loading>Analisando...</span>
            </button>
        </div>

        @if($loading)
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <span class="ml-3 text-gray-600 dark:text-gray-400">Analisando contrato...</span>
            </div>
        @elseif($analysis)
            <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300">
                {!! nl2br(e($analysis)) !!}
            </div>
        @else
            <div class="py-8 text-center text-gray-500 dark:text-gray-400">
                <p>Clique em "Analisar" para gerar análise com IA</p>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
