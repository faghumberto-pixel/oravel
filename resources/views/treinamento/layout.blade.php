<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('titulo') — Oravel</title>
    <style>
        :root {
            --navy: #1A3A52; --laranja: #EA580C;
            --bg: #f4f6f8; --card: #ffffff; --texto: #1f2937; --suave: #6b7280; --borda: #d9dee4;
            --ok: #15803d; --ok-bg: #dcfce7; --erro: #b91c1c; --erro-bg: #fee2e2; --alerta-bg: #fff4e5;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1c28; --card: #172a3b; --texto: #e8edf2; --suave: #9fb0bf; --borda: #2a4258;
                --ok: #4ade80; --ok-bg: #12351f; --erro: #f87171; --erro-bg: #3a1717; --alerta-bg: #3a2512;
            }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; overflow-x: hidden; }
        body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: var(--bg); color: var(--texto); line-height: 1.55; }
        .topo { background: var(--navy); color: #fff; padding: 14px 16px; }
        .topo b { font-size: 1.1rem; letter-spacing: .02em; }
        .topo span { color: var(--laranja); }
        main { max-width: 760px; margin: 0 auto; padding: 20px 16px 48px; }
        h1 { font-size: 1.5rem; line-height: 1.25; margin: 4px 0 8px; color: var(--navy); }
        @media (prefers-color-scheme: dark) { h1 { color: #fff; } }
        .card { background: var(--card); border: 1px solid var(--borda); border-radius: 12px; padding: 16px; margin: 14px 0; }
        label { display: block; font-weight: 600; margin: 10px 0 4px; }
        input[type=text] { width: 100%; padding: 11px 12px; border: 1px solid var(--borda); border-radius: 8px; background: var(--bg); color: var(--texto); font-size: 1rem; }
        input[type=text]:focus { outline: 2px solid var(--laranja); outline-offset: 1px; }
        .pergunta { margin: 18px 0; padding-bottom: 14px; border-bottom: 1px dashed var(--borda); }
        .pergunta:last-of-type { border-bottom: 0; }
        .pergunta h3 { font-size: 1rem; margin: 0 0 8px; }
        .alt { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; margin: 6px 0; padding: 10px 12px; border: 1px solid var(--borda); border-radius: 8px; cursor: pointer; }
        .alt input { margin-top: 4px; accent-color: var(--laranja); }
        .btn { display: inline-block; background: var(--laranja); color: #fff; border: 0; border-radius: 8px; padding: 12px 20px; font-size: 1rem; font-weight: 700; cursor: pointer; text-decoration: none; }
        .btn.sec { background: transparent; color: var(--navy); border: 1px solid var(--navy); }
        @media (prefers-color-scheme: dark) { .btn.sec { color: #fff; border-color: #fff; } }
        .erro { background: var(--erro-bg); color: var(--erro); border-radius: 8px; padding: 10px 12px; margin: 10px 0; }
        .nota { font-size: 2.4rem; font-weight: 800; margin: 0; }
        .selo { display: inline-block; border-radius: 999px; padding: 4px 12px; font-weight: 700; }
        .selo.ok { background: var(--ok-bg); color: var(--ok); }
        .selo.nao { background: var(--erro-bg); color: var(--erro); }
        .rev { margin: 12px 0; padding: 12px; border-radius: 8px; border: 1px solid var(--borda); }
        .rev.certa { border-left: 4px solid var(--ok); }
        .rev.errada { border-left: 4px solid var(--erro); }
        .rev p { margin: 4px 0; }
        .suave { color: var(--suave); font-size: .9rem; }
        .aviso { background: var(--alerta-bg); border-radius: 8px; padding: 10px 12px; }
        .oculto-robo { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    </style>
</head>
<body>
    <div class="topo"><b>ORAVEL<span>.</span></b> &nbsp; Treinamento</div>
    <main>@yield('conteudo')</main>
</body>
</html>
