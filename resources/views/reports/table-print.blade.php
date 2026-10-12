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
@include('partials.relatorio-cabecalho', ['tituloRelatorio' => $titulo, 'filtrosRelatorio' => $filtros])
    <button onclick="window.print()" class="no-print bg-gray-800 text-white px-4 py-2 rounded mb-4 font-bold uppercase text-xs">
        Imprimir
    </button>

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
@include('partials.relatorio-rodape')
</body>
</html>
