<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
        />
    </div>

    <div class="grid gap-6 md:grid-cols-2">
        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
            :columnSpan="6"
        />
    </div>
</x-filament-panels::page>
