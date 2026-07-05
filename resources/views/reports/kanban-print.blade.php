<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Kanban do Pátio - Impressão</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            section { break-inside: avoid; }
        }
    </style>
</head>
<body class="bg-white text-black p-8 font-sans">
    <button onclick="window.print()" class="no-print bg-gray-800 text-white px-4 py-2 rounded mb-4 font-bold uppercase text-xs">
        Imprimir
    </button>

    <h1 class="text-2xl font-black uppercase mb-1">Oficina - Kanban Pátio</h1>
    <p class="text-gray-600 mb-1 italic">Gerado em {{ $generatedAt->format('d/m/Y H:i') }}</p>

    @if($technicianName || !empty(request()->query('hidden')))
        <p class="text-gray-700 text-sm mb-4">
            Filtros ativos:
            @if($technicianName)
                <span class="font-semibold">Técnico: {{ $technicianName }}</span>
            @endif
            @if($technicianName && !empty(request()->query('hidden')))
                &middot;
            @endif
            @if(!empty(request()->query('hidden')))
                <span class="font-semibold">{{ count(explode(',', request()->query('hidden'))) }} coluna(s) oculta(s)</span>
            @endif
        </p>
    @else
        <p class="text-gray-500 text-sm mb-4">Sem filtros ativos - exibindo todo o quadro.</p>
    @endif

    @forelse($visibleStatuses as $statusId => $statusData)
        @php $orders = $groupedOrders->get($statusId, collect()); @endphp
        <section class="mb-8">
            <h2 class="text-lg font-bold uppercase border-b-2 border-black mb-3 flex items-center justify-between">
                <span>{{ $statusData['title'] }}</span>
                <span class="text-sm font-normal">{{ $orders->count() }} OS</span>
            </h2>

            @if($orders->isEmpty())
                <p class="text-gray-500 italic text-sm">Nenhuma OS nesta etapa.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="py-2">OS</th>
                            <th class="py-2">Equipamento</th>
                            <th class="py-2">Técnico / Cliente</th>
                            <th class="py-2">Patrimônio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orders as $order)
                            <tr class="border-b">
                                <td class="py-2 font-mono">{{ $order->os_number }}</td>
                                <td class="py-2">{{ $order->asset?->name ?? 'N/A' }}</td>
                                <td class="py-2">{{ $order->technician?->name ?? $order->client?->name ?? '--' }}</td>
                                <td class="py-2">{{ $order->asset?->patrimonio ?? '---' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @empty
        <p class="text-gray-500 italic">Nenhuma coluna visível para exibir.</p>
    @endforelse
</body>
</html>
