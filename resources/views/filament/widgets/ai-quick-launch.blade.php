<x-filament-widgets::widget>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        @foreach ($this->getAreas() as $area)
            @if ($area['visible'])
                <a
                    href="{{ $area['url'] }}"
                    class="fi-section flex flex-col gap-2 rounded-xl border p-5 transition hover:border-primary-400 dark:hover:border-primary-500"
                >
                    <x-filament::icon
                        :icon="$area['icon']"
                        class="h-8 w-8"
                        style="color: {{ $area['color'] }}"
                    />
                    <span class="font-bold">{{ $area['label'] }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $area['description'] }}</span>
                </a>
            @endif
        @endforeach
    </div>
</x-filament-widgets::widget>
