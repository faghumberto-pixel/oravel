<x-filament-panels::page>
    <form wire:submit="create">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Enviar Solicitação
        </x-filament::button>
    </form>
</x-filament-panels::page>
