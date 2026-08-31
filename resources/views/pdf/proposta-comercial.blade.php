<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Proposta Comercial - Oravel</title>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; margin: 0; padding: 0; }
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
        .items-table tfoot td { font-weight: bold; background: #f9fafb; }
        .total-row { text-align: right; font-size: 14px; font-weight: bold; color: #111827; padding-top: 10px; }
        .total-row .amount { color: #E8541A; }
        .footer { position: fixed; bottom: -10px; width: 100%; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td class="logo-area">
                    <div class="logo-text">O<span class="accent">r</span>avel</div>
                    <div class="logo-subtext">Asset Intelligence &amp; Maintenance Systems</div>
                </td>
                <td class="title-area">
                    <div class="title">Proposta Comercial</div>
                    <div class="quote-tag">#{{ $proposta->id }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Cliente</div>
        <table class="data-grid">
            <tr>
                <td>
                    <span class="label">Nome</span>
                    <span class="value">{{ $proposta->client?->name }}</span>
                </td>
                <td>
                    <span class="label">Vendedor</span>
                    <span class="value">{{ $proposta->sellerUser?->name ?? '—' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Itens</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th class="numeric">Qtd.</th>
                    <th class="numeric">Valor Unit.</th>
                    <th class="numeric">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($proposta->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="numeric">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                        <td class="numeric">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                        <td class="numeric">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="total-row">Total: <span class="amount">R$ {{ number_format($proposta->total_value, 2, ',', '.') }}</span></div>
    </div>

    @if($proposta->terms)
        <div class="section">
            <div class="section-title">Termos</div>
            <p>{{ $proposta->terms }}</p>
        </div>
    @endif

    <div class="footer">Gerado em {{ $generatedAt }} — Oravel Sistemas</div>
</body>
</html>
