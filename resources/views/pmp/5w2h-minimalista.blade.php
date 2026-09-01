<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $data['title'] }} - 5W2H</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; color: black; padding: 0; margin: 0; }
            @page { margin: 1.2cm; }
            .print-header { display: none !important; }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased font-sans p-4 sm:p-8">

    <div class="print-header max-w-4xl mx-auto mb-6 flex justify-between items-center bg-white p-4 rounded-xl border border-gray-200 shadow-sm no-print">
        <span class="text-sm text-gray-500 font-medium">
            <strong>→ Visualização PHP Minimalista</strong> — {{ $data['title'] }}
        </span>
        <div class="flex gap-2">
            <button onclick="window.history.back()" class="px-4 py-2 text-sm font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition">Fechar</button>
            <button onclick="window.print()" class="px-4 py-2 text-sm font-semibold bg-amber-500 hover:bg-amber-600 text-white rounded-lg shadow-sm transition">Imprimir Agora</button>
        </div>
    </div>

    <div class="max-w-4xl mx-auto bg-white p-8 sm:p-12 rounded-2xl border border-gray-200 shadow-sm">

        <!-- Cabeçalho -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-gray-200 pb-6 mb-8 gap-4">
            <div class="flex items-start gap-4">
                <div class="text-4xl">{{ $data['icon'] }}</div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-amber-500">{{ $data['title'] }}</h1>
                    <p class="text-sm text-gray-500 mt-1">{{ $data['subtitle'] }}</p>
                </div>
            </div>
            <div class="text-right text-xs text-gray-400">
                {{ now()->format('d/m/Y H:i:s') }}
            </div>
        </div>

        <!-- Grid 5W2H -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- What -->
            <div class="border border-gray-200 rounded-lg p-6 bg-gradient-to-br from-blue-50 to-transparent">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">❓</span>
                    <h2 class="text-lg font-bold text-gray-900">O Quê?</h2>
                </div>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $data['what'] }}</p>
            </div>

            <!-- Why -->
            <div class="border border-gray-200 rounded-lg p-6 bg-gradient-to-br from-green-50 to-transparent">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">🎯</span>
                    <h2 class="text-lg font-bold text-gray-900">Por Quê?</h2>
                </div>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $data['why'] }}</p>
            </div>

            <!-- When -->
            <div class="border border-gray-200 rounded-lg p-6 bg-gradient-to-br from-purple-50 to-transparent">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">⏰</span>
                    <h2 class="text-lg font-bold text-gray-900">Quando?</h2>
                </div>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $data['when'] }}</p>
            </div>

            <!-- Where -->
            <div class="border border-gray-200 rounded-lg p-6 bg-gradient-to-br from-orange-50 to-transparent">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">📍</span>
                    <h2 class="text-lg font-bold text-gray-900">Onde?</h2>
                </div>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $data['where'] }}</p>
            </div>

            <!-- Who -->
            <div class="border border-gray-200 rounded-lg p-6 bg-gradient-to-br from-red-50 to-transparent">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">👤</span>
                    <h2 class="text-lg font-bold text-gray-900">Quem?</h2>
                </div>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $data['who'] }}</p>
            </div>

            <!-- How -->
            <div class="border border-gray-200 rounded-lg p-6 bg-gradient-to-br from-cyan-50 to-transparent">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">⚙️</span>
                    <h2 class="text-lg font-bold text-gray-900">Como?</h2>
                </div>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $data['how'] }}</p>
            </div>

            <!-- How Much -->
            <div class="sm:col-span-2 border border-gray-200 rounded-lg p-6 bg-gradient-to-br from-yellow-50 to-transparent">
                <div class="flex items-center gap-2 mb-4">
                    <span class="text-2xl">💰</span>
                    <h2 class="text-lg font-bold text-gray-900">Quanto?</h2>
                </div>
                <p class="text-sm text-gray-700 leading-relaxed">{{ $data['howmuch'] }}</p>
            </div>
        </div>

        <!-- Rodapé -->
        <div class="mt-8 pt-6 border-t border-gray-200 text-center text-xs text-gray-400">
            <p>Oravel ERP — Módulo de Planejamento de Manutenção Preventiva (PMP)</p>
        </div>

    </div>

</body>
</html>
