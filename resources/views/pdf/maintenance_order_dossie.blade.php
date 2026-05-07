<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dossiê de Auditoria Patrimonial - Oravel</title>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; margin: 0; padding: 0; }
        
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 15px; margin-bottom: 25px; }
        .header table { width: 100%; border: none; }
        .logo-area { width: 60%; }
        .logo-text { font-size: 26px; font-weight: 800; color: #2563eb; letter-spacing: -1px; }
        .logo-subtext { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .title-area { width: 40%; text-align: right; }
        .title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #111827; }
        .os-tag { font-family: 'Courier', monospace; font-size: 16px; color: #2563eb; font-weight: bold; }

        .section { margin-bottom: 25px; clear: both; }
        .section-title { background: #f9fafb; padding: 6px 12px; font-weight: bold; border-left: 4px solid #2563eb; color: #374151; text-transform: uppercase; font-size: 10px; margin-bottom: 12px; }
        
        table.data-grid { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-grid td { padding: 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .label { font-weight: bold; color: #4b5563; font-size: 9px; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-size: 11px; color: #111827; font-weight: 500; }

        .evidence-container { margin-top: 10px; }
        .evidence-card { page-break-inside: avoid; border: 1px solid #e5e7eb; border-radius: 4px; padding: 12px; margin-bottom: 20px; background: #ffffff; }
        .evidence-img { width: 100%; max-height: 380px; object-fit: contain; border-radius: 2px; margin-bottom: 10px; display: block; }
        .evidence-meta-table { width: 100%; background: #f3f4f6; padding: 8px; border-radius: 4px; }
        .evidence-meta-table td { border: none !important; padding: 2px 5px !important; font-size: 9px !important; }
        .meta-icon { color: #2563eb; font-weight: bold; }

        .signatures-area { margin-top: 40px; page-break-inside: avoid; }
        .signature-table { width: 100%; border: none; }
        .signature-box { width: 48%; text-align: center; vertical-align: bottom; }
        .signature-line { border-top: 1px solid #9ca3af; margin: 0 10px; padding-top: 8px; }
        .signature-image { max-width: 280px; max-height: 140px; margin-bottom: -15px; filter: contrast(150%); }
        .signature-placeholder { height: 80px; }

        .footer { position: fixed; bottom: -10px; width: 100%; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 10px; }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td class="logo-area">
                    <div class="logo-text">ORAVEL</div>
                    <div class="logo-subtext">Asset Intelligence & Maintenance Systems</div>
                </td>
                <td class="title-area">
                    <div class="title">
                        @if($order->maintenance_type === 'Check-out') LAUDO TÉCNICO DE ENTREGA @else LAUDO TÉCNICO DE RECOLHIMENTO @endif
                    </div>
                    <div class="os-tag">OS #{{ $order->os_number }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Informações do Equipamento e Custódia</div>
        <table class="data-grid">
            <tr>
                <td style="width: 50%;">
                    <span class="label">Equipamento / Ativo</span>
                    <span class="value">{{ $order->asset->name }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Nº Patrimônio</span>
                    <span class="value">{{ $order->asset->patrimonio }}</span>
                </td>
                <td style="width: 25%;">
                    <span class="label">Placa/Série</span>
                    <span class="value">{{ $order->asset->code ?? 'N/A' }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Cliente (Destino/Origem)</span>
                    <span class="value">{{ $order->client->name ?? 'CONSUMIDOR FINAL' }}</span>
                </td>
                <td>
                    <span class="label">Tipo de Serviço</span>
                    <span class="value">{{ $order->maintenance_type }}</span>
                </td>
                <td>
                    <span class="label">Data de Fechamento</span>
                    <span class="value">{{ $order->finished_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Dossiê Fotográfico e Rastreabilidade Satélite</div>
        <div class="evidence-container">
            @forelse($order->evidences as $evidence)
                <div class="evidence-card">
                    @if($evidence->file_path)
                        <img src="{{ public_path('storage/' . $evidence->file_path) }}" class="evidence-img">
                    @endif
                    <table class="evidence-meta-table">
                        <tr>
                            <td style="width: 15%;"><span class="meta-icon">📍 LOCAL:</span></td>
                            <td>{{ $evidence->address ?? 'Coordenadas registradas no ato' }}</td>
                        </tr>
                        <tr>
                            <td><span class="meta-icon">🌍 GPS:</span></td>
                            <td>Lat: {{ $evidence->latitude }} | Long: {{ $evidence->longitude }}</td>
                        </tr>
                        <tr>
                            <td><span class="meta-icon">⏰ DATA:</span></td>
                            <td>{{ $evidence->captured_at?->format('d/m/Y H:i:s') }} (Timestamp Infalsificável)</td>
                        </tr>
                    </table>
                </div>
            @empty
                <div style="padding: 20px; text-align: center; border: 1px dashed #ccc;">
                    Nenhuma evidência fotográfica registrada para este procedimento.
                </div>
            @endforelse
        </div>
    </div>

    <div class="signatures-area">
        <div class="section-title">Validação e Aceite das Partes</div>
        <table class="signature-table">
            <tr>
                <td class="signature-box">
                    <div class="signature-placeholder"></div>
                    <div class="signature-line"></div>
                    <span class="value">{{ $order->technician->name ?? 'Técnico Responsável' }}</span><br>
                    <span class="label" style="text-align: center;">Vistoriador / Oravel System</span>
                </td>
                <td class="signature-box">
                    <div class="signature-placeholder">
                        @if($order->client_signature)
                            <img src="{{ $order->client_signature }}" class="signature-image">
                        @endif
                    </div>
                    <div class="signature-line"></div>
                    <span class="value">{{ $order->client->name ?? 'Responsável p/ Recebimento' }}</span><br>
                    <span class="label" style="text-align: center;">Cliente / Recebedor</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Este documento é parte integrante do sistema de gestão <strong>Oravel</strong>. 
        Gerado em {{ $generatedAt }}. 
        A autenticidade deste laudo técnico é garantida pela integração de metadados geográficos e assinatura digital.
    </div>

</body>
</html>
