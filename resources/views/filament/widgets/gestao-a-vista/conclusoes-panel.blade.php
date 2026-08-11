<x-filament-widgets::widget>
    @php($bullets = $this->getBullets())
    <div class="rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 p-3 h-full">
        <h2 class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">
            <x-heroicon-o-light-bulb class="w-3.5 h-3.5" />
            Conclusão
        </h2>
        @if (empty($bullets))
            <p class="text-[12px] text-gray-500">Sem dados suficientes no período para gerar conclusões.</p>
        @else
            <ul class="space-y-2">
                @foreach ($bullets as $bullet)
                    <li class="flex gap-2 text-[12px] leading-relaxed text-gray-200">
                        <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-indigo-400"></span>
                        <span>{{ $bullet }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-filament-widgets::widget>
