<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($this->cards() as $card)
                @if ($card['grupo'] === 'geral')
                    <x-central.card-filtro :card="$card" />
                @endif
            @endforeach
        </div>

        <div>
            <h3 class="mb-2 text-sm font-semibold text-gray-600 dark:text-gray-300">Por estágio / resultado</h3>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($this->cards() as $card)
                    @if ($card['grupo'] === 'resultado')
                        <x-central.card-filtro :card="$card" />
                    @endif
                @endforeach
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
