<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acesso Negado</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #eef0f3;
            color: #374151;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
            padding: 48px 40px;
            max-width: 420px;
            width: 100%;
            text-align: center;
        }

        .logo { font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -1px; margin-bottom: 28px; }
        .logo .accent { color: #E8541A; }

        .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: #fef2f2;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 30px;
        }

        h1 { font-size: 22px; font-weight: 700; color: #111827; margin-bottom: 10px; }

        p.message {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 8px;
        }

        p.support {
            font-size: 13px;
            color: #9ca3af;
            line-height: 1.6;
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid #f0f0f0;
        }

        .btn {
            display: inline-block;
            margin-top: 24px;
            background: #E8541A;
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 8px;
            transition: background 0.2s;
        }

        .btn:hover { background: #d6480f; }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">O<span class="accent">r</span>avel</div>
        <div class="icon">🔒</div>
        <h1>Acesso Negado</h1>
        <p class="message">{{ $message ?? 'Você não tem permissão para acessar este recurso.' }}</p>
        <p class="support">Favor contatar o responsável pelo sistema em sua empresa para liberar este acesso.</p>
        <a href="/admin" class="btn">← Voltar ao Início</a>
    </div>
</body>
</html>
