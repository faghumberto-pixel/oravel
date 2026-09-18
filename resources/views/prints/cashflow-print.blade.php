<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fluxo de Caixa - Impressão</title>
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        thead {
            background: #f0f0f0;
            border-bottom: 2px solid #ddd;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #0b0f0d;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-receber {
            background: #dbeafe;
            color: #0c4a6e;
        }

        .badge-pagar {
            background: #fed7aa;
            color: #92400e;
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
            <h1>📊 Fluxo de Caixa</h1>
            <p>Relatório de Contas a Receber e a Pagar</p>
        </div>

        <div class="filters-info">
            <strong>Período:</strong> {{ $dateStart ? \Carbon\Carbon::parse($dateStart)->format('d/m/Y') : 'Indefinido' }}
            até {{ $dateEnd ? \Carbon\Carbon::parse($dateEnd)->format('d/m/Y') : 'Hoje' }}
            @if($status)
                | <strong>Status:</strong> {{ ucfirst($status) }}
            @endif
            @if($type)
                | <strong>Tipo:</strong> {{ $type === 'AR' ? 'Contas a Receber' : 'Contas a Pagar' }}
            @endif
        </div>

        @if($records->isEmpty())
            <p style="text-align: center; padding: 40px; color: #999;">Nenhum registro encontrado para os filtros selecionados.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th class="text-right">Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $record)
                        @php
                            $typeLabel = $record->type === 'AR' ? 'Receber' : 'Pagar';
                            $typeBadge = $record->type === 'AR' ? 'badge-receber' : 'badge-pagar';
                            $statusBadge = match($record->status) {
                                'pago' => 'badge-pago',
                                'atrasado' => 'badge-atrasado',
                                'pendente' => 'badge-pendente',
                                default => 'badge-pendente',
                            };
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($record->date)->format('d/m/Y') }}</td>
                            <td>
                                @if($record->type === 'AR' && $record->client_id)
                                    @php $client = \App\Models\Client::find($record->client_id) @endphp
                                    {{ $client?->name ?? '-' }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $record->description }}</td>
                            <td><span class="badge {{ $typeBadge }}">{{ $typeLabel }}</span></td>
                            <td class="text-right">R$ {{ number_format($record->amount, 2, ',', '.') }}</td>
                            <td><span class="badge {{ $statusBadge }}">{{ ucfirst($record->status) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="4" style="text-align: right;">Total:</td>
                        <td class="text-right">R$ {{ number_format($records->sum('amount'), 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <div class="footer">
                <p>Relatório gerado em {{ now()->format('d/m/Y H:i') }}</p>
                <p>Oravel © 2026</p>
            </div>
        @endif
    </div>
</body>
</html>
