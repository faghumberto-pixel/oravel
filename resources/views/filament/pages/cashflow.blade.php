<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
        />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach ($this->getFooterWidgets() as $widget)
            <div class="col-span-1">
                @livewire($widget)
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
