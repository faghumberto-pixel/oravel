<x-filament-widgets::widget>
    @php($custo = $this->getCusto())
    <div class="rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 p-3">
        <h2 class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">
            <x-heroicon-o-banknotes class="w-3.5 h-3.5" />
            Custo Total de Manutenção
        </h2>
        <p class="text-2xl font-bold text-gray-100 tabular-nums">{{ $custo['valor_formatado'] }}</p>
        @if ($custo['variacao_percentual'] !== null)
            @php($subiu = $custo['variacao_percentual'] >= 0)
            <p class="mt-1 flex items-center gap-1 text-[11px] font-semibold {{ $subiu ? 'text-rose-400' : 'text-emerald-400' }}">
                @if ($subiu)
                    <x-heroicon-m-arrow-trending-up class="w-3.5 h-3.5" />
                @else
                    <x-heroicon-m-arrow-trending-down class="w-3.5 h-3.5" />
                @endif
                {{ number_format(abs($custo['variacao_percentual']), 1, ',', '.') }}% vs. período anterior
            </p>
        @else
            <p class="mt-1 text-[11px] text-gray-500">Sem período anterior para comparar</p>
        @endif
    </div>
</x-filament-widgets::widget>
