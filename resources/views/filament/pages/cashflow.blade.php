<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
        />
    </div>

    <div class="flex gap-6 w-full">
        @foreach ($this->getFooterWidgets() as $widget)
            <div class="w-1/2">
                @livewire($widget)
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
