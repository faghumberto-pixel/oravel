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
            background: #f5f5f5;
            color: #333;
            line-height: 1.4;
            padding: 20px;
        }
        .print-header {
            background: white;
            padding: 15px 20px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 4px;
        }
        .print-header-text {
            font-size: 12px;
            color: #666;
        }
        .print-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-fechar {
            background: white;
            color: #333;
        }
        .btn-fechar:hover {
            background: #f0f0f0;
        }
        .btn-imprimir {
            background: #ffa500;
            color: white;
            border-color: #ffa500;
        }
        .btn-imprimir:hover {
            background: #ff8c00;
        }
        .container {
            background: white;
            padding: 30px;
            max-width: 1200px;
            margin: 0 auto;
            border-radius: 4px;
        }
        .title-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 20px;
        }
        .title-left h1 {
            font-size: 20px;
            color: #ffa500;
            margin-bottom: 5px;
        }
        .title-left p {
            font-size: 11px;
            color: #999;
        }
        .title-right {
            text-align: right;
        }
        .title-right h2 {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .title-right p {
            font-size: 11px;
            color: #999;
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
            background: #f9f9f9;
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
            background: #f5f5f5;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 2px solid #ddd;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
                background: white;
            }
            .print-header {
                display: none;
            }
            .container {
                padding: 10px;
                max-width: 100%;
                background: white;
                border-radius: 0;
            }
            .title-section {
                border-bottom: 1px solid #ccc;
            }
            .footer {
                page-break-after: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="print-header">
        <div class="print-header-text">
            <strong>→ Visualização PHP Minimalista — Kanban de Execuções Preventivas</strong>
        </div>
        <div class="print-actions">
            <button onclick="window.history.back()" class="btn btn-fechar">Fechar</button>
            <button onclick="window.print()" class="btn btn-imprimir">Imprimir Agora</button>
        </div>
    </div>

    <div class="container">
        <div class="title-section">
            <div class="title-left">
                <h1>ORAVEL SISTEMAS</h1>
                <p>{{ now()->format('d/m/Y H:i:s') }}</p>
            </div>
            <div class="title-right">
                <h2>KANBAN DE PREVENTIVAS</h2>
                <p>{{ now()->format('d/m/Y') }}</p>
            </div>
        </div>

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

        <div class="footer">
            Total de execuções: {{ collect($records)->flatten()->count() }}
        </div>
    </div>
</body>
</html>
