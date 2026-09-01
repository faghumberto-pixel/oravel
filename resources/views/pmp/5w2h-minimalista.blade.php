<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $data['title'] }} — 5W2H</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            background: #fafbfc;
            color: #1f2937;
            line-height: 1.6;
            font-size: 14px;
        }

        .no-print {
            display: block;
        }

        .print-header {
            background: white;
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .print-header-text {
            font-size: 12px;
            color: #6b7280;
        }

        .print-header-text strong {
            color: #1f2937;
        }

        .print-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
            color: #374151;
        }

        .btn:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
        }

        .btn-print {
            background: #f97316;
            color: white;
            border-color: #f97316;
        }

        .btn-print:hover {
            background: #ea580c;
            border-color: #ea580c;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .page {
            background: white;
            padding: 40px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 24px;
            margin-bottom: 32px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #f97316;
            margin-bottom: 4px;
        }

        .header p {
            font-size: 13px;
            color: #6b7280;
        }

        .header-meta {
            text-align: right;
            font-size: 11px;
            color: #9ca3af;
            margin-top: 16px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .section {
            border: 1px solid #e5e7eb;
            padding: 20px;
            background: #f9fafb;
        }

        .section-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #374151;
            margin-bottom: 12px;
            display: block;
        }

        .section-content {
            font-size: 14px;
            color: #4b5563;
            line-height: 1.6;
        }

        .section-full {
            grid-column: 1 / -1;
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 16px;
            text-align: center;
            font-size: 11px;
            color: #9ca3af;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
                padding: 0;
            }

            .container {
                padding: 0;
                max-width: 100%;
            }

            .page {
                border: none;
                border-radius: 0;
                padding: 20px;
                box-shadow: none;
            }

            .grid {
                page-break-inside: avoid;
            }

            .section {
                page-break-inside: avoid;
            }
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .section-full {
                grid-column: 1;
            }

            .print-header {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }

            .print-actions {
                justify-content: center;
            }

            .page {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="no-print print-header">
        <div class="print-header-text">
            <strong>→ Visualização Minimalista</strong> — {{ $data['title'] }}
        </div>
        <div class="print-actions">
            <button onclick="window.history.back()" class="btn">Fechar</button>
            <button onclick="window.print()" class="btn btn-print">Imprimir Agora</button>
        </div>
    </div>

    <div class="container">
        <div class="page">
            <!-- Cabeçalho -->
            <div class="header">
                <h1>{{ $data['title'] }}</h1>
                <p>{{ $data['subtitle'] }}</p>
                <div class="header-meta">{{ now()->format('d/m/Y H:i:s') }}</div>
            </div>

            <!-- Grid 5W2H -->
            <div class="grid">
                <div class="section">
                    <span class="section-title">O Quê?</span>
                    <div class="section-content">{{ $data['what'] }}</div>
                </div>

                <div class="section">
                    <span class="section-title">Por Quê?</span>
                    <div class="section-content">{{ $data['why'] }}</div>
                </div>

                <div class="section">
                    <span class="section-title">Quando?</span>
                    <div class="section-content">{{ $data['when'] }}</div>
                </div>

                <div class="section">
                    <span class="section-title">Onde?</span>
                    <div class="section-content">{{ $data['where'] }}</div>
                </div>

                <div class="section">
                    <span class="section-title">Quem?</span>
                    <div class="section-content">{{ $data['who'] }}</div>
                </div>

                <div class="section">
                    <span class="section-title">Como?</span>
                    <div class="section-content">{{ $data['how'] }}</div>
                </div>

                <div class="section section-full">
                    <span class="section-title">Quanto?</span>
                    <div class="section-content">{{ $data['howmuch'] }}</div>
                </div>
            </div>

            <!-- Rodapé -->
            <div class="footer">
                Oravel ERP — Módulo de Planejamento de Manutenção Preventiva (PMP)
            </div>
        </div>
    </div>

</body>
</html>
