<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
        />
    </div>

    <div class="grid gap-6 grid-cols-1 lg:grid-cols-2">
        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
        />
    </div>
</x-filament-panels::page>
