<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban de Execuções Preventivas - Impressão</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: white;
            color: #333;
            line-height: 1.4;
        }
        .header {
            background: #f0f0f0;
            padding: 20px;
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 12px;
            color: #666;
        }
        .container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            page-break-inside: avoid;
        }
        .column {
            page-break-inside: avoid;
        }
        .column-header {
            background: #333;
            color: white;
            padding: 12px;
            font-weight: bold;
            font-size: 13px;
            border: 1px solid #333;
            margin-bottom: 10px;
        }
        .card {
            background: white;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            page-break-inside: avoid;
            font-size: 11px;
        }
        .card-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .card-info {
            margin: 3px 0;
            color: #666;
        }
        .footer {
            margin-top: 30px;
            padding: 15px;
            background: #f0f0f0;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 2px solid #333;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .container {
                padding: 10px;
            }
            .header {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Kanban de Execuções Preventivas</h1>
        <p>Impresso em {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="container">
        <div class="grid">
            @foreach($statuses as $statusKey => $statusData)
                <div class="column">
                    <div class="column-header">
                        {{ $statusData['title'] }}
                        <span style="float: right;">{{ count($records[$statusKey] ?? []) }}</span>
                    </div>
                    @forelse($records[$statusKey] ?? [] as $execution)
                        <div class="card">
                            <div class="card-title">{{ $execution->asset?->patrimonio ?? 'N/A' }}</div>
                            <div class="card-info"><strong>{{ $execution->maintenancePlan?->name ?? 'Sem Plano' }}</strong></div>
                            @if($execution->technician)
                                <div class="card-info">👤 {{ $execution->technician->name }}</div>
                            @endif
                            <div class="card-info">
                                Horímetro: {{ number_format($execution->horimetro_at_execution, 0) }}h
                                @if($execution->next_due_horimetro)
                                    / {{ number_format($execution->next_due_horimetro, 0) }}h
                                @endif
                            </div>
                            <div class="card-info">{{ $execution->created_at?->format('d/m/Y') ?? '--' }}</div>
                        </div>
                    @empty
                        <div style="text-align: center; color: #999; padding: 20px; font-size: 11px;">
                            Nenhuma execução
                        </div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </div>

    <div class="footer">
        Total de execuções: {{ collect($records)->flatten()->count() }}
    </div>

    <script>
        window.print();
    </script>
</body>
</html>
