<x-filament-panels::page>
    @php
        $kpis = $this->getKpis();
        $reservas = $this->getReservas();
    @endphp

    <div class="rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 p-4 mb-4">
        <p class="text-sm text-amber-800 dark:text-amber-300">
            Toda Solicitação de Locação marcada pelo Comercial como <strong>"Reservar para Manutenção (Urgente)"</strong>
            aparece aqui -- o mesmo gatilho que já trava a criação de OS nova nesses Ativos e mostra a faixa vermelha
            no Kanban do Pátio, agora numa fila própria.
        </p>
    </div>

    {{-- ===================== KPIs ===================== --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
        @foreach([
            ['value' => $kpis['total'], 'label' => 'Reservas Urgentes', 'icon' => 'heroicon-o-bell-alert', 'text' => 'text-gray-600 dark:text-gray-400', 'chip' => 'bg-gray-100 dark:bg-gray-800'],
            ['value' => $kpis['semOs'], 'label' => 'Sem OS Aberta', 'icon' => 'heroicon-o-exclamation-triangle', 'text' => 'text-rose-600 dark:text-rose-400', 'chip' => 'bg-rose-50 dark:bg-rose-500/10'],
            ['value' => $kpis['vencidas'], 'label' => 'Prazo Vencido', 'icon' => 'heroicon-o-clock', 'text' => 'text-amber-600 dark:text-amber-400', 'chip' => 'bg-amber-50 dark:bg-amber-500/10'],
            ['value' => $kpis['prontas'], 'label' => 'Prontas p/ Liberar', 'icon' => 'heroicon-o-check-circle', 'text' => 'text-emerald-600 dark:text-emerald-400', 'chip' => 'bg-emerald-50 dark:bg-emerald-500/10'],
        ] as $kpi)
            <div class="flex items-center gap-2.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $kpi['chip'] }} {{ $kpi['text'] }}">
                    <x-dynamic-component :component="$kpi['icon']" class="h-4 w-4" />
                </span>
                <div class="min-w-0 leading-tight">
                    <div class="text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $kpi['value'] }}</div>
                    <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 truncate">{{ $kpi['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ===================== FILA ===================== --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
        @if($reservas->isEmpty())
            <div class="text-center py-16">
                <x-heroicon-o-check-circle class="w-8 h-8 mx-auto text-emerald-400 mb-2" />
                <p class="text-sm font-bold text-gray-500 dark:text-gray-400">Nenhuma reserva urgente no momento.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-[10px] uppercase text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-800">
                            <th class="px-4 py-2 font-medium">Cliente</th>
                            <th class="px-4 py-2 font-medium">Ativo / Categoria</th>
                            <th class="px-4 py-2 font-medium">Status do Ativo</th>
                            <th class="px-4 py-2 font-medium">OS Aberta</th>
                            <th class="px-4 py-2 font-medium">Prazo</th>
                            <th class="px-4 py-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reservas as $row)
                            @php
                                $solicitacao = $row['solicitacao'];
                                $asset = $row['asset'];
                                $openOrder = $row['openOrder'];
                            @endphp
                            <tr class="border-b border-gray-100 dark:border-gray-800 last:border-0">
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $solicitacao->customer?->name ?? '—' }}</td>
                                <td class="px-4 py-2.5">
                                    @if($asset)
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $asset->patrimonio ?? '—' }} — {{ $asset->name }}</span>
                                    @else
                                        <span class="text-gray-400 italic">Categoria: {{ $solicitacao->category?->name ?? '—' }} (ativo não definido)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($asset)
                                        <span class="inline-flex items-center rounded-md px-2 py-1 text-[10px] font-bold uppercase tracking-wide
                                            {{ $asset->status === \App\Models\Asset::STATUS_DISPONIVEL ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                                            {{ ucfirst($asset->status ?? '—') }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($openOrder)
                                        <a href="{{ \App\Filament\Resources\MaintenanceOrderResource::getUrl('edit', ['record' => $openOrder]) }}"
                                           class="text-primary-600 hover:underline dark:text-primary-400">
                                            OS #{{ $openOrder->os_number ?? \Illuminate\Support\Str::substr($openOrder->id, 0, 8) }}
                                        </a>
                                    @elseif($asset)
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-rose-600 dark:text-rose-400">
                                            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                                            Nenhuma OS aberta
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    @if($row['diasRestantes'] === null)
                                        <span class="text-gray-400">Sem prazo</span>
                                    @elseif($row['vencida'])
                                        <span class="font-semibold text-rose-600 dark:text-rose-400">Vencida há {{ abs($row['diasRestantes']) }} dia(s)</span>
                                    @else
                                        <span class="text-gray-700 dark:text-gray-300">{{ $solicitacao->data_saida_prevista?->format('d/m/Y') }} ({{ $row['diasRestantes'] }}d)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <a href="{{ \App\Filament\Resources\SolicitacaoLocacaoResource::getUrl('edit', ['record' => $solicitacao]) }}"
                                       class="text-[11px] font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        Ver Solicitação
                                    </a>
                                    @if($asset)
                                        <span class="text-gray-300 dark:text-gray-700 mx-1">·</span>
                                        <a href="{{ \App\Filament\Pages\HistoricoPatrimonio::getUrl(['assetId' => $asset->id]) }}"
                                           class="text-[11px] font-medium text-primary-600 hover:underline dark:text-primary-400">
                                            Histórico
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-panels::page>
