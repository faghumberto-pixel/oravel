<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manutenções por Cliente - ORAVEL</title>
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

    @php
        $categoryMeta = [
            'atrasada' => ['label' => 'Atrasada', 'color' => 'text-red-700 bg-red-50'],
            'pendente' => ['label' => 'Pendente', 'color' => 'text-amber-700 bg-amber-50'],
            'em_andamento' => ['label' => 'Em Andamento', 'color' => 'text-sky-700 bg-sky-50'],
            'programada' => ['label' => 'Programada', 'color' => 'text-indigo-700 bg-indigo-50'],
            'concluida' => ['label' => 'Concluída', 'color' => 'text-emerald-700 bg-emerald-50'],
        ];
    @endphp

    <div class="max-w-5xl mx-auto mb-6 flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm no-print">
        <span class="text-sm text-gray-500 font-medium">➔ <strong>Visualização PHP Minimalista</strong> — Manutenções por Cliente</span>
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
                <h2 class="text-xl font-bold text-gray-900">MANUTENÇÕES POR CLIENTE</h2>
                <p class="text-sm font-mono font-black text-amber-600 mt-0.5">{{ $clientName ?? '—' }}</p>
            </div>
        </div>

        <div class="mb-4 text-xs font-semibold text-gray-500 uppercase">{{ $rows->count() }} manutenções listadas</div>

        <table class="w-full text-xs border-collapse">
            <thead>
                <tr class="text-left uppercase tracking-wide text-gray-500 border-b border-gray-300">
                    <th class="py-2 pr-3">Status</th>
                    <th class="py-2 pr-3">Equipamento</th>
                    <th class="py-2 pr-3">Patrimônio</th>
                    <th class="py-2 pr-3">Grupo</th>
                    <th class="py-2 pr-3">Plano</th>
                    <th class="py-2 pr-3">Técnico</th>
                    <th class="py-2 pr-3">OS</th>
                    <th class="py-2 pr-3">Aberta em</th>
                    <th class="py-2 pr-3">Próximas datas previstas</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    @php $meta = $categoryMeta[$row['category']]; @endphp
                    <tr class="border-b border-gray-100 last:border-0">
                        <td class="py-2 pr-3">
                            <span class="inline-block px-2 py-0.5 rounded font-semibold {{ $meta['color'] }}">{{ $meta['label'] }}</span>
                        </td>
                        <td class="py-2 pr-3 font-medium">{{ $row['asset']->name }}</td>
                        <td class="py-2 pr-3 font-mono text-gray-600">{{ $row['asset']->patrimonio ?? '—' }}</td>
                        <td class="py-2 pr-3 text-gray-600">{{ $row['asset']->checklistGroup?->name ?? '—' }}</td>
                        <td class="py-2 pr-3 text-gray-600">{{ $row['plan']->name }}</td>
                        <td class="py-2 pr-3 text-gray-600">{{ $row['order']?->technician?->name ?? 'Sem técnico' }}</td>
                        <td class="py-2 pr-3 text-gray-600 font-mono">{{ $row['order']?->os_number ?? '—' }}</td>
                        <td class="py-2 pr-3 text-gray-600">{{ $row['order']?->created_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="py-2 pr-3 text-gray-600">
                            @forelse($row['projections'] as $projection)
                                <span class="inline-block mr-2">{{ $projection['month_label'] }} ({{ $projection['reason'] }})</span>
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-6 text-center text-gray-400">Nenhum resultado para os filtros selecionados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

    </div>
</body>
</html>
