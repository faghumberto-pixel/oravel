<x-filament-widgets::widgets
    :widgets="$this->getWidgets()"
    @class([
        'fi-hidden' => empty($widgets = $this->getWidgets()),
    ])
/>

<x-filament::section>
    {{ $this->table }}
</x-filament::section>
