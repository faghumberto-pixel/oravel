<x-filament-panels::page>
    @if(auth()->user()->isAdmin())
        <div class="mb-4">
            <select wire:model.live="technicianId" class="fi-input w-full max-w-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white text-sm">
                <option value="">Todos os técnicos</option>
                @foreach($this->technicians as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @livewire(\App\Filament\Widgets\AgendaTecnicoWidget::class, ['technicianId' => $technicianId], key('agenda-tecnico-'.$technicianId))
</x-filament-panels::page>
