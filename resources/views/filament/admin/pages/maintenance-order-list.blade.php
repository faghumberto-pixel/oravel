<x-mobile.shell title="Ordens de Serviço" :subtitle="$this->total.' ordens registradas'" active="os">
    <x-slot:search>
        <div class="relative">
            <input
                wire:model.live="search"
                type="text"
                placeholder="Buscar por cliente, OS ou tipo..."
                class="w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 pl-10 text-zinc-900 placeholder-zinc-400 shadow-md focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
            <svg class="absolute left-3 top-3.5 h-5 w-5 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
    </x-slot:search>

    <x-slot:tabs>
        <div class="flex gap-2 overflow-x-auto pb-2">
            <button
                wire:click="$set('statusFilter', '')"
                class="px-4 py-2 rounded-full font-semibold whitespace-nowrap transition {{ $statusFilter === '' ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-zinc-700' }}"
            >
                Todas <span class="text-sm font-normal">({{ $this->total }})</span>
            </button>
            <button
                wire:click="$set('statusFilter', 'Pendente')"
                class="px-4 py-2 rounded-full font-semibold whitespace-nowrap transition {{ $statusFilter === 'Pendente' ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-amber-600' }}"
            >
                Pendentes <span class="text-sm font-normal">({{ $this->pendingCount }})</span>
            </button>
            <button
                wire:click="$set('statusFilter', 'Em Andamento')"
                class="px-4 py-2 rounded-full font-semibold whitespace-nowrap transition {{ $statusFilter === 'Em Andamento' ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-zinc-700' }}"
            >
                Em andamento <span class="text-sm font-normal">({{ $this->inProgressCount }})</span>
            </button>
            <button
                wire:click="$set('statusFilter', 'Concluída')"
                class="px-4 py-2 rounded-full font-semibold whitespace-nowrap transition {{ $statusFilter === 'Concluída' ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-green-600' }}"
            >
                Concluídas <span class="text-sm font-normal">({{ $this->completedCount }})</span>
            </button>
        </div>
    </x-slot:tabs>

    @forelse ($this->orders as $order)
                <a href="{{ route('maintenance-orders.field-wizard', $order) }}" class="block bg-white rounded-xl border border-zinc-200 shadow-sm hover:shadow-md transition p-4">
                    {{-- Header do card --}}
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-blue-600 font-semibold">OS #{{ $order->os_number }}</span>
                        <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $order->status === 'Concluída' ? 'bg-green-100 text-green-800' : ($order->status === 'Em Andamento' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') }}">
                            {{ $order->status }}
                        </span>
                    </div>
    
                    {{-- Nome do cliente --}}
                    <h3 class="font-bold text-zinc-900 truncate mb-1">
                        {{ $order->client?->name ?? 'Sem cliente' }}
                    </h3>
    
                    {{-- Tipo de serviço --}}
                    @if ($order->maintenance_type)
                        <p class="text-sm text-zinc-600 mb-3">{{ $order->maintenance_type }}</p>
                    @endif
    
                    {{-- Localização --}}
                    @if ($order->asset?->endereco)
                        <div class="flex items-center gap-2 text-sm text-zinc-600 mb-2">
                            <svg class="w-4 h-4 text-zinc-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"/>
                            </svg>
                            <span class="truncate">{{ $order->asset->endereco }}</span>
                        </div>
                    @endif
    
                    {{-- Data e Prioridade --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2 text-sm text-zinc-600">
                            <svg class="w-4 h-4 text-zinc-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
                            </svg>
                            <span>{{ $order->created_at->format('d M Y') }}</span>
                        </div>
                        @if ($order->asset?->currentCriticalityLevel())
                            <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                                {{ $order->asset->currentCriticalityLevel()->code }}
                            </span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="text-center py-12">
                    <svg class="w-12 h-12 text-zinc-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-zinc-600 font-medium">Nenhuma ordem encontrada</p>
                </div>
            @endforelse

    <x-slot:fab>
        <a href="{{ route('filament.admin.resources.maintenance-orders.create') }}" class="absolute bottom-24 right-5 rounded-full bg-blue-600 p-4 text-white shadow-lg transition hover:bg-blue-700 active:scale-95" aria-label="Nova OS">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </a>
    </x-slot:fab>
</x-mobile.shell>
