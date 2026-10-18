<div style="font-family: Arial, Helvetica, sans-serif; color: #1f2937; max-width: 560px; margin: 0 auto; line-height: 1.6;">
    <p>Olá, {{ $nota->tomador['nome'] ?? 'cliente' }}.</p>
    <p>A <strong>{{ $empresa }}</strong> emitiu a sua nota fiscal de serviço.</p>
    <table cellpadding="6" style="border-collapse: collapse; margin: 12px 0;">
        <tr><td style="color:#6b7280">Número</td><td><strong>{{ $nota->numero }}</strong></td></tr>
        <tr><td style="color:#6b7280">Valor</td><td><strong>R$ {{ number_format((float) $nota->valor, 2, ',', '.') }}</strong></td></tr>
        @if ($nota->codigo_verificacao)<tr><td style="color:#6b7280">Código de verificação</td><td>{{ $nota->codigo_verificacao }}</td></tr>@endif
        @if ($nota->emitida_em)<tr><td style="color:#6b7280">Emitida em</td><td>{{ $nota->emitida_em->format('d/m/Y') }}</td></tr>@endif
    </table>
    @if ($nota->pdf_url)<p><a href="{{ $nota->pdf_url }}" style="background:#ea580c;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:bold">Ver a nota</a></p>@endif
    @if ($nota->xml_url)<p style="font-size:13px"><a href="{{ $nota->xml_url }}">Baixar o XML</a></p>@endif
    <p style="font-size:12px;color:#6b7280">Se tiver qualquer dúvida, responda este e-mail.</p>
</div>
