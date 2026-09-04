<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assinatura Eletrônica</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }

        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            font-weight: 600;
            color: #333;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-box {
            background: #f9f9f9;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }

        .info-box p {
            margin: 5px 0;
            font-size: 14px;
            color: #555;
        }

        .info-box strong {
            color: #333;
        }

        .button-container {
            text-align: center;
            margin: 30px 0;
        }

        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 14px 40px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.3s, box-shadow 0.3s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }

        .instructions {
            background: #e8f4f8;
            border: 1px solid #b3dfe8;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .instructions h3 {
            color: #0288a3;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .instructions ol {
            margin-left: 20px;
            color: #333;
            font-size: 14px;
        }

        .instructions li {
            margin-bottom: 8px;
        }

        .features {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .feature {
            flex: 1;
            text-align: center;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 6px;
        }

        .feature-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .feature-text {
            font-size: 12px;
            color: #555;
            line-height: 1.4;
        }

        .validity {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #856404;
        }

        .validity strong {
            color: #333;
        }

        .footer {
            background: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #e0e0e0;
        }

        .footer p {
            margin: 5px 0;
        }

        .footer a {
            color: #667eea;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .divider {
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
        }

        @media (max-width: 600px) {
            .container {
                margin: 0;
                border-radius: 0;
            }

            .content {
                padding: 20px;
            }

            .features {
                flex-direction: column;
            }

            .button {
                width: 100%;
                padding: 12px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>✍️ Assinatura Eletrônica</h1>
            <p>{{ $documentType }}</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">Olá {{ $signature->signer_name }}!</p>

            <p>Você foi solicitado para assinar um <strong>{{ $documentType }}</strong> eletronicamente. Este documento tem validade jurídica e pode ser assinado diretamente pelo seu navegador, sem a necessidade de programas especiais.</p>

            <!-- Validity Notice -->
            <div class="validity">
                ⏰ <strong>Este link é válido até {{ $expiresAt }}</strong>
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h3>🔍 Como assinar:</h3>
                <ol>
                    <li><strong>Clique no botão</strong> "Assinar Documento" abaixo</li>
                    <li><strong>Preencha seus dados</strong> (nome, email, telefone)</li>
                    <li><strong>Desenhe ou digite</strong> sua assinatura</li>
                    <li><strong>Confirme e envie</strong> para finalizar</li>
                    <li><strong>Baixe o PDF</strong> assinado e autenticado</li>
                </ol>
            </div>

            <!-- CTA Button -->
            <div class="button-container">
                <a href="{{ $link }}" class="button">Assinar Documento Agora</a>
            </div>

            <!-- Features -->
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">🔐</div>
                    <div class="feature-text">Totalmente<br>Seguro</div>
                </div>
                <div class="feature">
                    <div class="feature-icon">📱</div>
                    <div class="feature-text">Funciona em<br>Mobile</div>
                </div>
                <div class="feature">
                    <div class="feature-icon">⚡</div>
                    <div class="feature-text">Assinatura<br>Imediata</div>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Info Box -->
            <div class="info-box">
                <p><strong>📌 Informações importantes:</strong></p>
                <p>✓ Não é necessário ter uma conta no sistema</p>
                <p>✓ A assinatura é criptografada e segura</p>
                <p>✓ Você receberá uma cópia do documento assinado</p>
                <p>✓ Pode assinar desenhando ou digitando seu nome</p>
            </div>

            <!-- Security -->
            <div class="info-box">
                <p><strong>🛡️ Segurança da sua assinatura:</strong></p>
                <p>Sua localização, IP e dados são registrados para auditoria. O documento gerado contém um hash SHA-256 para verificação de integridade.</p>
            </div>

            <!-- Direct Link -->
            <p style="text-align: center; margin-top: 20px; font-size: 12px; color: #999;">
                Se o botão não funcionar, copie e cole este link no seu navegador:<br>
                <a href="{{ $link }}" style="color: #667eea; word-break: break-all;">{{ $link }}</a>
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Se não foi você quem solicitou esta assinatura, desconsidere este e-mail.</p>
            <p>Dúvidas? Entre em contato com o suporte.</p>
            <p style="margin-top: 15px; border-top: 1px solid #ddd; padding-top: 10px;">
                © {{ now()->year }} Oravel - Sistema de Gestão de Frota<br>
                Todos os direitos reservados
            </p>
        </div>
    </div>
</body>
</html>
