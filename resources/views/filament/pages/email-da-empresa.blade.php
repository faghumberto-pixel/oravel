<x-filament-panels::page>
    <form wire:submit="salvar" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" color="gray">Salvar</x-filament::button>
            <x-filament::button type="button" wire:click="testarEAtivar" color="success" icon="heroicon-o-check-circle">Testar conexão e ativar</x-filament::button>
            @if($this->config()?->enabled)
                <x-filament::button type="button" wire:click="desativar" color="danger" outlined>Voltar a usar a caixa da Oravel</x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>
