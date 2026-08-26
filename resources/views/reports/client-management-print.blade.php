<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico do Cliente — {{ $client->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 20px; }
        }
    </style>
</head>
<body class="bg-white text-black p-8 font-sans">
    <button onclick="window.print()" class="no-print bg-gray-800 text-white px-4 py-2 rounded mb-4 font-bold uppercase text-xs">Imprimir</button>

    <h1 class="text-2xl font-black uppercase mb-2">{{ $client->name }}</h1>
    <p class="text-gray-600 mb-8 italic">Gerado em {{ $generatedAt }}</p>

    <section class="mb-8">
        <h2 class="text-lg font-bold uppercase border-b-2 border-black mb-4">Solicitações Pendentes</h2>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="py-2">Tipo</th>
                    <th class="py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($solicitacoes as $s)
                    <tr class="border-b"><td class="py-2">Solicitação de Equipamento</td><td class="py-2">{{ $s->status_comercial }}</td></tr>
                @endforeach
                @foreach ($ordens as $o)
                    <tr class="border-b"><td class="py-2">OS {{ $o->os_number }}</td><td class="py-2">{{ $o->status }}</td></tr>
                @endforeach
                @foreach ($retiradas as $r)
                    <tr class="border-b"><td class="py-2">Solicitação de Retirada</td><td class="py-2">{{ $r->status }}</td></tr>
                @endforeach
                @if ($solicitacoes->isEmpty() && $ordens->isEmpty() && $retiradas->isEmpty())
                    <tr><td colspan="2" class="py-2 text-gray-400">Nenhuma pendência.</td></tr>
                @endif
            </tbody>
        </table>
    </section>

    <section class="mb-8">
        <h2 class="text-lg font-bold uppercase border-b-2 border-black mb-4">Mensagens</h2>
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="py-2">Data/Hora</th>
                    <th class="py-2">De</th>
                    <th class="py-2">Mensagem</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($messages as $message)
                    <tr class="border-b">
                        <td class="py-2 font-mono">{{ $message->created_at->format('d/m/Y H:i') }}</td>
                        <td class="py-2">{{ $message->senderName() }}</td>
                        <td class="py-2">{{ $message->body ?? '(anexo)' }}</td>
                    </tr>
                @endforeach
                @if ($messages->isEmpty())
                    <tr><td colspan="3" class="py-2 text-gray-400">Nenhuma mensagem.</td></tr>
                @endif
            </tbody>
        </table>
    </section>
</body>
</html>
