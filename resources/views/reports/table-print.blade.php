<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 20px; }
        }
    </style>
</head>
<body class="bg-white text-black p-8 font-sans">
    <button onclick="window.print()" class="no-print bg-gray-800 text-white px-4 py-2 rounded mb-4 font-bold uppercase text-xs">
        Imprimir
    </button>

    <div class="flex items-start justify-between border-b-4 border-black pb-3 mb-4">
        <div>
            <div class="text-xl font-black tracking-tight">O<span class="text-orange-500">r</span>avel</div>
            @if ($tenantName)
                <div class="text-sm font-semibold text-gray-700">{{ $tenantName }}</div>
            @endif
        </div>
        <div class="text-right text-xs text-gray-600">
            <div>{{ $generatedAt->format('d/m/Y') }}</div>
            <div>{{ $generatedAt->format('H:i') }}</div>
        </div>
    </div>

    <h1 class="text-2xl font-black uppercase mb-2">{{ $titulo }}</h1>

    @if (count($filtros))
        <p class="text-sm text-gray-700 mb-6">
            <span class="font-bold uppercase">Filtros aplicados:</span>
            {{ implode(' · ', $filtros) }}
        </p>
    @else
        <p class="text-sm text-gray-500 mb-6 italic">Sem filtros aplicados — todos os registros visíveis.</p>
    @endif

    <table class="w-full text-left text-sm border-collapse">
        <thead>
            <tr class="border-b-2 border-black">
                @foreach ($columns as $column)
                    <th class="py-2 pr-4">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr class="border-b">
                    @foreach ($columns as $column)
                        <td class="py-2 pr-4">{{ $column['value']($record) ?? '—' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" class="py-4 text-center text-gray-500 italic">
                        Nenhum registro encontrado com os filtros atuais.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="text-xs text-gray-400 mt-6">Total de registros: {{ count($records) }}</p>
</body>
</html>
