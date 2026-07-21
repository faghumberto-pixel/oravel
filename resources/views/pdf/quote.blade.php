<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Orçamento - Oravel</title>
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

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; color: #ffffff; margin-top: 4px; }
        .status-rascunho { background: #6b7280; }
        .status-enviado { background: #3b82f6; }
        .status-aprovado { background: #059669; }
        .status-reprovado { background: #dc2626; }
        .status-concluido { background: #7c3aed; }

        .section { margin-bottom: 20px; clear: both; }
        .section-title { background: #f9fafb; padding: 6px 12px; font-weight: bold; border-left: 4px solid #E8541A; color: #374151; text-transform: uppercase; font-size: 10px; margin-bottom: 12px; }

        table.data-grid { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-grid td { padding: 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .label { font-weight: bold; color: #4b5563; font-size: 9px; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-size: 11px; color: #111827; font-weight: 500; }

        .description-box { padding: 10px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; font-size: 11px; white-space: pre-line; }

        table.items-table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .items-table th, .items-table td { border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 10px; text-align: left; }
        .items-table th { background: #f9fafb; text-transform: uppercase; color: #4b5563; font-size: 9px; }
        .items-table td.numeric { text-align: right; }
        .items-table tfoot td { font-weight: bold; background: #f9fafb; }

        .total-row { text-align: right; font-size: 14px; font-weight: bold; color: #111827; padding-top: 10px; }
        .total-row .amount { color: #E8541A; }

        .rejection-box { padding: 10px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 4px; font-size: 11px; color: #991b1b; }

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
                    <div class="title">Orçamento{{ $quote->type !== \App\Models\Quote::TYPE_INTERNO ? ' — '.\App\Models\Quote::typeLabels()[$quote->type] : '' }}</div>
                    <div class="quote-tag">#{{ substr($quote->id, 0, 8) }}</div>
                    <span class="status-badge status-{{ $quote->status }}">{{ \App\Models\Quote::statusLabels()[$quote->status] ?? $quote->status }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Cliente</div>
        <table class="data-grid">
            <tr>
                <td style="width: 50%;">
                    <span class="label">Nome / Razão Social</span>
                    <span class="value">{{ $quote->client->name }}</span>
                </td>
                <td style="width: 50%;">
                    <span class="label">Documento</span>
                    <span class="value">{{ $quote->client->cpf_cnpj ?? '—' }}</span>
                </td>
            </tr>
        </table>
    </div>

    @if($quote->type === \App\Models\Quote::TYPE_TERCEIRO && $quote->technical_report)
        <div class="section">
            <div class="section-title">Laudo Técnico Prévio</div>
            <div class="description-box">{{ $quote->technical_report }}</div>
        </div>
    @endif

    @if($quote->thirdPartySupplier)
        <div class="section">
            <div class="section-title">Responsável Externo (Terceiro)</div>
            <table class="data-grid">
                <tr>
                    <td>
                        <span class="label">Fornecedor</span>
                        <span class="value">{{ $quote->thirdPartySupplier->name }}</span>
                    </td>
                </tr>
            </table>
        </div>
    @endif

    <div class="section">
        <div class="section-title">Itens do Orçamento</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 10%;">Tipo</th>
                    <th style="width: 45%;">Descrição</th>
                    <th style="width: 10%;" class="numeric">Qtd.</th>
                    <th style="width: 15%;" class="numeric">Valor Unit.</th>
                    <th style="width: 20%;" class="numeric">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $item)
                    <tr>
                        <td>{{ \App\Models\QuoteItem::typeLabels()[$item->type] ?? $item->type }}</td>
                        <td>{{ $item->description }}</td>
                        <td class="numeric">{{ number_format($item->quantity, 2, ',', '.') }}</td>
                        <td class="numeric">R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                        <td class="numeric">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="total-row">
            Total: <span class="amount">R$ {{ number_format($quote->total_value, 2, ',', '.') }}</span>
        </div>
    </div>

    @if($quote->status === \App\Models\Quote::STATUS_REPROVADO && $quote->rejection_reason)
        <div class="section">
            <div class="section-title">Motivo da Reprovação</div>
            <div class="rejection-box">{{ $quote->rejection_reason }}</div>
        </div>
    @endif

    <div class="footer">
        Gerado em {{ $generatedAt }} pelo sistema Oravel.
    </div>

</body>
</html>
