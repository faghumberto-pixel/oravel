<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Ativo</title>
    <style>
        body { font-family: sans-serif; color: #333; }
        .header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .metric { font-size: 18px; font-weight: bold; color: #2d3748; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Relatório Executivo: {{ $asset->name }}</h1>
        <p>TAG: {{ $asset->tag }} | Data: {{ $date }}</p>
    </div>

    <h3>Status Financeiro</h3>
    <p class="metric">ROI de Manutenção: {{ number_format($roi, 2) }}%</p>
    <p>Análise: {{ $status_financeiro }}</p>

    <h3>Resumo de Custos</h3>
    <ul>
        <li>Valor de Aquisição: R$ {{ number_format($asset->acquisition_value, 2, ',', '.') }}</li>
        <li>Total Gasto em Manutenção: R$ {{ number_format($total_maintenance, 2, ',', '.') }}</li>
    </ul>

    <h3>Histórico de Intervenções</h3>
    <table>
        <tr><th>Data</th><th>Serviço</th><th>Custo Total</th></tr>
        @foreach($asset->maintenanceOrders as $os)
        <tr>
            <td>{{ $os->started_at?->format('d/m/Y') }}</td>
            <td>{{ $os->service_type }}</td>
            <td>R$ {{ number_format($os->total_order_cost, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>
</body>
</html>
