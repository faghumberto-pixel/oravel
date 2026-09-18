<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Botões de Ação --}}
        <div class="flex gap-2 justify-end">
            <a href="{{ route('bancary-reconciliation.export-excel', [
                'dateStart' => $this->dateStart,
                'dateEnd' => $this->dateEnd,
                'syncStatus' => $this->syncStatus,
            ]) }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-semibold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Exportar Excel
            </a>
            <button onclick="window.print()"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4H9a2 2 0 01-2-2v-4a2 2 0 012-2h6a2 2 0 012 2v4a2 2 0 01-2 2z"></path>
                </svg>
                Imprimir
            </button>
        </div>

        {{-- Filtros --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-lg font-semibold mb-4">Filtros</h3>
            <form wire:submit="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-2">Data Inicial</label>
                    <input type="date" wire:model="dateStart" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Data Final</label>
                    <input type="date" wire:model="dateEnd" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">Status de Sincronismo</label>
                    <select wire:model="syncStatus" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700">
                        <option value="">Todos</option>
                        <option value="automatic">Automático</option>
                        <option value="pending">Pendente</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
            </form>
        </div>

        {{-- Saldo Consolidado --}}
        <div class="bg-gradient-to-r from-slate-900 to-slate-800 dark:from-slate-950 dark:to-slate-900 rounded-lg p-8 text-white shadow-lg">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <p class="text-sm font-medium text-slate-300 opacity-90">Saldo Baixado</p>
                    <p class="mt-2 text-3xl font-bold">R$ {{ number_format($summary['automaticallySettledAmount'] ?? 0, 2, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $summary['automaticallySettled'] ?? 0 }} operação(ões)</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-300 opacity-90">Saldo Pendente</p>
                    <p class="mt-2 text-3xl font-bold">R$ {{ number_format($summary['pendingConfirmationAmount'] ?? 0, 2, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $summary['pendingConfirmation'] ?? 0 }} operação(ões)</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-300 opacity-90">Saldo Manual</p>
                    <p class="mt-2 text-3xl font-bold">R$ {{ number_format($summary['manualSettlementAmount'] ?? 0, 2, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $summary['manualSettlement'] ?? 0 }} operação(ões)</p>
                </div>
            </div>
            <div class="mt-6 pt-6 border-t border-slate-700">
                <p class="text-sm font-medium text-slate-300">Saldo Total em Conciliação</p>
                <p class="mt-2 text-4xl font-bold">R$ {{ number_format(($summary['automaticallySettledAmount'] ?? 0) + ($summary['pendingConfirmationAmount'] ?? 0) + ($summary['manualSettlementAmount'] ?? 0), 2, ',', '.') }}</p>
            </div>
        </div>

        {{-- Summary Stats Cards --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border-2 border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/20 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-700 dark:text-green-400">Baixadas Automaticamente</p>
                        <p class="mt-2 text-2xl font-bold text-green-900 dark:text-green-300">{{ $summary['automaticallySettled'] ?? 0 }}</p>
                    </div>
                    <div class="text-4xl">✓</div>
                </div>
            </div>

            <div class="rounded-lg border-2 border-yellow-200 bg-yellow-50 dark:border-yellow-800 dark:bg-yellow-900/20 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-yellow-700 dark:text-yellow-400">Pendentes de Confirmação</p>
                        <p class="mt-2 text-2xl font-bold text-yellow-900 dark:text-yellow-300">{{ $summary['pendingConfirmation'] ?? 0 }}</p>
                    </div>
                    <div class="text-4xl">⏳</div>
                </div>
            </div>

            <div class="rounded-lg border-2 border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/20 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-700 dark:text-blue-400">Cobrança Manual</p>
                        <p class="mt-2 text-2xl font-bold text-blue-900 dark:text-blue-300">{{ $summary['manualSettlement'] ?? 0 }}</p>
                    </div>
                    <div class="text-4xl">📋</div>
                </div>
            </div>
        </div>

        {{-- Automatically Settled --}}
        @if ($automaticallySettled->count() > 0)
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-700/50">
                    <h3 class="text-lg font-semibold">✓ Baixadas Automaticamente</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Pagamentos confirmados pelo webhook Asaas</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-gray-200 dark:border-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">Descrição</th>
                                <th class="px-6 py-3 text-left font-medium">Cliente</th>
                                <th class="px-6 py-3 text-right font-medium">Valor</th>
                                <th class="px-6 py-3 text-left font-medium">Vencimento</th>
                                <th class="px-6 py-3 text-left font-medium">Recebimento</th>
                                <th class="px-6 py-3 text-left font-medium">Asaas Payment ID</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach ($automaticallySettled as $receivable)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-6 py-3">{{ $receivable->description }}</td>
                                    <td class="px-6 py-3">{{ $receivable->client?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-right">R$ {{ number_format($receivable->amount, 2, ',', '.') }}</td>
                                    <td class="px-6 py-3">{{ $receivable->due_date->format('d/m/Y') }}</td>
                                    <td class="px-6 py-3">{{ $receivable->payment_date?->format('d/m/Y') ?? '—' }}</td>
                                    <td class="px-6 py-3 font-mono text-xs">{{ substr($receivable->asaas_payment_id, 0, 12) }}...</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Pending Confirmation --}}
        @if ($pendingConfirmation->count() > 0)
            <div class="rounded-lg border border-yellow-200 bg-white dark:border-yellow-800 dark:bg-gray-800">
                <div class="border-b border-yellow-200 bg-yellow-50 px-6 py-4 dark:border-yellow-800 dark:bg-yellow-900/20">
                    <h3 class="text-lg font-semibold">⏳ Pendentes de Confirmação</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Enviadas para Asaas, aguardando pagamento</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-yellow-200 dark:border-yellow-800">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">Descrição</th>
                                <th class="px-6 py-3 text-left font-medium">Cliente</th>
                                <th class="px-6 py-3 text-right font-medium">Valor</th>
                                <th class="px-6 py-3 text-left font-medium">Vencimento</th>
                                <th class="px-6 py-3 text-left font-medium">Status</th>
                                <th class="px-6 py-3 text-left font-medium">Asaas Payment ID</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-yellow-200 dark:divide-yellow-800">
                            @foreach ($pendingConfirmation as $receivable)
                                <tr class="hover:bg-yellow-50 dark:hover:bg-yellow-900/10">
                                    <td class="px-6 py-3">{{ $receivable->description }}</td>
                                    <td class="px-6 py-3">{{ $receivable->client?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-right">R$ {{ number_format($receivable->amount, 2, ',', '.') }}</td>
                                    <td class="px-6 py-3">{{ $receivable->due_date->format('d/m/Y') }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                                            @if ($receivable->status === 'pendente')
                                                style="background-color: #dbeafe; color: #0c4a6e;"
                                            @else
                                                style="background-color: #fee2e2; color: #7f1d1d;"
                                            @endif>
                                            {{ ucfirst($receivable->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 font-mono text-xs">{{ substr($receivable->asaas_payment_id, 0, 12) }}...</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Manual Settlement --}}
        @if ($manualSettlement->count() > 0)
            <div class="rounded-lg border border-blue-200 bg-white dark:border-blue-800 dark:bg-gray-800">
                <div class="border-b border-blue-200 bg-blue-50 px-6 py-4 dark:border-blue-800 dark:bg-blue-900/20">
                    <h3 class="text-lg font-semibold">📋 Cobrança Manual</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Contas sem integração Asaas — requer baixa manual</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-blue-200 dark:border-blue-800">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium">Descrição</th>
                                <th class="px-6 py-3 text-left font-medium">Cliente</th>
                                <th class="px-6 py-3 text-right font-medium">Valor</th>
                                <th class="px-6 py-3 text-left font-medium">Vencimento</th>
                                <th class="px-6 py-3 text-left font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-200 dark:divide-blue-800">
                            @foreach ($manualSettlement as $receivable)
                                <tr class="hover:bg-blue-50 dark:hover:bg-blue-900/10">
                                    <td class="px-6 py-3">{{ $receivable->description }}</td>
                                    <td class="px-6 py-3">{{ $receivable->client?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-right">R$ {{ number_format($receivable->amount, 2, ',', '.') }}</td>
                                    <td class="px-6 py-3">{{ $receivable->due_date->format('d/m/Y') }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium"
                                            @if ($receivable->status === 'pago')
                                                style="background-color: #dcfce7; color: #166534;"
                                            @elseif ($receivable->status === 'pendente')
                                                style="background-color: #dbeafe; color: #0c4a6e;"
                                            @else
                                                style="background-color: #fee2e2; color: #7f1d1d;"
                                            @endif>
                                            {{ ucfirst($receivable->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($automaticallySettled->isEmpty() && $pendingConfirmation->isEmpty() && $manualSettlement->isEmpty())
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-12 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-gray-600 dark:text-gray-400">Nenhuma conta a receber encontrada.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
