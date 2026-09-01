<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dossiê OS #{{ $order->os_number }} - ORAVEL</title>
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
        $isCheckout = $order->maintenance_type === \App\Models\MaintenanceOrder::TYPE_CHECKOUT;
        $operationLabel = $isCheckout ? 'Check-out (Desmobilização)' : 'Check-in (Mobilização)';
    @endphp

    <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm no-print">
        <span class="text-sm text-gray-500 font-medium">➔ <strong>Visualização Minimalista</strong> do Dossiê Operacional</span>
        <div class="flex gap-2">
            <button onclick="window.close()" class="px-4 py-2 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">Fechar</button>
            <button onclick="window.print()" class="px-4 py-2 text-sm font-semibold bg-amber-500 hover:bg-amber-600 text-white rounded-lg shadow-sm transition">Imprimir Agora</button>
        </div>
    </div>

    <div class="max-w-4xl mx-auto bg-white p-8 sm:p-12 rounded-2xl border border-gray-200 shadow-sm relative">

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-gray-200 pb-6 mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-black tracking-tight"><span class="text-emerald-600">O</span>ravel ERP</h1>
                <p class="text-xs text-gray-500 font-mono mt-1">Dossiê Operacional de Locação — {{ $operationLabel }}</p>
            </div>
            <div class="text-left sm:text-right">
                <h2 class="text-xl font-bold text-gray-900">OS Nº {{ $order->os_number }}</h2>
                <p class="text-sm text-gray-500 mt-0.5">{{ $order->client?->name ?? 'Cliente não informado' }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs font-medium text-gray-600 border border-gray-100 bg-gray-50 rounded-xl p-4 mb-8">
            <div>
                <span class="block text-[10px] uppercase tracking-widest text-gray-400">Status</span>
                <span class="text-gray-900 font-bold">{{ $order->status }}</span>
            </div>
            <div>
                <span class="block text-[10px] uppercase tracking-widest text-gray-400">Patrimônio</span>
                <span class="text-gray-900 font-bold">{{ $order->asset?->patrimonio ?? 'N/A' }}</span>
            </div>
            <div>
                <span class="block text-[10px] uppercase tracking-widest text-gray-400">Ativo</span>
                <span class="text-gray-900 font-bold">{{ $order->asset?->name ?? 'N/A' }}</span>
            </div>
            <div>
                <span class="block text-[10px] uppercase tracking-widest text-gray-400">Técnico</span>
                <span class="text-gray-900 font-bold">{{ $order->technician?->name ?? 'N/A' }}</span>
            </div>
        </div>

        <h3 class="text-sm font-black uppercase tracking-widest text-gray-500 mb-3">Auditoria Fotográfica</h3>
        @if($order->evidences->isEmpty())
            <p class="text-sm text-gray-400 italic mb-8">Nenhuma evidência fotográfica registrada.</p>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                @foreach($order->evidences as $evidence)
                    @php
                        $isAvaria = $evidence->severity === 'avaria';
                        $categoryLabel = $evidence->category ?: match($evidence->evidence_type) {
                            'antes' => 'Estado Inicial',
                            'depois' => 'Estado Final',
                            default => 'Evidência',
                        };
                    @endphp
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div class="{{ $isAvaria ? 'bg-red-600' : 'bg-emerald-600' }} text-white text-[10px] font-black uppercase px-3 py-1.5">
                            {{ $categoryLabel }}
                        </div>
                        @if($evidence->file_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($evidence->file_path) }}" class="w-full h-40 object-cover">
                        @endif
                        <div class="p-3 text-[10px] font-mono text-gray-500 space-y-1">
                            <p>📍 Lat: {{ $evidence->latitude ?? '—' }}, Long: {{ $evidence->longitude ?? '—' }}</p>
                            <p>⏰ {{ optional($evidence->captured_at)->format('d/m/Y H:i:s') }}</p>
                            @if($evidence->observation)
                                <p class="text-gray-700 pt-1">{{ $evidence->observation }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="border border-gray-200 rounded-xl p-4">
                <span class="block text-[10px] uppercase tracking-widest text-gray-400 mb-1">Diagnóstico Técnico</span>
                <p class="text-sm text-gray-800 whitespace-pre-line">{{ $order->technical_notes ?: 'Nenhum diagnóstico registrado.' }}</p>
            </div>
            <div class="border border-gray-200 rounded-xl p-4 text-center">
                <span class="block text-[10px] uppercase tracking-widest text-gray-400 mb-2">Assinatura do Cliente</span>
                @if($order->client_signature)
                    <img src="{{ $order->client_signature }}" class="h-16 mx-auto object-contain">
                @else
                    <p class="text-xs text-gray-400 italic">Assinatura não coletada</p>
                @endif
            </div>
        </div>

    </div>
</body>
</html>
