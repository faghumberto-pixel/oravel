<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alocação de Técnicos - ORAVEL</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; color: black; padding: 0; margin: 0; }
            @page { margin: 1.2cm; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased font-sans p-4 sm:p-8">

    <div class="max-w-5xl mx-auto mb-6 flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm no-print">
        <span class="text-sm text-gray-500 font-medium">➔ <strong>Visualização PHP Minimalista</strong> — Alocação de Técnicos</span>
        <div class="flex gap-2">
            <button onclick="window.close()" class="px-4 py-2 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">Fechar</button>
            <button onclick="window.print()" class="px-4 py-2 text-sm font-semibold bg-amber-500 hover:bg-amber-600 text-white rounded-lg shadow-sm transition">Imprimir Agora</button>
        </div>
    </div>

    <div class="max-w-5xl mx-auto bg-white p-8 sm:p-12 rounded-2xl border border-gray-200 shadow-sm relative">

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-gray-200 pb-6 mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-black tracking-tight"><span class="text-amber-500">O</span>ravel ERP</h1>
                <p class="text-xs text-gray-500 font-mono mt-1">{{ now()->format('d/m/Y H:i:s') }}</p>
            </div>
            <div class="text-left sm:text-right">
                <h2 class="text-xl font-bold text-gray-900">ALOCAÇÃO DE TÉCNICOS</h2>
                <p class="text-sm font-mono font-black text-amber-600 mt-0.5">{{ $periodStart->format('d/m/Y') }} — {{ $periodEnd->format('d/m/Y') }}</p>
            </div>
        </div>

        {{-- Filtros aplicados --}}
        <div class="bg-gray-50 rounded-xl p-4 mb-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-medium text-gray-600 border border-gray-100">
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Visão</span>
                <span class="text-gray-900 font-semibold">{{ ['day' => 'Dia', 'week' => 'Semana', 'month' => 'Mês'][$page->viewMode] ?? 'Semana' }}</span>
            </div>
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Cliente</span>
                <span class="text-gray-900 font-semibold">{{ $clientName ?? 'Todos' }}</span>
            </div>
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Técnico</span>
                <span class="text-gray-900 font-semibold">{{ $technicianName ?? 'Todos' }}</span>
            </div>
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Patrimônio</span>
                <span class="text-gray-900 font-semibold">{{ $patrimonioFilter ?: 'Todos' }}</span>
            </div>
        </div>

        {{-- Resumo por técnico --}}
        <div class="mb-8">
            <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-3">Resumo por Técnico</h3>
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-gray-400 uppercase bg-gray-50 border-y border-gray-200/60">
                        <th class="p-2.5">Técnico</th>
                        <th class="p-2.5 text-center">Dias Alocados</th>
                        <th class="p-2.5 text-center">Aguardando Confirmação</th>
                        <th class="p-2.5 text-center">Dias Confirmados</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($technicianSummary as $row)
                        <tr class="border-b border-gray-100">
                            <td class="p-2.5 font-bold text-gray-900">{{ $row['technician']->name }}</td>
                            <td class="p-2.5 text-center font-mono text-gray-700">{{ $row['alocados'] }}</td>
                            <td class="p-2.5 text-center font-mono {{ $row['aguardando'] > 0 ? 'text-amber-700 font-bold' : 'text-gray-400' }}">{{ $row['aguardando'] }}</td>
                            <td class="p-2.5 text-center font-mono text-emerald-700">{{ $row['confirmados'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-4 text-center text-gray-400">Nenhum técnico no filtro selecionado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Detalhe por técnico e alocação --}}
        <div class="mb-8">
            <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-3">Detalhamento das Alocações</h3>
            @forelse($page->technicians as $technician)
                @php
                    $items = $page->allocations->where('technician_id', $technician->id)->sortBy('starts_at');
                @endphp
                @if($items->isNotEmpty())
                    <div class="mb-4 page-break-inside-avoid">
                        <p class="text-xs font-bold text-gray-800 mb-1.5">{{ $technician->name }}</p>
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-gray-400 uppercase bg-gray-50 border-y border-gray-200/60">
                                    <th class="p-2">Data</th>
                                    <th class="p-2">Ativo / Tipo</th>
                                    <th class="p-2">Cliente</th>
                                    <th class="p-2">Status</th>
                                    <th class="p-2">Modalidade</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($items as $allocation)
                                    @php
                                        $clientLabel = $allocation->maintenanceOrder?->client?->name
                                            ?? $allocation->maintenanceOrder?->asset?->client?->name
                                            ?? $allocation->maintenanceDueAlert?->asset?->client?->name
                                            ?? '—';
                                        $statusLabel = match ($allocation->status) {
                                            \App\Models\TechnicianAllocation::STATUS_CONFIRMADO => 'Confirmado',
                                            \App\Models\TechnicianAllocation::STATUS_CONCLUIDO => 'Concluído',
                                            \App\Models\TechnicianAllocation::STATUS_CANCELADO => 'Cancelado',
                                            default => 'Pendente',
                                        };
                                    @endphp
                                    <tr class="border-b border-gray-100">
                                        <td class="p-2 font-mono text-gray-600">{{ $allocation->starts_at->format('d/m/Y') }}</td>
                                        <td class="p-2 text-gray-800">{{ $allocation->displayLabel() }}</td>
                                        <td class="p-2 text-gray-600">{{ $clientLabel }}</td>
                                        <td class="p-2 text-gray-600">{{ $statusLabel }}</td>
                                        <td class="p-2 text-gray-600">{{ $allocation->delivery_mode === \App\Models\TechnicianAllocation::DELIVERY_IMPRESSA ? 'Impressa' : 'Digital' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @empty
                <p class="text-xs text-gray-400">Nenhum técnico no filtro selecionado.</p>
            @endforelse
        </div>

        {{-- Total consolidado --}}
        <div class="mt-8 pt-6 border-t-2 border-gray-200">
            <h3 class="text-sm font-bold text-gray-900 mb-3">Totais do Período</h3>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div class="p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <p class="text-2xl font-black text-gray-900">{{ $technicianSummaryTotals['alocados'] }}</p>
                    <p class="text-[10px] text-gray-500 uppercase font-semibold mt-1">Dias Alocados</p>
                </div>
                <div class="p-4 bg-amber-50 rounded-xl border border-amber-100">
                    <p class="text-2xl font-black text-amber-700">{{ $technicianSummaryTotals['aguardando'] }}</p>
                    <p class="text-[10px] text-amber-600 uppercase font-semibold mt-1">Aguardando Confirmação</p>
                </div>
                <div class="p-4 bg-emerald-50 rounded-xl border border-emerald-100">
                    <p class="text-2xl font-black text-emerald-700">{{ $technicianSummaryTotals['confirmados'] }}</p>
                    <p class="text-[10px] text-emerald-600 uppercase font-semibold mt-1">Dias Confirmados</p>
                </div>
            </div>
        </div>

        <div class="mt-16 pt-8 border-t border-gray-200 text-center text-[10px] text-gray-400 font-mono">
            Documento emitido eletronicamente via Central ORAVEL.<br>
            Visualizado em: {{ now()->format('d/m/Y') }} às {{ now()->format('H:i:s') }} (Horário de Brasília)
        </div>
    </div>

</body>
</html>
