<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Summary Stats --}}
        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Baixadas Automaticamente</p>
                        <p class="mt-2 text-2xl font-bold">{{ $summary['automaticallySettled'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            R$ {{ number_format($summary['automaticallySettledAmount'] ?? 0, 2, ',', '.') }}
                        </p>
                    </div>
                    <div class="text-3xl text-green-500">✓</div>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Pendentes de Confirmação</p>
                        <p class="mt-2 text-2xl font-bold">{{ $summary['pendingConfirmation'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            R$ {{ number_format($summary['pendingConfirmationAmount'] ?? 0, 2, ',', '.') }}
                        </p>
                    </div>
                    <div class="text-3xl text-yellow-500">⏳</div>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Cobrança Manual</p>
                        <p class="mt-2 text-2xl font-bold">{{ $summary['manualSettlement'] ?? 0 }}</p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            R$ {{ number_format($summary['manualSettlementAmount'] ?? 0, 2, ',', '.') }}
                        </p>
                    </div>
                    <div class="text-3xl text-blue-500">📋</div>
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
