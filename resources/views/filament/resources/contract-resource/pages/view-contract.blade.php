<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Dados Principais -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Número do Contrato</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">{{ $this->record->contract_number }}</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Cliente</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">{{ $this->record->client?->name ?? 'N/A' }}</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Equipamento</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">{{ $this->record->asset?->name ?? 'N/A' }}</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $this->record->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' }}">
                        {{ $this->record->is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                </p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Início da Vigência</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">{{ $this->record->start_date?->format('d/m/Y') ?? 'N/A' }}</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Fim da Vigência</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">{{ $this->record->end_date?->format('d/m/Y') ?? 'N/A' }}</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Valor</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">R$ {{ number_format($this->record->price ?? 0, 2, ',', '.') }}</p>
            </div>
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tipo de Faturamento</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white mt-1">{{ $this->record->billing_type ?? 'N/A' }}</p>
            </div>
        </div>

        <!-- Observações -->
        @if($this->record->observations)
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Observações</h3>
                <p class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $this->record->observations }}</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
