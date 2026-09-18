<x-filament-panels::page>
    <div class="grid gap-6">
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columnSpan="[
                'sm' => 12,
                'md' => 12,
                'lg' => 12,
            ]"
        />

        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
            :columnSpan="[
                'sm' => 12,
                'md' => 12,
                'lg' => 12,
            ]"
        />
    </div>
</x-filament-panels::page>
