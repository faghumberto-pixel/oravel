<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Histórico de Transmissões - Oravel</title>
    <style>
        body { font-family: sans-serif; color: #333; margin: 30px; font-size: 13px; }
        .header { border-bottom: 2px solid #d97706; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; color: #111; }
        .header p { margin: 4px 0 0 0; color: #666; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background-color: #f3f4f6; text-align: left; padding: 10px; font-size: 11px; text-transform: uppercase; border-bottom: 1px solid #ddd; }
        td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: top; }
        .timestamp { font-family: monospace; color: #555; }
        .user-badge { font-weight: bold; color: #9a5200; }
        @media print {
            button { display: none; }
            body { margin: 0; }
        }
    </style>
</head>
<body>

    <div class="header">
        <button onclick="window.print()" style="float: right; padding: 6px 12px; background-color: #d97706; border: none; color: white; font-weight: bold; border-radius: 4px; cursor: pointer;">Imprimir Relatório</button>
        <h1>Relatório de Comunicação Interna</h1>
        <p>Sistema de Gestão de Ativos Oravel | Emitido em: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 15%;">Data / Hora</th>
                <th style="width: 25%;">Colaborador</th>
                <th style="width: 60%;">Conteúdo da Transmissão</th>
            </tr>
        </thead>
        <tbody>
            @forelse($messages as $message)
                <tr>
                    <td class="timestamp">{{ $message->created_at->format('d/m/Y H:i') }}</td>
                    <td class="user-badge">{{ $message->user->name ?? 'Usuário Oravel' }}</td>
                    <td style="white-space: pre-wrap;">{{ $message->content }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" style="text-align: center; color: #999;">Nenhum registro encontrado no histórico deste canal.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
