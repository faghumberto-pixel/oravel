<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-4">
        @foreach($this->resumo() as $item)
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500">{{ $item['label'] }}</div>
                <div class="mt-1 text-2xl font-bold {{ $item['cor'] }}">R$ {{ number_format($item['total'], 2, ',', '.') }}</div>
                <div class="text-xs text-gray-500">{{ $item['qtd'] }} {{ $item['qtd'] === 1 ? 'conta' : 'contas' }}</div>
            </div>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
