<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro na Assinatura</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0b0f0d 0%, #1a2f27 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 60px 40px;
            text-align: center;
        }

        .error-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
            background: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }

        h1 {
            font-size: 28px;
            color: #123028;
            margin-bottom: 12px;
        }

        .error-message {
            background: #fee;
            color: #c00;
            border-left: 4px solid #c00;
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 30px;
            font-size: 14px;
            text-align: left;
        }

        .error-message-title {
            font-weight: 600;
            margin-bottom: 8px;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-direction: column;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background: #123028;
            color: white;
        }

        .btn-primary:hover {
            background: #0f1f1c;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .help-text {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
            font-size: 13px;
            color: #666;
            text-align: left;
        }

        .help-text h3 {
            color: #123028;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .help-text ul {
            margin-left: 20px;
            line-height: 1.8;
        }

        @media (max-width: 600px) {
            .container {
                padding: 40px 20px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Error Icon -->
        <div class="error-icon">⚠️</div>

        <!-- Header -->
        <h1>Erro na Assinatura</h1>

        <!-- Error Message -->
        <div class="error-message">
            <div class="error-message-title">{{ $message ?? 'Ocorreu um erro ao processar sua assinatura' }}</div>
            <p style="margin-top: 8px; font-size: 13px;">
                Por favor, tente novamente ou entre em contato com o suporte.
            </p>
        </div>

        <!-- Actions -->
        <div class="actions">
            <button class="btn btn-primary" onclick="window.history.back()">
                ← Voltar
            </button>
            <a href="/" class="btn btn-secondary">
                🏠 Voltar ao Início
            </a>
        </div>

        <!-- Help Text -->
        <div class="help-text">
            <h3>Possíveis Causas:</h3>
            <ul>
                <li><strong>Token expirado:</strong> Sua assinatura pode ter expirado após 30 dias. Solicite um novo link.</li>
                <li><strong>Documento já assinado:</strong> Este documento já foi assinado anteriormente.</li>
                <li><strong>Assinatura cancelada:</strong> O administrador cancelou esta assinatura.</li>
                <li><strong>Erro técnico:</strong> Tente novamente ou use outro navegador.</li>
            </ul>
        </div>
    </div>
</body>
</html>
