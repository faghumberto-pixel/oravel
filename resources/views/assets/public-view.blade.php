<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prontuário: {{ $asset->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden p-6">
        <h1 class="text-2xl font-bold text-gray-800">{{ $asset->name }}</h1>
        <p class="text-gray-600 mb-4">Código: {{ $asset->code ?? 'N/A' }}</p>
        
        <div class="border-t pt-4">
            <h2 class="font-semibold text-blue-600">Últimas Manutenções</h2>
            <ul class="mt-2 space-y-2">
                @forelse($asset->maintenanceOrders as $order)
                    <li class="bg-gray-50 p-2 rounded text-sm">
                        {{ $order->created_at->format('d/m/Y') }} - {{ $order->status }}
                    </li>
                @empty
                    <li class="text-gray-400 text-sm">Nenhuma manutenção registrada.</li>
                @endforelse
            </ul>
        </div>
    </div>
</body>
</html>
