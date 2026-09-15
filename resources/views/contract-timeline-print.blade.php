<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timeline - Contrato #{{ $contract->contract_number }}</title>
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
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 30px;
            background: white;
            border: 1px solid #ddd;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #ff6a1a;
            padding-bottom: 15px;
        }

        .header h1 {
            color: #ff6a1a;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            background: #f5f5f5;
            padding: 10px 15px;
            font-weight: bold;
            border-left: 4px solid #ff6a1a;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            border: 1px solid #eee;
            padding: 12px;
            border-radius: 4px;
        }

        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .info-value {
            font-size: 16px;
            color: #000;
            font-weight: bold;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            padding: 15px;
            text-align: center;
            border-radius: 4px;
        }

        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 22px;
            font-weight: bold;
            color: #000;
        }

        .maintenance-list {
            margin-top: 15px;
        }

        .maintenance-item {
            border-left: 3px solid #ddd;
            padding-left: 12px;
            margin-bottom: 10px;
            padding: 10px;
            padding-left: 12px;
            background: #fafafa;
        }

        .maintenance-date {
            font-size: 12px;
            color: #999;
        }

        .maintenance-type {
            display: inline-block;
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 3px;
            margin-left: 8px;
        }

        .type-preventiva {
            background: #d1fae5;
            color: #065f46;
        }

        .type-corretiva {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .type-inspecao {
            background: #fef3c7;
            color: #92400e;
        }

        .maintenance-desc {
            font-size: 13px;
            color: #333;
            margin-top: 5px;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            font-size: 12px;
            color: #999;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            .container {
                max-width: 100%;
                margin: 0;
                padding: 20px;
                border: none;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>📋 TIMELINE DO CONTRATO</h1>
            <p>Contrato #{{ $contract->contract_number }}</p>
        </div>

        <!-- Informações Principais -->
        <div class="section">
            <div class="section-title">📦 Informações do Contrato</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Cliente</div>
                    <div class="info-value">{{ $contract->client->name }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Equipamento</div>
                    <div class="info-value">{{ $contract->asset->name }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Valor</div>
                    <div class="info-value">R$ {{ number_format($contract->price, 2, ',', '.') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">{{ $contract->is_active ? '✅ Ativo' : '❌ Inativo' }}</div>
                </div>
            </div>
        </div>

        <!-- Datas -->
        <div class="section">
            <div class="section-title">📅 Datas</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Data de Início</div>
                    <div class="info-value">{{ $timelineData['startDate']->format('d/m/Y') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Data de Vencimento</div>
                    <div class="info-value">{{ $timelineData['endDate']->format('d/m/Y') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Renovação Sugerida</div>
                    <div class="info-value">{{ $timelineData['renewalSuggestedDate']->format('d/m/Y') }}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Dias Restantes</div>
                    <div class="info-value" style="color: #0066cc;">{{ $timelineData['daysRemaining'] }}</div>
                </div>
            </div>
        </div>

        <!-- Estatísticas -->
        <div class="section">
            <div class="section-title">📊 Estatísticas</div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Dias Decorridos</div>
                    <div class="stat-value">{{ $timelineData['daysElapsed'] }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Dias Totais</div>
                    <div class="stat-value">{{ $timelineData['totalDays'] }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Manutenções</div>
                    <div class="stat-value">{{ $timelineData['maintenanceCount'] }}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Horas Trabalhadas</div>
                    <div class="stat-value">{{ $timelineData['totalMaintenanceHours'] }}</div>
                </div>
            </div>
        </div>

        <!-- Histórico de Manutenções -->
        @if(count($timelineData['maintenanceEvents']) > 0)
            <div class="section">
                <div class="section-title">🔧 Histórico de Manutenções</div>
                <div class="maintenance-list">
                    @foreach($timelineData['maintenanceEvents'] as $event)
                        <div class="maintenance-item" style="border-left-color: {{ $event['color'] }};">
                            <div class="maintenance-date">
                                {{ $event['date'] }}
                                <span class="maintenance-type type-{{ $event['type'] }}">
                                    {{ strtoupper($event['type']) }}
                                </span>
                            </div>
                            <div class="maintenance-desc">
                                {{ $event['description'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="section">
                <div class="section-title">🔧 Histórico de Manutenções</div>
                <p style="color: #999; text-align: center; padding: 20px;">
                    Nenhuma manutenção registrada neste período.
                </p>
            </div>
        @endif

        <!-- Observações -->
        <div class="section">
            <div class="section-title">📝 Resumo</div>
            <div class="info-item">
                <div style="font-size: 13px; color: #333;">
                    <p style="margin-bottom: 8px;">
                        <strong>Progresso do Contrato:</strong> {{ $timelineData['progress'] }}%
                    </p>
                    <p>
                        O contrato iniciou em {{ $timelineData['startDate']->format('d/m/Y') }} e vencerá em
                        {{ $timelineData['endDate']->format('d/m/Y') }}.
                        @if($timelineData['daysRemaining'] > 0)
                            Faltam <strong>{{ $timelineData['daysRemaining'] }} dias</strong> para o vencimento.
                        @else
                            O contrato está <strong>VENCIDO</strong>.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Relatório gerado em {{ now()->format('d/m/Y H:i') }}</p>
            <p>Oravel - Sistema de Gestão de Contratos</p>
        </div>
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
