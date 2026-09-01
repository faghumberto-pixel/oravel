<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preventiva OS #{{ $order->os_number }} - ORAVEL</title>
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

    <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm no-print">
        <span class="text-sm text-gray-500 font-medium">➔ Checklist de Manutenção Preventiva</span>
        <div class="flex gap-2">
            <button onclick="window.close()" class="px-4 py-2 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">Fechar</button>
            <button onclick="window.print()" class="px-4 py-2 text-sm font-semibold bg-amber-500 hover:bg-amber-600 text-white rounded-lg shadow-sm transition">Imprimir Agora</button>
        </div>
    </div>

    <div class="max-w-4xl mx-auto bg-white p-8 sm:p-12 rounded-2xl border border-gray-200 shadow-sm relative">

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-gray-200 pb-6 mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-black tracking-tight"><span class="text-amber-500">O</span>ravel ERP</h1>
                <p class="text-xs text-gray-500 font-mono mt-1">Tenant ID: {{ $order->tenant_id }}</p>
            </div>
            <div class="text-left sm:text-right">
                <h2 class="text-xl font-bold text-gray-900">MANUTENÇÃO PREVENTIVA</h2>
                <p class="text-lg font-mono font-black text-amber-600 mt-0.5">OS Nº {{ $order->os_number ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="bg-gray-50 rounded-xl p-4 mb-6 grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs font-medium text-gray-600 border border-gray-100">
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Ativo</span>
                <span class="text-gray-900 font-semibold">{{ $order->asset?->name ?? '—' }}</span>
            </div>
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Grupo</span>
                <span class="text-gray-900 font-semibold">{{ $order->asset?->checklistGroup?->name ?? '—' }}</span>
            </div>
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Patrimônio</span>
                <span class="text-gray-900 font-semibold">{{ $order->asset?->patrimonio ?? '—' }}</span>
            </div>
            <div>
                <span class="block text-[10px] text-gray-400 uppercase">Horímetro Atual</span>
                <span class="text-gray-900 font-semibold">{{ number_format((float) $order->asset?->horimetro_atual, 2, ',', '.') }} h</span>
            </div>
        </div>

        <div class="mb-8">
            <h3 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2 mb-3">Itens do Plano de Preventiva</h3>
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="text-gray-400 uppercase bg-gray-50 border-y border-gray-200/60">
                        <th class="p-2.5">Item</th>
                        <th class="p-2.5">Intervalo</th>
                        <th class="p-2.5">Situação</th>
                        <th class="p-2.5">Horímetro Execução</th>
                        <th class="p-2.5">Data</th>
                        <th class="p-2.5">Técnico</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr class="border-b border-gray-100">
                            <td class="p-2.5 font-medium text-gray-800">
                                {{ $item['plan']->name }}
                                @if($item['plan']->notes)
                                    <span class="block text-[10px] text-amber-600">{{ $item['plan']->notes }}</span>
                                @endif
                            </td>
                            <td class="p-2.5 font-mono text-gray-600">{{ $item['plan']->interval_hours }}h</td>
                            <td class="p-2.5">
                                @if($item['execution'])
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-100 text-emerald-800">Executado</span>
                                @elseif($item['status']['is_overdue'])
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-100 text-rose-800">Vencido</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-gray-100 text-gray-600">Pendente</span>
                                @endif
                            </td>
                            <td class="p-2.5 font-mono text-gray-600">{{ $item['execution']?->horimetro_at_execution ?? '—' }}</td>
                            <td class="p-2.5 text-gray-600">{{ $item['execution']?->created_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="p-2.5 text-gray-600">{{ $item['execution']?->technician?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-12 pt-8 grid grid-cols-2 gap-12 text-center text-xs">
            <div class="border-t border-gray-400 pt-2 text-gray-600">Assinatura do Técnico</div>
            <div class="border-t border-gray-400 pt-2 text-gray-600">Responsável Pátio / Liberação</div>
        </div>

        <div class="mt-16 pt-8 border-t border-gray-200 text-center text-[10px] text-gray-400 font-mono">
            Documento emitido eletronicamente via Central ORAVEL.<br>
            Visualizado em: {{ now()->format('d/m/Y') }} às {{ now()->format('H:i:s') }} (Horário de Brasília)
        </div>
    </div>

</body>
</html>
