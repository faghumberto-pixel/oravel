<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">Chamados de Emergência em Aberto</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            O.S. tipo "Chamado de Emergência" com prazo de atendimento definido — o relógio para quando o técnico inicia o serviço.
        </p>

        @php $resumo = $this->resumo; @endphp
        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                <span class="block text-[10px] uppercase tracking-widest text-gray-400">Chamados Abertos</span>
                <span class="mt-1 block text-2xl font-black text-gray-900 dark:text-white">{{ $resumo['total'] }}</span>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                <span class="block text-[10px] uppercase tracking-widest text-gray-400">No Prazo</span>
                <span class="mt-1 block text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $resumo['no_prazo_pct'] }}%</span>
            </div>
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                <span class="block text-[10px] uppercase tracking-widest text-gray-400">Vencidos</span>
                <span class="mt-1 block text-2xl font-black text-danger-600 dark:text-danger-400">{{ $resumo['vencidos_pct'] }}%</span>
            </div>
        </div>

        @php
            $corStyles = [
                'danger' => 'bg-danger-50 text-danger-700 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400',
                'warning' => 'bg-warning-50 text-warning-700 ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400',
                'success' => 'bg-success-50 text-success-700 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400',
                'gray' => 'bg-gray-50 text-gray-600 ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400',
            ];
            $corLabels = ['danger' => 'Vencido', 'warning' => 'Próximo do prazo', 'success' => 'No prazo', 'gray' => 'Em atendimento'];
        @endphp

        <div class="mt-6 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-xs uppercase text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2 font-medium">OS</th>
                        <th class="px-4 py-2 font-medium">Ativo</th>
                        <th class="px-4 py-2 font-medium">Cliente</th>
                        <th class="px-4 py-2 font-medium">Técnico</th>
                        <th class="px-4 py-2 font-medium">Meta</th>
                        <th class="px-4 py-2 font-medium">Decorrido</th>
                        <th class="px-4 py-2 font-medium">Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->chamados as $linha)
                        @php $order = $linha['order']; @endphp
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2">
                                <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $order]) }}"
                                   class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                    OS #{{ $order->os_number }}
                                </a>
                            </td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $order->asset?->patrimonio ?? '—' }} — {{ $order->asset?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $order->client?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $order->technician?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $order->sla_target_minutes }}min</td>
                            <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                {{ $linha['minutos_decorridos'] !== null ? $order->created_at->diffForHumans(null, true) : 'atendimento iniciado' }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="fi-badge inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $corStyles[$linha['cor']] }}">
                                    {{ $corLabels[$linha['cor']] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                Nenhum chamado de emergência em aberto no momento.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
