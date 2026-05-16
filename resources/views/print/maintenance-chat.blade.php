<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dossiê OS #{{ $os->os_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print { .no-print { display: none !important; } }
        body { font-family: sans-serif; }
    </style>
</head>
<body class="bg-white p-10">
    <div class="no-print flex justify-end mb-8">
        <button onclick="window.print()" class="bg-primary-600 text-white px-6 py-2 rounded-lg font-bold shadow-md hover:bg-primary-700 transition">
            🖨️ IMPRIMIR AGORA
        </button>
    </div>

    <div class="border-b-4 border-black pb-4 mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-2xl font-black uppercase tracking-tighter">ORAVEL - DOSSIÊ TÉCNICO</h1>
            <p class="text-sm font-bold text-gray-600">Histórico de Comunicação e Evidências</p>
        </div>
        <div class="text-right">
            <span class="text-xl font-black text-primary-600">OS #{{ $os->os_number }}</span>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6 mb-10 bg-gray-50 p-4 rounded-xl border border-gray-200">
        <div>
            <span class="text-[10px] font-black text-gray-400 uppercase">Equipamento</span>
            <p class="text-sm font-bold">{{ $os->asset?->name }}</p>
            <p class="text-[10px]">Pat: {{ $os->asset?->patrimonio ?? 'S/N' }}</p>
        </div>
        <div>
            <span class="text-[10px] font-black text-gray-400 uppercase">Técnico Responsável</span>
            <p class="text-sm font-bold">{{ $os->technician?->name ?? 'N/A' }}</p>
        </div>
        <div>
            <span class="text-[10px] font-black text-gray-400 uppercase">Extraído em</span>
            <p class="text-sm font-bold">{{ now()->format('d/m/Y H:i') }}</p>
        </div>
    </div>

    <table class="w-full border-collapse">
        <thead>
            <tr class="bg-gray-900 text-white text-[10px] uppercase tracking-widest text-left">
                <th class="p-3 border border-gray-900">Data/Hora</th>
                <th class="p-3 border border-gray-900">Usuário</th>
                <th class="p-3 border border-gray-900">Mensagem / Evidência</th>
            </tr>
        </thead>
        <tbody class="text-xs">
            @php $messages = $os->chatRoom?->messages->sortBy('created_at') ?? collect(); @endphp
            @forelse($messages as $msg)
                <tr>
                    <td class="p-3 border border-gray-200 whitespace-nowrap">{{ $msg->created_at->format('d/m/Y H:i') }}</td>
                    <td class="p-3 border border-gray-200 font-bold uppercase">{{ $msg->user->name }}</td>
                    <td class="p-3 border border-gray-200 leading-relaxed">
                        {{ $msg->message }}
                        @if($msg->hasMedia('chat_attachments'))
                            <div class="flex gap-2 mt-2">
                                @foreach($msg->getMedia('chat_attachments') as $media)
                                    <img src="{{ $media->getPath() }}" class="h-24 w-auto border rounded shadow-sm grayscale print:grayscale">
                                @endforeach
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="p-10 text-center text-gray-400">Nenhuma conversa registrada.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
