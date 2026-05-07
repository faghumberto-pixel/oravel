<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        {{-- Renderiza os widgets --}}
        @foreach($this->getHeaderWidgets() as $widget)
            @livewire(\Livewire\Livewire::getClass($widget))
        @endforeach
    </div>
</x-filament-panels::page>
