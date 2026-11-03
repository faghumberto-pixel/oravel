<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($this->cards() as $card)
                <x-central.card-filtro :card="$card" />
            @endforeach
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
