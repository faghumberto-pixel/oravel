<x-filament-panels::page>
    <div class="space-y-6">
        {{-- BOTOES DE NAVEGACAO --}}
        <div class="flex gap-4">
            <x-filament::button wire:click="selectTab('gestao')" color="{{ $activeTab === 'gestao' ? 'primary' : 'gray' }}">
                Painel de Gestão
            </x-filament::button>
            <x-filament::button wire:click="selectTab('comando')" color="{{ $activeTab === 'comando' ? 'warning' : 'gray' }}">
                Centro de Comando
            </x-filament::button>
        </div>
        {{-- AREA DE CONTEUDO UNIFICADA --}}
        <div class="w-full space-y-6">
            @if($activeTab === 'gestao')
                {{-- PAINEL DE GESTAO --}}
                @livewire(\App\Filament\Widgets\RadarOperacional::class)

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @livewire(\App\Filament\Widgets\AssetsByStatusChart::class)
                    @livewire(\App\Filament\Widgets\MaintenanceByStatusChart::class)
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @livewire(\App\Filament\Widgets\MobilizationVsDemobilizationChart::class)
                    @livewire(\App\Filament\Widgets\ReplacementVsRepairChart::class)
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @livewire(\App\Filament\Widgets\MaintenanceCostChart::class)
                    @livewire(\App\Filament\Widgets\DamagesBySeverityChart::class)
                </div>

                <div class="grid grid-cols-1 gap-6">
                    @livewire(\App\Filament\Widgets\TopClientsByRentals::class)
                </div>
            @else
                {{-- CENTRO DE COMANDO --}}
                <div>
                    <h2 class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-3">Minhas Ordens de Serviço</h2>
                    @livewire(\App\Filament\Widgets\TechnicianOrderStats::class)
                </div>

                @livewire(\App\Filament\Widgets\RadarUrgencia::class)

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    @livewire(\App\Filament\Widgets\OrdersByTechnicianChart::class)
                    @livewire(\App\Filament\Widgets\CompletedOrdersLast7DaysChart::class)
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="md:col-span-3 bg-[#0d1321] border border-slate-800 rounded-lg p-6 shadow-2xl min-h-[300px]">
                        <h2 class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-4">Lista de Ativos - Alerta Visual</h2>
                        @livewire(\App\Filament\Widgets\ListaAlertaAtivos::class)
                    </div>
                    <div class="md:col-span-1 bg-[#0d1321] border border-slate-800 rounded-lg p-6 shadow-2xl min-h-[300px]">
                        <h2 class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-4">Agenda de Campo</h2>
                        @livewire(\App\Filament\Widgets\AgendaCampo::class)
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
