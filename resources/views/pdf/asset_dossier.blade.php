<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Dossiê do Ativo - {{ $asset->name }}</title>
    <style>
        @page { margin: 1.5cm; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; margin: 0; padding: 0; }

        .header { border-bottom: 2px solid #ea580c; padding-bottom: 15px; margin-bottom: 20px; }
        .header table { width: 100%; border: none; }
        .logo-text { font-size: 26px; font-weight: 800; color: #ea580c; letter-spacing: -1px; }
        .logo-subtext { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; }
        .title-area { width: 40%; text-align: right; }
        .title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #111827; }
        .patrimonio-tag { font-family: 'Courier', monospace; font-size: 16px; color: #ea580c; font-weight: bold; }

        .section { margin-bottom: 18px; clear: both; }
        .section-title { background: #f9fafb; padding: 6px 12px; font-weight: bold; border-left: 4px solid #ea580c; color: #374151; text-transform: uppercase; font-size: 10px; margin-bottom: 10px; }

        table.data-grid { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
        table.data-grid td { padding: 8px; border: 1px solid #e5e7eb; vertical-align: top; }
        .label { font-weight: bold; color: #4b5563; font-size: 9px; text-transform: uppercase; display: block; margin-bottom: 2px; }
        .value { font-size: 11px; color: #111827; font-weight: 500; }

        .list-table { width: 100%; border-collapse: collapse; }
        .list-table th, .list-table td { border: 1px solid #e5e7eb; padding: 6px 8px; font-size: 9px; text-align: left; vertical-align: top; }
        .list-table th { background: #f9fafb; text-transform: uppercase; color: #4b5563; }
        .empty-note { font-size: 10px; color: #9ca3af; font-style: italic; }

        .footer { position: fixed; bottom: -10px; width: 100%; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #f3f4f6; padding-top: 10px; }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td style="width: 60%;">
                    <div class="logo-text">Oravel</div>
                    <div class="logo-subtext">Gestão de Frota e Manutenção</div>
                </td>
                <td class="title-area">
                    <div class="title">Dossiê do Ativo</div>
                    <div class="patrimonio-tag">{{ $asset->patrimonio ?? '—' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Dados Gerais</div>
        <table class="data-grid">
            <tr>
                <td><span class="label">Equipamento</span><span class="value">{{ $asset->name }}</span></td>
                <td><span class="label">Tag</span><span class="value">{{ $asset->tag ?? '—' }}</span></td>
                <td><span class="label">Nº Série</span><span class="value">{{ $asset->serial_number ?? '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Categoria</span><span class="value">{{ $asset->asset_category ?? '—' }}</span></td>
                <td><span class="label">Especificação</span><span class="value">{{ $asset->specification ?? '—' }}</span></td>
                <td><span class="label">Status</span><span class="value">{{ ucfirst($asset->status ?? '—') }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Criticidade</span><span class="value">{{ $asset->criticality_level ?? $asset->criticality ?? '—' }}</span></td>
                <td><span class="label">Cliente Atual</span><span class="value">{{ $asset->client?->name ?? 'Disponível / sem cliente' }}</span></td>
                <td>
                    <span class="label">Matriz ABC</span>
                    <span class="value">
                        @if ($asset->abcMatrix)
                            Nível {{ $asset->abcMatrix->nivel }} — {{ $asset->abcMatrix->descricao }}
                        @else
                            —
                        @endif
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Contrato Vigente</div>
        @if ($currentContract)
            <table class="data-grid">
                <tr>
                    <td><span class="label">Nº Contrato</span><span class="value">{{ $currentContract->contract_number }}</span></td>
                    <td><span class="label">Cliente</span><span class="value">{{ $currentContract->client?->name ?? '—' }}</span></td>
                    <td><span class="label">Início</span><span class="value">{{ optional($currentContract->start_date)->format('d/m/Y') ?? '—' }}</span></td>
                    <td><span class="label">Valor Mensal</span><span class="value">R$ {{ number_format((float) $currentContract->price, 2, ',', '.') }}</span></td>
                </tr>
            </table>
        @else
            <p class="empty-note">Nenhum contrato ativo para este ativo.</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">Horas Trabalhadas</div>
        <table class="data-grid">
            <tr>
                <td><span class="label">Horímetro Atual</span><span class="value">{{ number_format((float) $asset->horimetro_atual, 2, ',', '.') }} h</span></td>
                <td><span class="label">Horímetro Inicial</span><span class="value">{{ number_format((float) $asset->horimetro_inicial, 2, ',', '.') }} h</span></td>
                <td><span class="label">Odômetro Atual</span><span class="value">{{ $asset->is_vehicle ? number_format((float) $asset->odometro_atual, 2, ',', '.').' km' : '—' }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Avarias Recentes</div>
        @if ($asset->damages->isNotEmpty())
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Severidade</th>
                        <th>Descrição</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($asset->damages as $damage)
                        <tr>
                            <td>{{ $damage->created_at->format('d/m/Y H:i') }}</td>
                            <td>{{ ucfirst($damage->severity) }}</td>
                            <td>{{ $damage->description }}</td>
                            <td>{{ $damage->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty-note">Nenhuma avaria registrada.</p>
        @endif
    </div>

    <div class="section">
        <div class="section-title">Ordens de Serviço Abertas</div>
        @if ($asset->maintenanceOrders->isNotEmpty())
            <table class="list-table">
                <thead>
                    <tr>
                        <th>Nº OS</th>
                        <th>Status</th>
                        <th>Técnico</th>
                        <th>Problema Relatado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($asset->maintenanceOrders as $order)
                        <tr>
                            <td>{{ $order->os_number }}</td>
                            <td>{{ $order->status }}</td>
                            <td>{{ $order->technician?->name ?? 'Não atribuído' }}</td>
                            <td>{{ $order->reportedProblem?->description ?? $order->description ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="empty-note">Nenhuma OS em aberto.</p>
        @endif
    </div>

    <div class="footer">
        Gerado em {{ $generatedAt }} · Oravel — Gestão de Frota e Manutenção
    </div>

</body>
</html>
