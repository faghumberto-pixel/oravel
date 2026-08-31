<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Propostas Comerciais - Oravel</title>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; margin: 0; padding: 20px; }
        .proposta-page { page-break-after: always; }
        .proposta-page:last-child { page-break-after: auto; }
        .header { border-bottom: 2px solid #E8541A; padding-bottom: 15px; margin-bottom: 25px; }
        .header table { width: 100%; border: none; }
        .logo-area { width: 60%; }
        .logo-text { font-size: 26px; font-weight: 800; color: #111827; letter-spacing: -1px; }
        .logo-text .accent { color: #E8541A; }
        .logo-subtext { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .title-area { width: 40%; text-align: right; }
        .title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #111827; }
        .quote-tag { font-family: 'Courier', monospace; font-size: 13px; color: #E8541A; font-weight: bold; }
        .section { margin-bottom: 20px; clear: both; }
        .section-title { background: #f9fafb; padding: 6px 12px; font-weight: bold; border-left: 4px solid #E8541A; color: #374151; text-transform: uppercase; font-size: 10px; margin-bottom: 12px; }
        table.data-grid { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-grid td { padding: 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .label { font-weight: bold; color: #4b5563; font-size: 9px; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-size: 11px; color: #111827; font-weight: 500; }
        table.items-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .items-table th, .items-table td { border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 10px; text-align: left; }
        .items-table th { background: #f9fafb; text-transform: uppercase; color: #4b5563; font-size: 9px; }
        .items-table td.numeric { text-align: right; }
        .total-row { text-align: right; font-size: 14px; font-weight: bold; color: #111827; padding-top: 10px; }
        .total-row .amount { color: #E8541A; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 16px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #E8541A; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Imprimir</button>
    </div>

    @forelse($propostas as $proposta)
        <div class="proposta-page">
            @include('proposta-comercial._conteudo', ['proposta' => $proposta])
        </div>
    @empty
        <p>Nenhuma proposta encontrada para os filtros selecionados.</p>
    @endforelse
</body>
</html>
