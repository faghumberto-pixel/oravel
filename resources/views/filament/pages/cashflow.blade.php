<x-filament-panels::page>
    {{-- Header Widgets (Stats) --}}
    <div class="grid gap-6">
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
        />
    </div>

    {{-- Filters --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold mb-4">Filtros</h3>
        <form wire:submit="submit" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium mb-2">Data Inicial</label>
                <input type="date" wire:model="dateStart" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Data Final</label>
                <input type="date" wire:model="dateEnd" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Tipo</label>
                <select wire:model="type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                    <option value="">Todos</option>
                    <option value="AR">Contas a Receber</option>
                    <option value="AP">Contas a Pagar</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-2">Status</label>
                <select wire:model="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                    <option value="">Todos</option>
                    <option value="pendente">Pendente</option>
                    <option value="atrasado">Atrasado</option>
                    <option value="pago">Pago</option>
                </select>
            </div>
        </form>
    </div>

    {{-- Chart --}}
    <div class="grid gap-6">
        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
        />
    </div>

    {{-- Detail Table (Em desenvolvimento) --}}
</x-filament-panels::page>
