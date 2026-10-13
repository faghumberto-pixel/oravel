<x-filament-panels::page>
    <form wire:submit="send">
        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4">
            Enviar
        </x-filament::button>
    </form>

    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
