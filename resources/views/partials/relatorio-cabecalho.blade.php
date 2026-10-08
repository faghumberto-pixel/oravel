<div style="border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 14px; font-family: 'Helvetica', 'Arial', sans-serif;">
    <div style="font-size: 18px; font-weight: bold; color: #111827;">{{ \App\Support\Relatorio::emissor() }}</div>
    @if(\App\Support\Relatorio::detalhe())
        <div style="font-size: 10px; color: #6b7280;">{{ \App\Support\Relatorio::detalhe() }}</div>
    @endif
</div>
