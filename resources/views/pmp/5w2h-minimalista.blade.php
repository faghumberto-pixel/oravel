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

        html, body {
            width: 100%;
            height: 100%;
        }

        body {
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            color: #2c3e50;
            line-height: 1.7;
            font-size: 13px;
        }

        .toolbar {
            background: white;
            padding: 12px 20px;
            border-bottom: 1px solid #d0d0d0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .toolbar-text {
            font-size: 11px;
            color: #666;
            font-weight: 500;
        }

        .toolbar-text strong {
            color: #333;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        button {
            padding: 7px 14px;
            border: 1px solid #999;
            background: white;
            color: #333;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            cursor: pointer;
            border-radius: 3px;
            transition: all 0.15s;
        }

        button:hover {
            background: #efefef;
            border-color: #666;
        }

        .btn-print {
            background: #2c5282;
            color: white;
            border-color: #1a365d;
        }

        .btn-print:hover {
            background: #1a365d;
        }

        .document {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 50px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.12);
            line-height: 1.8;
        }

        .doc-header {
            margin-bottom: 40px;
            padding-bottom: 24px;
            border-bottom: 2px solid #333;
        }

        .doc-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .doc-subtitle {
            font-size: 12px;
            color: #666;
            font-weight: 400;
        }

        .doc-meta {
            text-align: right;
            font-size: 10px;
            color: #999;
            margin-top: 20px;
            font-family: 'Courier New', monospace;
        }

        .content {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 32px;
            margin-bottom: 40px;
        }

        .item {
            padding-bottom: 20px;
        }

        .item-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #1a1a1a;
            letter-spacing: 0.8px;
            margin-bottom: 10px;
            display: block;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 8px;
        }

        .item-text {
            font-size: 13px;
            color: #444;
            line-height: 1.7;
            text-align: justify;
        }

        .item-full {
            grid-column: 1 / -1;
        }

        .doc-footer {
            border-top: 1px solid #d0d0d0;
            padding-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #999;
        }

        .no-print {
            display: block;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: white;
            }

            .document {
                max-width: 100%;
                margin: 0;
                padding: 40px;
                box-shadow: none;
                page-break-after: auto;
            }

            .content {
                page-break-inside: avoid;
            }

            .item {
                page-break-inside: avoid;
            }

            button {
                display: none !important;
            }
        }

        @media (max-width: 800px) {
            .document {
                padding: 30px 20px;
            }

            .content {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .item-full {
                grid-column: 1;
            }

            .toolbar {
                flex-direction: column;
                gap: 10px;
            }

            .actions {
                width: 100%;
            }

            .actions button {
                flex: 1;
            }
        }
    </style>
</head>
<body>

    <div class="no-print toolbar">
        <div class="toolbar-text">
            <strong>Visualização Minimalista</strong> — {{ $data['title'] }}
        </div>
        <div class="actions">
            <button onclick="history.back()">Fechar</button>
            <button class="btn-print" onclick="this.style.display='none'; window.print(); setTimeout(()=>this.style.display='',500);">Imprimir</button>
        </div>
    </div>

    <div class="document">
        <!-- Cabeçalho -->
        <div class="doc-header">
            <div class="doc-title">{{ $data['title'] }}</div>
            <div class="doc-subtitle">{{ $data['subtitle'] }}</div>
            <div class="doc-meta">{{ now()->format('d.m.Y') }} — {{ now()->format('H:i') }}</div>
        </div>

        <!-- Conteúdo 5W2H -->
        <div class="content">
            <div class="item">
                <span class="item-title">O Quê?</span>
                <div class="item-text">{{ $data['what'] }}</div>
            </div>

            <div class="item">
                <span class="item-title">Por Quê?</span>
                <div class="item-text">{{ $data['why'] }}</div>
            </div>

            <div class="item">
                <span class="item-title">Quando?</span>
                <div class="item-text">{{ $data['when'] }}</div>
            </div>

            <div class="item">
                <span class="item-title">Onde?</span>
                <div class="item-text">{{ $data['where'] }}</div>
            </div>

            <div class="item">
                <span class="item-title">Quem?</span>
                <div class="item-text">{{ $data['who'] }}</div>
            </div>

            <div class="item">
                <span class="item-title">Como?</span>
                <div class="item-text">{{ $data['how'] }}</div>
            </div>

            <div class="item item-full">
                <span class="item-title">Quanto?</span>
                <div class="item-text">{{ $data['howmuch'] }}</div>
            </div>
        </div>

        <!-- Rodapé -->
        <div class="doc-footer">
            ORAVEL ERP — Planejamento de Manutenção Preventiva
        </div>
    </div>

</body>
</html>
