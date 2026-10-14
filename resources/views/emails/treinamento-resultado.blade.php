<!doctype html>
<html lang="pt-BR">
<body style="font-family: Arial, Helvetica, sans-serif; color:#1f2937; line-height:1.5;">
    <h2 style="color:#1A3A52;">Resultado do teste: {{ $titulo }}</h2>
    <table cellpadding="6" style="border-collapse:collapse;">
        <tr><td><strong>Nome</strong></td><td>{{ $resultado->nome }}</td></tr>
        <tr><td><strong>Função</strong></td><td>{{ $resultado->funcao }}</td></tr>
        <tr><td><strong>Data e hora</strong></td><td>{{ $quando }} (Brasília)</td></tr>
        <tr><td><strong>Nota</strong></td><td>{{ number_format((float) $resultado->nota, 1, ',', '') }}</td></tr>
        <tr><td><strong>Acertos</strong></td><td>{{ $resultado->acertos }} de {{ $resultado->total }}</td></tr>
        <tr><td><strong>Situação</strong></td><td>{{ $resultado->aprovado ? 'Aprovado' : 'Precisa refazer' }}</td></tr>
    </table>
    <h3 style="color:#1A3A52;">Respostas</h3>
    <table cellpadding="6" style="border-collapse:collapse; width:100%;">
        <thead>
            <tr style="text-align:left; background:#f3f4f6;"><th>#</th><th>Alternativa escolhida</th><th>Resultado</th></tr>
        </thead>
        <tbody>
        @foreach ($linhas as $linha)
            <tr style="border-top:1px solid #e5e7eb;">
                <td>{{ $linha['n'] }}</td>
                <td>{{ $linha['escolhida'] ?? '(em branco)' }}</td>
                <td style="color:{{ $linha['certa'] ? '#15803d' : '#b91c1c' }};"><strong>{{ $linha['certa'] ? 'Certa' : 'Errada' }}</strong></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p style="color:#6b7280; font-size:12px;">Enviado pela página pública de treinamento da Oravel.</p>
</body>
</html>
