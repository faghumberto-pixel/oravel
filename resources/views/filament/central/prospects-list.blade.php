<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($this->cards() as $card)
                <button
                    type="button"
                    wire:click="filtrarCard('{{ $card['chave'] }}')"
                    @class([
                        'rounded-xl border p-3 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                        'border-primary-500 bg-primary-50 dark:bg-primary-500/10' => $card['ativo'],
                        'border-gray-200 bg-white hover:border-primary-400 dark:border-white/10 dark:bg-gray-900' => ! $card['ativo'],
                    ])
                >
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $card['rotulo'] }}</div>
                    <div class="text-2xl font-semibold">{{ number_format($card['total'], 0, ',', '.') }}</div>
                </button>
            @endforeach
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
