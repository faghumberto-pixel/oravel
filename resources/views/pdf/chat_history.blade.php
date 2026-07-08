<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico de Conversa - Oravel</title>
    <style>
        body { font-family: sans-serif; color: #333; margin: 30px; font-size: 12px; }
        .header { border-bottom: 2px solid #E8541A; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; color: #111; }
        .header p { margin: 4px 0 0 0; color: #666; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background-color: #f3f4f6; text-align: left; padding: 8px; font-size: 10px; text-transform: uppercase; border-bottom: 1px solid #ddd; }
        td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        .timestamp { font-family: monospace; color: #555; white-space: nowrap; }
        .author { font-weight: bold; }
        .attachment { color: #E8541A; font-style: italic; }
        .transcript { color: #666; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Histórico de Conversa</h1>
        <p>Oravel Chat Interno &middot; Conversa com {{ $otherUser?->name ?? 'Contato' }} &middot; Emitido em {{ $generatedAt }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Data / Hora</th>
                <th style="width: 20%;">Remetente</th>
                <th style="width: 65%;">Conteúdo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($messages as $message)
                <tr>
                    <td class="timestamp">{{ $message->created_at->format('d/m/Y H:i') }}</td>
                    <td class="author">{{ $message->user?->name ?? 'Usuário removido' }}</td>
                    <td>
                        @if($message->message)
                            <div style="white-space: pre-wrap;">{{ $message->message }}</div>
                        @endif

                        @foreach($message->getMedia('chat_attachments') as $media)
                            @if(str_starts_with($media->mime_type, 'image/'))
                                <div class="attachment">📷 Imagem: {{ $media->file_name }}</div>
                            @elseif(str_starts_with($media->mime_type, 'audio/'))
                                <div class="attachment">🎤 Áudio ({{ $media->human_readable_size }})</div>
                                @if($message->transcript)
                                    <div class="transcript">"{{ $message->transcript }}"</div>
                                @endif
                            @else
                                <div class="attachment">📎 Documento: {{ $media->file_name }} ({{ $media->human_readable_size }})</div>
                            @endif
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center; color: #999;">Nenhuma mensagem nesta conversa.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
