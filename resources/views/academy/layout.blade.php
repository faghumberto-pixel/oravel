@php $theme = in_array(config('oravel.academy.theme'), ['aurora', 'planta', 'ondas'], true) ? config('oravel.academy.theme') : 'aurora'; @endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Oravel Academy</title>
    <style>
        :root {
            --navy: #0b1f3a; --navy2: #12305a; --blue: #2563eb; --teal: #0ea5a4; --violet: #7c3aed;
            --green: #16a34a; --amber: #d97706; --red: #dc2626;
            --bg: #f3f5fa; --card: #fff; --line: #e4e8f0; --text: #1e293b; --muted: #64748b;
            --side: 292px; --top: 58px;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body { font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--text); background: var(--bg); -webkit-font-smoothing: antialiased; }
        a { color: var(--blue); text-decoration: none; }
        button { font: inherit; cursor: pointer; }

        /* ---------- fundo da pagina (sutil) ---------- */
        body.ac-theme-aurora { background:
            radial-gradient(1100px 420px at 50% -180px, rgba(37,99,235,.13), transparent),
            radial-gradient(rgba(15,39,66,.055) 1px, transparent 1px) 0 0 / 22px 22px, var(--bg); }
        body.ac-theme-planta { background:
            linear-gradient(rgba(15,39,66,.045) 1px, transparent 1px) 0 0 / 34px 34px,
            linear-gradient(90deg, rgba(15,39,66,.045) 1px, transparent 1px) 0 0 / 34px 34px, var(--bg); }
        body.ac-theme-ondas { background:
            linear-gradient(180deg, #e9effb 0, var(--bg) 380px), var(--bg); }

        /* ---------- topo ---------- */
        .ac-top { position: fixed; inset: 0 0 auto 0; height: var(--top); z-index: 40; display: flex; align-items: center; gap: 14px; padding: 0 18px;
            background: linear-gradient(90deg, var(--navy), var(--navy2)); color: #fff; box-shadow: 0 2px 12px rgba(11,31,58,.25); }
        .ac-burger { display: none; background: transparent; border: 0; color: #fff; font-size: 22px; line-height: 1; padding: 4px 8px; }
        .ac-brand { color: #fff; font-weight: 800; font-size: 18px; letter-spacing: .2px; display: flex; align-items: center; gap: 9px; }
        .ac-brand i { width: 26px; height: 26px; border-radius: 8px; background: linear-gradient(135deg, var(--blue), var(--teal)); display: inline-block; box-shadow: 0 0 0 3px rgba(255,255,255,.12); }
        .ac-brand span { font-weight: 500; opacity: .85; }
        .ac-top-right { margin-left: auto; display: flex; align-items: center; gap: 16px; font-size: 13px; }
        .ac-top-right a { color: #cfe0ff; }
        .ac-top-right a:hover { color: #fff; }
        .ac-user { background: rgba(255,255,255,.12); padding: 6px 12px; border-radius: 999px; }

        /* ---------- estrutura ---------- */
        .ac-shell { display: flex; padding-top: var(--top); min-height: 100vh; }
        .ac-side { width: var(--side); flex: 0 0 var(--side); position: fixed; top: var(--top); bottom: 0; left: 0; overflow-y: auto; background: #fff; border-right: 1px solid var(--line); z-index: 30; }
        .ac-main { flex: 1; margin-left: var(--side); min-width: 0; padding: 26px 30px 60px; }
        .ac-backdrop { display: none; }
        @media (max-width: 900px) {
            .ac-burger { display: block; }
            .ac-side { transform: translateX(-100%); transition: transform .2s; box-shadow: 4px 0 24px rgba(0,0,0,.2); }
            .ac-side.ac-open { transform: none; }
            .ac-main { margin-left: 0; padding: 18px 14px 50px; }
            .ac-backdrop.ac-open { display: block; position: fixed; inset: var(--top) 0 0 0; background: rgba(11,31,58,.45); z-index: 25; }
            .ac-user { display: none; }
            .ac-hide-sm { display: none; }
        }

        /* ---------- barra lateral ---------- */
        .ac-sec { padding: 16px 14px 4px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); }
        .ac-nav a, .ac-course { display: block; padding: 9px 12px; margin: 2px 8px; border-radius: 10px; color: var(--text); font-size: 14px; }
        .ac-nav a:hover, .ac-course:hover { background: #f1f5ff; }
        .ac-nav a.ac-on, .ac-course.ac-on { background: #e8efff; color: var(--blue); font-weight: 600; }
        .ac-course-title { display: flex; justify-content: space-between; gap: 8px; align-items: baseline; }
        .ac-course-title b { font-weight: 600; font-size: 13.5px; line-height: 1.25; }
        .ac-course small { color: var(--muted); font-size: 11.5px; }
        .ac-mini { display: block; height: 5px; background: #e5e9f2; border-radius: 99px; margin: 7px 0 5px; overflow: hidden; }
        .ac-mini div { height: 5px; border-radius: 99px; }
        .ac-side-foot { position: sticky; bottom: 0; background: #fff; border-top: 1px solid var(--line); padding: 12px 16px; font-size: 13px; display: flex; justify-content: space-between; }
        .ac-empty { padding: 4px 22px; font-size: 12.5px; color: #94a3b8; }

        /* ---------- hero com fundo ---------- */
        .ac-hero { position: relative; overflow: hidden; border-radius: 20px; padding: 30px 32px; color: #fff; margin-bottom: 22px; box-shadow: 0 10px 30px rgba(11,31,58,.18); }
        .ac-hero > * { position: relative; z-index: 2; }
        .ac-hero h1 { margin: 0 0 6px; font-size: 28px; line-height: 1.15; letter-spacing: -.3px; }
        .ac-hero p { margin: 0; opacity: .88; font-size: 15px; max-width: 640px; }
        .ac-hero.sm { padding: 22px 26px; }
        .ac-hero.sm h1 { font-size: 23px; }
        .ac-hero-row { display: flex; gap: 22px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
        .ac-crumb { font-size: 12.5px; opacity: .8; margin-bottom: 8px; }
        .ac-crumb a { color: #cfe0ff; }

        .ac-theme-aurora .ac-hero { background:
            radial-gradient(760px 360px at 8% -20%, rgba(37,99,235,.95), transparent 62%),
            radial-gradient(620px 340px at 96% 0%, rgba(124,58,237,.85), transparent 58%),
            radial-gradient(520px 300px at 62% 130%, rgba(14,165,164,.8), transparent 60%), var(--navy); }
        .ac-theme-aurora .ac-hero::after { content: ""; position: absolute; inset: 0; z-index: 1; opacity: .5;
            background: radial-gradient(rgba(255,255,255,.18) 1px, transparent 1px) 0 0 / 20px 20px;
            -webkit-mask-image: linear-gradient(120deg, transparent 20%, #000 90%); mask-image: linear-gradient(120deg, transparent 20%, #000 90%); }

        .ac-theme-planta .ac-hero { background:
            radial-gradient(520px 260px at 85% 10%, rgba(37,99,235,.55), transparent 65%),
            linear-gradient(rgba(255,255,255,.075) 1px, transparent 1px) 0 0 / 30px 30px,
            linear-gradient(90deg, rgba(255,255,255,.075) 1px, transparent 1px) 0 0 / 30px 30px,
            linear-gradient(135deg, var(--navy), var(--navy2)); }
        .ac-theme-planta .ac-hero::after { content: ""; position: absolute; right: -40px; top: -40px; width: 220px; height: 220px; z-index: 1; border-radius: 50%;
            border: 2px dashed rgba(255,255,255,.28); box-shadow: inset 0 0 0 28px rgba(255,255,255,.04); }

        .ac-theme-ondas .ac-hero { background: linear-gradient(135deg, var(--navy) 0%, #174a9c 55%, #1d6fd8 100%); }
        .ac-theme-ondas .ac-hero::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 90px; z-index: 1;
            background: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'><path d='M0,64 C200,110 360,10 600,58 C840,106 1000,20 1200,62 L1200,120 L0,120 Z' fill='white' fill-opacity='0.10'/><path d='M0,84 C220,40 420,116 660,78 C900,40 1040,104 1200,76 L1200,120 L0,120 Z' fill='white' fill-opacity='0.12'/><path d='M0,100 C260,76 480,120 720,98 C960,76 1080,112 1200,98 L1200,120 L0,120 Z' fill='white' fill-opacity='0.16'/></svg>") bottom / 100% 100% no-repeat; }

        /* ---------- cartoes e indicadores ---------- */
        .ac-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 18px 20px; box-shadow: 0 1px 2px rgba(15,39,66,.04); }
        .ac-card h2, .ac-h2 { margin: 0 0 14px; font-size: 16px; }
        .ac-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 14px; margin-bottom: 22px; }
        .ac-kpi { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 16px 18px; box-shadow: 0 1px 2px rgba(15,39,66,.04); }
        .ac-kpi small { color: var(--muted); font-size: 12px; display: block; }
        .ac-kpi strong { font-size: 25px; display: block; margin: 3px 0 6px; letter-spacing: -.3px; }
        .ac-kpi em { font-style: normal; font-size: 11.5px; color: #94a3b8; display: block; margin-top: 5px; }
        .ac-btn { display: inline-flex; align-items: center; gap: 6px; border: 0; border-radius: 11px; padding: 10px 18px; font-weight: 600; font-size: 14px; background: var(--blue); color: #fff; }
        .ac-btn:hover { filter: brightness(1.08); }
        .ac-btn.alt { background: #fff; color: var(--navy); }
        .ac-btn.ghost { background: #eef2fb; color: var(--navy); }
        .ac-btn.ok { background: var(--green); }
        .ac-btn[disabled] { opacity: .5; cursor: default; }
        .ac-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
        .ac-pill { border: 1px solid var(--line); background: #fff; color: var(--text); border-radius: 999px; padding: 7px 14px; font-size: 13px; }
        .ac-pill.on { background: var(--navy); color: #fff; border-color: var(--navy); }
        .ac-badge { display: inline-block; border-radius: 999px; padding: 3px 10px; font-size: 11.5px; font-weight: 600; }
        .ac-badge.todo { background: #eef2fb; color: #475569; }
        .ac-badge.progress { background: #fef3c7; color: #92400e; }
        .ac-badge.done { background: #dcfce7; color: #166534; }
        .ac-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 16px; }
        .ac-course-card { display: flex; flex-direction: column; gap: 10px; color: var(--text); transition: transform .12s, box-shadow .12s; }
        .ac-course-card:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(15,39,66,.1); }
        .ac-course-card h3 { margin: 0; font-size: 16px; line-height: 1.25; }
        .ac-course-card p { margin: 0; color: var(--muted); font-size: 13px; line-height: 1.45; }
        .ac-meta { display: flex; justify-content: space-between; gap: 10px; font-size: 12px; color: var(--muted); }
        .ac-table-wrap { overflow-x: auto; }
        table.ac-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .ac-table th { text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); padding: 8px 10px; border-bottom: 1px solid var(--line); white-space: nowrap; }
        .ac-table th.s { cursor: pointer; }
        .ac-table td { padding: 11px 10px; border-bottom: 1px solid #f0f3f9; vertical-align: middle; }
        .ac-table tr:last-child td { border-bottom: 0; }
        .ac-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 22px; }
        @media (max-width: 900px) { .ac-grid2 { grid-template-columns: 1fr; } }
        .ac-toast { position: fixed; right: 20px; bottom: 20px; z-index: 60; background: var(--navy); color: #fff; padding: 12px 18px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.25); font-size: 14px; }
        .ac-alert { background: #fff7ed; border: 1px solid #fed7aa; color: #9a3412; padding: 10px 14px; border-radius: 12px; font-size: 13.5px; margin-bottom: 16px; }
        .ac-inputs { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
        .ac-inputs select, .ac-inputs input { border: 1px solid var(--line); border-radius: 10px; padding: 9px 12px; font: inherit; font-size: 13.5px; background: #fff; }

        /* ---------- curso / aula ---------- */
        .ac-course-grid { display: grid; grid-template-columns: 300px minmax(0, 1fr); gap: 20px; align-items: start; }
        @media (max-width: 1100px) { .ac-course-grid { grid-template-columns: 1fr; } }
        .ac-lessons { position: sticky; top: calc(var(--top) + 16px); padding: 10px; }
        .ac-lesson { width: 100%; text-align: left; display: flex; gap: 10px; align-items: flex-start; border: 0; background: transparent; border-radius: 11px; padding: 10px; color: var(--text); font-size: 13.5px; }
        .ac-lesson:hover { background: #f5f8ff; }
        .ac-lesson.on { background: #e8efff; font-weight: 600; }
        .ac-dot { flex: 0 0 24px; height: 24px; border-radius: 50%; display: grid; place-items: center; font-size: 12px; background: #e5e9f2; color: #475569; font-weight: 700; }
        .ac-dot.done { background: var(--green); color: #fff; }
        .ac-dot.cur { background: var(--blue); color: #fff; }
        .ac-viewer h2 { font-size: 21px; margin: 0 0 6px; }
        .ac-video { position: relative; padding-top: 56.25%; border-radius: 14px; overflow: hidden; background: #000; margin: 14px 0; }
        .ac-video iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
        .ac-video-soon { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; text-align: center; margin: 14px 0; padding: 38px 20px; border-radius: 14px; color: #fff;
            background: linear-gradient(135deg, var(--navy) 0%, #174a9c 100%); box-shadow: inset 0 0 0 1px rgba(255,255,255,.08); }
        .ac-video-soon b { font-size: 18px; letter-spacing: .2px; }
        .ac-video-soon span { font-size: 13.5px; opacity: .85; max-width: 420px; line-height: 1.5; }
        .ac-body { line-height: 1.65; font-size: 15px; }
        .ac-body img { max-width: 100%; }
        .ac-quiz { background: #f6f8ff; border: 1px solid #dfe7fb; border-radius: 14px; padding: 16px; margin-top: 18px; }
        .ac-q { margin-bottom: 16px; }
        .ac-q label { display: flex; gap: 8px; align-items: center; padding: 6px 0; font-size: 14px; }
        .ac-ok { color: var(--green); font-weight: 600; font-size: 13.5px; }
        .ac-bad { color: var(--red); font-weight: 600; font-size: 13.5px; }
        .ac-nav-btns { display: flex; justify-content: space-between; gap: 10px; margin-top: 22px; flex-wrap: wrap; }
        .ac-cert { display: flex; gap: 14px; align-items: center; justify-content: space-between; flex-wrap: wrap; background: linear-gradient(90deg, #eff6ff, #f5f3ff); border: 1px solid #c7d7fb; border-radius: 14px; padding: 14px 18px; margin-bottom: 18px; font-size: 14px; }
        .ac-rank { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; background: #eef2fb; font-weight: 700; font-size: 13px; }
        .ac-rank.g1 { background: #fde68a; } .ac-rank.g2 { background: #e5e7eb; } .ac-rank.g3 { background: #fdba74; }
        [x-cloak] { display: none !important; }
    </style>
    @livewireStyles
</head>
<body class="ac-theme-{{ $theme }}" x-data="{ open: false }">
    <header class="ac-top">
        <button class="ac-burger" type="button" @click="open = !open" aria-label="Menu">☰</button>
        <a class="ac-brand" href="{{ url('/academia') }}"><i></i>Oravel <span>Academy</span></a>
        <div class="ac-top-right">
            <a href="{{ url('/admin') }}">← Voltar<span class="ac-hide-sm"> ao sistema</span></a>
            <span class="ac-user">{{ auth()->user()->name }}</span>
        </div>
    </header>

    <div class="ac-shell">
        <aside class="ac-side" :class="{ 'ac-open': open }" @click="if ($event.target.closest('a')) open = false">
            <livewire:academy.sidebar />
        </aside>
        <div class="ac-backdrop" :class="{ 'ac-open': open }" @click="open = false"></div>
        <main class="ac-main">{{ $slot }}</main>
    </div>

    @livewireScripts
</body>
</html>
