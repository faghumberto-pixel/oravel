<!DOCTYPE html>
<html>
<head>
    <title>Acesso Negado</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #1a1a1a;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            text-align: center;
        }
        h1 { font-size: 72px; margin: 0; }
        p { font-size: 18px; margin: 20px 0; }
        a { color: #ff9800; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <p>Acesso Negado</p>
        <p>{{ $message ?? 'Você não tem permissão para acessar este recurso.' }}</p>
        <a href="/admin">← Voltar ao Dashboard</a>
    </div>
</body>
</html>
