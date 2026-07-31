<div class="min-h-screen bg-gray-50">
    {{-- Header com gradiente --}}
    <div class="bg-gradient-to-r from-blue-600 to-blue-500 rounded-b-3xl pb-8">
        <div class="px-5 pt-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h1 class="text-3xl font-bold text-white">Ordens de Serviço</h1>
                    <p class="text-sm text-blue-100 mt-1">{{ $this->total }} ordens registradas</p>
                </div>
                <button class="p-2 bg-blue-400 bg-opacity-30 rounded-lg text-white hover:bg-opacity-40 transition">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.5 1.5H3.75A2.25 2.25 0 001.5 3.75v12.5A2.25 2.25 0 003.75 18.5h12.5a2.25 2.25 0 002.25-2.25V9.5"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Barra de pesquisa flutuante --}}
    <div class="px-5 -mt-4 mb-6 relative z-10">
        <div class="relative">
            <input
                wire:model.live="search"
                type="text"
                placeholder="Buscar por cliente, OS ou tipo..."
                class="w-full px-4 py-3 pl-10 rounded-xl border border-gray-200 bg-white shadow-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            >
            <svg class="absolute left-3 top-3.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
        </div>
    </div>

    {{-- Tabs de filtro --}}
    <div class="px-5 mb-6">
        <div class="flex gap-2 overflow-x-auto pb-2">
            <button
                wire:click="$set('statusFilter', '')"
                class="px-4 py-2 rounded-full font-semibold whitespace-nowrap transition {{ $statusFilter === '' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' }}"
            >
                Todas <span class="text-sm font-normal">({{ $this->total }})</span>
            </button>
            <button
                wire:click="$set('statusFilter', 'Pendente')"
                class="px-4 py-2 rounded-full font-semibold whitespace-nowrap transition {{ $statusFilter === 'Pendente' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-amber-600' }}"
            >
                Pendentes <span class="text-sm font-normal">({{ $this->pendingCount }})</span>
            </button>
            <button
                wire:click="$set('statusFilter', 'Em Andamento')"
                class="px-4 py-2 rounded-full font-semibold whitespace-nowrap transition {{ $statusFilter === 'Em Andamento' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' }}"
            >
                Em andamento <span class="text-sm font-normal">({{ $this->inProgressCount }})</span>
            </button>
            <button
                wire:click="$set('statusFilter', 'Concluída')"
                class="px-4 py-2 rounded-full font-semibold whitespace-nowrap transition {{ $statusFilter === 'Concluída' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-green-600' }}"
            >
                Concluídas <span class="text-sm font-normal">({{ $this->completedCount }})</span>
            </button>
        </div>
    </div>

    {{-- Lista de cards --}}
    <div class="px-5 pb-32 space-y-3">
        @forelse ($this->orders as $order)
            <a href="{{ route('filament.admin.resources.maintenance-orders.edit', $order) }}" class="block bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition p-4">
                {{-- Header do card --}}
                <div class="flex items-center justify-between mb-3">
                    <span class="text-blue-600 font-semibold">OS #{{ $order->os_number }}</span>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $order->status === 'Concluída' ? 'bg-green-100 text-green-800' : ($order->status === 'Em Andamento' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800') }}">
                        {{ $order->status }}
                    </span>
                </div>

                {{-- Nome do cliente --}}
                <h3 class="font-bold text-gray-900 truncate mb-1">
                    {{ $order->client?->name ?? 'Sem cliente' }}
                </h3>

                {{-- Tipo de serviço --}}
                @if ($order->maintenance_type)
                    <p class="text-sm text-gray-600 mb-3">{{ $order->maintenance_type }}</p>
                @endif

                {{-- Localização --}}
                @if ($order->asset?->endereco)
                    <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z"/>
                        </svg>
                        <span class="truncate">{{ $order->asset->endereco }}</span>
                    </div>
                @endif

                {{-- Data e Prioridade --}}
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
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
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-gray-600 font-medium">Nenhuma ordem encontrada</p>
            </div>
        @endforelse
    </div>

    {{-- FAB (Floating Action Button) --}}
    <a href="{{ route('filament.admin.resources.maintenance-orders.create') }}" class="fixed bottom-24 right-5 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg p-4 transition transform active:scale-95">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
    </a>

    {{-- Bottom tab bar --}}
    <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 flex justify-around items-center h-20">
        <a href="{{ route('filament.admin.pages.dashboard') }}" class="flex flex-col items-center justify-center h-full w-full text-gray-600 hover:text-blue-600 transition">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>
            </svg>
            <span class="text-xs mt-1">Início</span>
        </a>
        <button class="flex flex-col items-center justify-center h-full w-full text-blue-600 transition">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>
            </svg>
            <span class="text-xs mt-1">OS</span>
        </button>
        <a href="{{ route('filament.admin.resources.clients.index') }}" class="flex flex-col items-center justify-center h-full w-full text-gray-600 hover:text-blue-600 transition">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM9 10a9 9 0 018.946 7.5H2a9 9 0 018.946-7.5z"/>
            </svg>
            <span class="text-xs mt-1">Clientes</span>
        </a>
        <a href="{{ route('filament.admin.pages.agenda-tecnico') }}" class="flex flex-col items-center justify-center h-full w-full text-gray-600 hover:text-blue-600 transition">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
            </svg>
            <span class="text-xs mt-1">Agenda</span>
        </a>
        <button class="flex flex-col items-center justify-center h-full w-full text-gray-600 hover:text-blue-600 transition">
            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                <path d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V17a2 2 0 01-2 2h-1C9.716 19 3 12.284 3 4V3z"/>
            </svg>
            <span class="text-xs mt-1">Mais</span>
        </button>
    </nav>
</div>
