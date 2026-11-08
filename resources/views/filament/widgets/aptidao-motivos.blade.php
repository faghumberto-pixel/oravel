<x-filament-widgets::widget>
    <x-filament::section heading="Principais motivos de Inapto e de restrição">
        @php($motivos = $this->motivos())
        @if (empty($motivos))
            <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum ativo com restrição ou inapto no momento.</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($motivos as $m)
                    <li class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span>{{ $m['texto'] }}</span>
                        <span class="whitespace-nowrap font-semibold {{ $m['nivel'] === 'inapto' ? 'text-danger-600 dark:text-danger-400' : 'text-warning-600 dark:text-warning-400' }}">
                            {{ $m['ativos'] }} ativo(s)
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
