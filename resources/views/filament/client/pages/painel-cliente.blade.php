<x-filament-panels::page>
    <div class="space-y-6">
    {{-- Cabeçalho de saudação --}}
    <div class="mb-2">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Olá, {{ $client->name }}</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Aqui está o resumo da sua locação com {{ $client->tenant?->name }}.</p>
    </div>

    {{-- Cards de indicadores --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Equipamentos Locados</p>
            <p class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white">{{ $stats['equipamentos'] }}</p>
        </div>
        <div class="rounded-xl border border-orange-200 bg-orange-50 p-4 dark:border-orange-800 dark:bg-orange-900/30">
            <p class="text-xs font-semibold uppercase tracking-wide text-orange-700 dark:text-orange-300">Contratos Vigentes</p>
            <p class="mt-2 text-2xl font-extrabold text-orange-700 dark:text-orange-300">{{ $stats['contratos_vigentes'] }}</p>
        </div>
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/30">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">OS em Andamento</p>
            <p class="mt-2 text-2xl font-extrabold text-blue-700 dark:text-blue-300">{{ $stats['os_em_andamento'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Próxima Fatura</p>
            @if ($stats['proxima_fatura'])
                <p class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white">R$ {{ number_format($stats['proxima_fatura']->amount, 2, ',', '.') }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">vence {{ $stats['proxima_fatura']->due_date->format('d/m/Y') }}</p>
            @else
                <p class="mt-2 text-lg font-semibold text-green-600 dark:text-green-400">Tudo em dia</p>
            @endif
        </div>
    </div>

    {{-- Meus Contratos: equipamento + linha do tempo de vigência --}}
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
            <h3 class="font-semibold text-gray-900 dark:text-white">📦 Meus Equipamentos &amp; Contratos em Andamento</h3>
            <a href="{{ \App\Filament\Client\Pages\MeusContratos::getUrl(panel: 'portal-cliente') }}" class="text-xs font-semibold text-orange-600 hover:underline dark:text-orange-400">Ver todos →</a>
        </div>

        @forelse ($equipmentTimelines as $item)
            @php
                $contract = $item['contract'];
                $asset = $item['asset'];
                $percent = $item['percent'];
            @endphp
            <div class="border-b border-gray-100 p-4 last:border-0 dark:border-gray-700">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $asset?->name ?? 'Equipamento' }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Contrato {{ $contract->contract_number }}
                            @if ($asset?->patrimonio) · Patrimônio {{ $asset->patrimonio }} @endif
                        </p>
                    </div>
                    <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-800 dark:bg-green-800 dark:text-green-100">
                        Vigente
                    </span>
                </div>

                @if ($percent !== null)
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-[11px] font-medium text-gray-500 dark:text-gray-400">
                            <span>Início: {{ $contract->start_date->format('d/m/Y') }}</span>
                            <span>
                                @if ($item['days_remaining'] !== null && $item['days_remaining'] <= 15)
                                    <span class="font-bold text-amber-600 dark:text-amber-400">{{ $item['days_remaining'] }} dia(s) restante(s)</span>
                                @else
                                    {{ $item['days_remaining'] }} dia(s) restante(s)
                                @endif
                            </span>
                            <span>Fim: {{ $contract->end_date->format('d/m/Y') }}</span>
                        </div>
                        <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                            <div class="h-full rounded-full bg-gradient-to-r from-orange-400 to-orange-600" style="width: {{ $percent }}%"></div>
                        </div>
                        <p class="mt-1 text-right text-[11px] text-gray-400">{{ $percent }}% do prazo decorrido</p>
                    </div>
                @endif

                <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span>💰 R$ {{ number_format($contract->price, 2, ',', '.') }}/{{ $contract->billing_type === 'diaria' ? 'diária' : 'mês' }}</span>
                    @if ($asset?->current_horimeter)
                        <span>⏱️ Horímetro: {{ number_format($asset->current_horimeter, 1, ',', '.') }} h</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="p-6 text-center text-sm text-gray-400">Nenhum contrato ativo com equipamento no momento.</p>
        @endforelse
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Últimas manutenções --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white">🔧 Manutenções no Equipamento</h3>
                <a href="{{ \App\Filament\Client\Pages\MinhasOS::getUrl(panel: 'portal-cliente') }}" class="text-xs font-semibold text-orange-600 hover:underline dark:text-orange-400">Ver todas →</a>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($recentOrders as $order)
                    @php
                        $statusColor = match ($order->status) {
                            'Concluída' => ['bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100', '✅'],
                            'Em Andamento' => ['bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100', '🔵'],
                            'Cancelada' => ['bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300', '⛔'],
                            default => ['bg-amber-100 text-amber-800 dark:bg-amber-800 dark:text-amber-100', '🟡'],
                        };
                    @endphp
                    <li class="flex items-start gap-3 p-4">
                        <span class="mt-0.5 text-lg">{{ $statusColor[1] }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $order->os_number }} · {{ $order->maintenance_type }}</p>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $statusColor[0] }}">{{ $order->status }}</span>
                            </div>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $order->asset?->name ?? 'Equipamento' }} — {{ $order->description ?? 'Sem descrição' }}</p>
                            <p class="text-[11px] text-gray-400">{{ $order->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </li>
                @empty
                    <li class="p-6 text-center text-sm text-gray-400">Nenhuma manutenção registrada ainda.</li>
                @endforelse
            </ul>
        </div>

        {{-- Financeiro: pagos e em aberto --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-200 p-4 dark:border-gray-700">
                <h3 class="font-semibold text-gray-900 dark:text-white">💳 Financeiro</h3>
                <a href="{{ \App\Filament\Client\Pages\MeuFinanceiro::getUrl(panel: 'portal-cliente') }}" class="text-xs font-semibold text-orange-600 hover:underline dark:text-orange-400">Ver tudo →</a>
            </div>
            <div class="flex gap-3 border-b border-gray-100 p-4 dark:border-gray-700">
                <div class="flex-1 rounded-lg bg-green-50 p-3 text-center dark:bg-green-900/30">
                    <p class="text-lg font-extrabold text-green-700 dark:text-green-300">{{ $invoicesPagas }}</p>
                    <p class="text-[11px] font-semibold uppercase text-green-700 dark:text-green-300">Pagas</p>
                </div>
                <div class="flex-1 rounded-lg bg-amber-50 p-3 text-center dark:bg-amber-900/30">
                    <p class="text-lg font-extrabold text-amber-700 dark:text-amber-300">{{ $invoicesAbertas }}</p>
                    <p class="text-[11px] font-semibold uppercase text-amber-700 dark:text-amber-300">Em Aberto</p>
                </div>
            </div>
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($recentInvoices as $invoice)
                    @php
                        $invColor = match ($invoice->status) {
                            'pago' => 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                            'atrasado' => 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                            default => 'bg-amber-100 text-amber-800 dark:bg-amber-800 dark:text-amber-100',
                        };
                    @endphp
                    <li class="flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $invoice->description }}</p>
                            <p class="text-[11px] text-gray-400">Vencimento {{ $invoice->due_date->format('d/m/Y') }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="text-sm font-semibold text-gray-900 dark:text-white">R$ {{ number_format($invoice->amount, 2, ',', '.') }}</span>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold capitalize {{ $invColor }}">{{ $invoice->status }}</span>
                        </div>
                    </li>
                @empty
                    <li class="p-6 text-center text-sm text-gray-400">Nenhuma fatura por aqui ainda.</li>
                @endforelse
            </ul>
        </div>
    </div>
    </div>
</x-filament-panels::page>
