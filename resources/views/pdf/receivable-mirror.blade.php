<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Espelho de Medição - Oravel</title>
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

        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; color: #ffffff; margin-top: 4px; }
        .status-pendente { background: #d97706; }
        .status-pago { background: #059669; }
        .status-vencido { background: #dc2626; }

        .section { margin-bottom: 20px; clear: both; }
        .section-title { background: #f9fafb; padding: 6px 12px; font-weight: bold; border-left: 4px solid #E8541A; color: #374151; text-transform: uppercase; font-size: 10px; margin-bottom: 12px; }

        table.data-grid { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-grid td { padding: 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .label { font-weight: bold; color: #4b5563; font-size: 9px; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-size: 11px; color: #111827; font-weight: 500; }

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
                    <div class="logo-subtext">Espelho de Medição</div>
                </td>
                <td class="title-area">
                    <div class="title">Medição #{{ str_pad($receivable->id, 6, '0', STR_PAD_LEFT) }}</div>
                    <span class="status-badge status-{{ \Illuminate\Support\Str::slug($receivable->status) }}">
                        {{ $receivable->status }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Dados da Medição</div>
        <table class="data-grid">
            <tr>
                <td>
                    <span class="label">Descrição</span>
                    <span class="value">{{ $receivable->description }}</span>
                </td>
                <td>
                    <span class="label">Referência</span>
                    <span class="value">{{ $receivable->mes ? str_pad($receivable->mes, 2, '0', STR_PAD_LEFT).'/'.$receivable->ano : '—' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Contrato</span>
                    <span class="value">{{ $receivable->contract?->contract_number ?? '—' }}</span>
                </td>
                <td>
                    <span class="label">Equipamento</span>
                    <span class="value">{{ $receivable->contract?->asset?->name ?? '—' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Vencimento</span>
                    <span class="value">{{ $receivable->due_date?->format('d/m/Y') ?? '—' }}</span>
                </td>
                <td>
                    <span class="label">Pagamento</span>
                    <span class="value">{{ $receivable->payment_date?->format('d/m/Y') ?? 'Em aberto' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="total-row">
        Valor Total: <span class="amount">R$ {{ number_format((float) $receivable->amount, 2, ',', '.') }}</span>
    </div>

    <div class="footer">
        Documento gerado em {{ $generatedAt }} — Oravel Gestão de Locadoras
    </div>
</body>
</html>
