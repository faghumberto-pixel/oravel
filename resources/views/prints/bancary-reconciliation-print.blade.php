<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conciliação Bancária - Impressão</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #333;
        }

        .container {
            background: white;
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .header {
            border-bottom: 2px solid #ea580c;
            margin-bottom: 30px;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 28px;
            color: #0b0f0d;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 14px;
            color: #666;
        }

        .filters-info {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .filters-info strong {
            color: #0b0f0d;
        }

        .section {
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            padding: 12px;
            background: #f0f0f0;
            border-left: 4px solid #ea580c;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f9f9f9;
            border-bottom: 2px solid #ddd;
        }

        th {
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: #0b0f0d;
        }

        td {
            padding: 8px 12px;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
        }

        .badge-pago {
            background: #dcfce7;
            color: #166534;
        }

        .badge-pendente {
            background: #fef3c7;
            color: #b45309;
        }

        .badge-atrasado {
            background: #fee2e2;
            color: #991b1b;
        }

        .text-right {
            text-align: right;
        }

        .total-row {
            background: #f0f0f0;
            font-weight: 600;
            border-top: 2px solid #ddd;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #ea580c;
        }

        .summary-card h3 {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }

        .summary-card .amount {
            font-size: 18px;
            font-weight: 700;
            color: #0b0f0d;
        }

        .summary-card .count {
            font-size: 11px;
            color: #999;
            margin-top: 4px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #999;
        }

        .print-button {
            background: #ea580c;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .print-button:hover {
            background: #d14700;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #999;
            font-size: 13px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .container {
                box-shadow: none;
                padding: 0;
            }

            .print-button {
                display: none;
            }

            .page-break {
                page-break-after: always;
            }
        }

        @page {
            margin: 1cm;
        }
    </style>
</head>
<body>
    <div class="container">
        <button class="print-button" onclick="window.print()">Imprimir / PDF</button>

        <div class="header">
            <h1>🏦 Conciliação Bancária</h1>
            <p>Relatório de Sincronismo Asaas</p>
        </div>

        <div class="filters-info">
            <strong>Período:</strong> {{ $dateStart ? \Carbon\Carbon::parse($dateStart)->format('d/m/Y') : 'Indefinido' }}
            até {{ $dateEnd ? \Carbon\Carbon::parse($dateEnd)->format('d/m/Y') : 'Hoje' }}
            @if($syncStatus)
                | <strong>Status:</strong>
                @if($syncStatus === 'automatic') Automático @elseif($syncStatus === 'pending') Pendente @else Manual @endif
            @endif
        </div>

        <div class="summary">
            <div class="summary-card">
                <h3>✓ Baixadas Automaticamente</h3>
                <div class="amount">R$ {{ number_format($summary['automaticallySettledAmount'] ?? 0, 2, ',', '.') }}</div>
                <div class="count">{{ $summary['automaticallySettled'] ?? 0 }} operação(ões)</div>
            </div>
            <div class="summary-card">
                <h3>⏳ Pendentes de Confirmação</h3>
                <div class="amount">R$ {{ number_format($summary['pendingConfirmationAmount'] ?? 0, 2, ',', '.') }}</div>
                <div class="count">{{ $summary['pendingConfirmation'] ?? 0 }} operação(ões)</div>
            </div>
            <div class="summary-card">
                <h3>📋 Cobrança Manual</h3>
                <div class="amount">R$ {{ number_format($summary['manualSettlementAmount'] ?? 0, 2, ',', '.') }}</div>
                <div class="count">{{ $summary['manualSettlement'] ?? 0 }} operação(ões)</div>
            </div>
        </div>

        {{-- Baixadas Automaticamente --}}
        @if ($automaticallySettled->count() > 0)
            <div class="section">
                <div class="section-title">✓ Baixadas Automaticamente</div>
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Cliente</th>
                            <th class="text-right">Valor</th>
                            <th>Vencimento</th>
                            <th>Recebimento</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($automaticallySettled as $record)
                            <tr>
                                <td>{{ $record->description }}</td>
                                <td>{{ $record->client?->name ?? '—' }}</td>
                                <td class="text-right">R$ {{ number_format($record->amount, 2, ',', '.') }}</td>
                                <td>{{ $record->due_date->format('d/m/Y') }}</td>
                                <td>{{ $record->payment_date?->format('d/m/Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2" style="text-align: right;">Subtotal:</td>
                            <td class="text-right">R$ {{ number_format($automaticallySettled->sum('amount'), 2, ',', '.') }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        {{-- Pendentes de Confirmação --}}
        @if ($pendingConfirmation->count() > 0)
            <div class="section">
                <div class="section-title">⏳ Pendentes de Confirmação</div>
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Cliente</th>
                            <th class="text-right">Valor</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingConfirmation as $record)
                            <tr>
                                <td>{{ $record->description }}</td>
                                <td>{{ $record->client?->name ?? '—' }}</td>
                                <td class="text-right">R$ {{ number_format($record->amount, 2, ',', '.') }}</td>
                                <td>{{ $record->due_date->format('d/m/Y') }}</td>
                                <td><span class="badge {{ $record->status === 'pendente' ? 'badge-pendente' : 'badge-atrasado' }}">{{ ucfirst($record->status) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2" style="text-align: right;">Subtotal:</td>
                            <td class="text-right">R$ {{ number_format($pendingConfirmation->sum('amount'), 2, ',', '.') }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        {{-- Cobrança Manual --}}
        @if ($manualSettlement->count() > 0)
            <div class="section">
                <div class="section-title">📋 Cobrança Manual</div>
                <table>
                    <thead>
                        <tr>
                            <th>Descrição</th>
                            <th>Cliente</th>
                            <th class="text-right">Valor</th>
                            <th>Vencimento</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($manualSettlement as $record)
                            <tr>
                                <td>{{ $record->description }}</td>
                                <td>{{ $record->client?->name ?? '—' }}</td>
                                <td class="text-right">R$ {{ number_format($record->amount, 2, ',', '.') }}</td>
                                <td>{{ $record->due_date->format('d/m/Y') }}</td>
                                <td>
                                    <span class="badge {{ match($record->status) {
                                        'pago' => 'badge-pago',
                                        'pendente' => 'badge-pendente',
                                        'atrasado' => 'badge-atrasado',
                                        default => 'badge-pendente',
                                    } }}">{{ ucfirst($record->status) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2" style="text-align: right;">Subtotal:</td>
                            <td class="text-right">R$ {{ number_format($manualSettlement->sum('amount'), 2, ',', '.') }}</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        @if ($automaticallySettled->isEmpty() && $pendingConfirmation->isEmpty() && $manualSettlement->isEmpty())
            <div class="empty">
                Nenhuma conta a receber encontrada para os filtros selecionados.
            </div>
        @endif

        <div class="footer">
            <p>Relatório gerado em {{ now()->format('d/m/Y H:i') }}</p>
            <p>Oravel © 2026</p>
        </div>
    </div>
</body>
</html>
