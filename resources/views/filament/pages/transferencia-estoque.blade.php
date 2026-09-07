<x-filament-panels::page>
    <form wire:submit="transferir">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-arrows-right-left">
                Transferir
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
