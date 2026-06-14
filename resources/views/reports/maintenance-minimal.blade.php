<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Analítico de Manutenção</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { 
            .no-print { display: none !important; } 
            body { padding: 20px; }
        }
    </style>
</head>
<body class="bg-white text-black p-8 font-sans">
    <button onclick="window.print()" class="no-print bg-gray-800 text-white px-4 py-2 rounded mb-4 font-bold uppercase text-xs">Imprimir Relatório</button>
    
    <h1 class="text-2xl font-black uppercase mb-2">Relatório Analítico de Manutenção</h1>
    <p class="text-gray-600 mb-8 italic">Data de geração: {{ now()->format('d/m/Y H:i') }}</p>

    @foreach($orders as $status => $group)
        <section class="mb-8">
            <h2 class="text-lg font-bold uppercase border-b-2 border-black mb-4">{{ str_replace('_', ' ', $status) }}</h2>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="py-2">OS</th>
                        <th class="py-2">Ativo</th>
                        <th class="py-2">Patrimônio</th>
                        <th class="py-2">Dias na Etapa</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group as $order)
                        <tr class="border-b">
                            <td class="py-2 font-mono">{{ $order->os_number }}</td>
                            <td class="py-2">{{ $order->asset->name ?? 'N/A' }}</td>
                            <td class="py-2">{{ $order->asset->patrimonio ?? 'N/A' }}</td>
                            <td class="py-2">
                                @php
                                    $last = $order->statusHistory()->where('status', $status)->latest('changed_at')->first();
                                    echo $last ? $last->changed_at->diffInDays(now()) : 0;
                                @endphp
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
</body>
</html>
