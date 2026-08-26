<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Contrato - Oravel</title>
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
        .contract-tag { font-family: 'Courier', monospace; font-size: 13px; color: #E8541A; font-weight: bold; }

        .section { margin-bottom: 20px; clear: both; }
        .section-title { background: #f9fafb; padding: 6px 12px; font-weight: bold; border-left: 4px solid #E8541A; color: #374151; text-transform: uppercase; font-size: 10px; margin-bottom: 12px; }

        table.data-grid { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-grid td { padding: 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .label { font-weight: bold; color: #4b5563; font-size: 9px; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-size: 11px; color: #111827; font-weight: 500; }

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
                    <div class="title">Contrato de Locação</div>
                    <div class="contract-tag">{{ $contract->contract_number }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Partes</div>
        <table class="data-grid">
            <tr>
                <td style="width: 50%;">
                    <span class="label">Cliente</span>
                    <span class="value">{{ $contract->client->name ?? '—' }}</span>
                </td>
                <td style="width: 50%;">
                    <span class="label">Documento</span>
                    <span class="value">{{ $contract->client->cpf_cnpj ?? '—' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Objeto e Prazos</div>
        <table class="data-grid">
            <tr>
                <td style="width: 50%;">
                    <span class="label">Equipamento</span>
                    <span class="value">{{ $contract->asset->name ?? '—' }}</span>
                </td>
                <td style="width: 50%;">
                    <span class="label">Status</span>
                    <span class="value">{{ $contract->status ?? '—' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Início da Vigência</span>
                    <span class="value">{{ $contract->start_date?->format('d/m/Y') ?? '—' }}</span>
                </td>
                <td>
                    <span class="label">Fim da Vigência</span>
                    <span class="value">{{ $contract->end_date?->format('d/m/Y') ?? '—' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Local de Instalação</div>
        <table class="data-grid">
            <tr>
                <td>
                    <span class="label">Endereço</span>
                    <span class="value">{{ $contract->resolvedLocation()['label'] ?? 'Não informado' }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Valores</div>
        <table class="data-grid">
            <tr>
                <td>
                    <span class="label">Modalidade de Cobrança</span>
                    <span class="value">{{ \App\Models\Contract::billingTypeOptions()[$contract->billing_type] ?? $contract->billing_type ?? '—' }}</span>
                </td>
                <td>
                    <span class="label">Valor</span>
                    <span class="value">R$ {{ number_format((float) $contract->price, 2, ',', '.') }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Gerado em {{ $generatedAt }} pelo sistema Oravel.
    </div>

</body>
</html>
