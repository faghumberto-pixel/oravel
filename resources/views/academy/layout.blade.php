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
        button.ac-course { width: calc(100% - 16px); text-align: left; background: transparent; border: 0; font: inherit; color: var(--text); }
        .ac-chev { display: inline-block; width: 12px; color: var(--muted); font-size: 11px; }
        .ac-lessons-side { list-style: none; margin: 0 8px 6px 22px; padding: 2px 0 2px 10px; border-left: 2px solid #e2e8f4; }
        .ac-lessons-side a { display: flex; gap: 8px; align-items: flex-start; padding: 6px 8px; margin: 1px 0; border-radius: 8px; color: #334155; font-size: 12.8px; line-height: 1.3; }
        .ac-lessons-side a:hover { background: #f1f5ff; } .ac-lessons-side a.ac-on { background: #e8efff; color: var(--blue); font-weight: 700; }
        .ac-lessons-side em { font-style: normal; color: #94a3b8; font-size: 11.5px; }
        .ac-ldot { flex: 0 0 15px; height: 15px; margin-top: 1px; border-radius: 50%; border: 2px solid #cbd5e1; display: grid; place-items: center; font-size: 9px; color: #fff; font-weight: 800; }
        .ac-ldot.done { background: #16a34a; border-color: #16a34a; }
        .ac-draft { font-size: 10px; font-weight: 700; text-transform: uppercase; background: #ede9fe; color: #5b21b6; padding: 1px 6px; border-radius: 99px; margin-left: 4px; vertical-align: 1px; }
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
        /* ---------- corpo da aula: tipografia ---------- */
        .ac-body { line-height: 1.7; font-size: 15.5px; color: #243247; }
        .ac-body img { max-width: 100%; }
        .ac-body h2 { font-size: 20px; margin: 30px 0 10px; color: var(--navy); letter-spacing: -.2px; padding-bottom: 6px; border-bottom: 3px solid #dbe6ff; }
        .ac-body h2::before { content: ""; display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 9px; background: linear-gradient(135deg, var(--blue), var(--teal)); vertical-align: 1px; }
        .ac-body h3 { font-size: 16.5px; margin: 22px 0 8px; color: var(--blue); }
        .ac-body p { margin: 10px 0; }
        .ac-body strong, .ac-body b { color: var(--navy); font-weight: 700; }
        .ac-body em { color: #475569; }
        .ac-body code { background: #eef2ff; color: #3730a3; padding: 2px 7px; border-radius: 6px; font-size: 13.5px; font-weight: 600; }
        .ac-body ul, .ac-body ol { padding-left: 0; margin: 10px 0; list-style: none; }
        .ac-body ul > li { position: relative; padding: 4px 0 4px 26px; }
        .ac-body ul > li::before { content: "✦"; position: absolute; left: 4px; top: 4px; color: var(--blue); font-size: 12px; line-height: 1.9; }
        .ac-body ol { counter-reset: n; }
        .ac-body ol > li { position: relative; padding: 6px 0 6px 38px; counter-increment: n; }
        .ac-body ol > li::before { content: counter(n); position: absolute; left: 0; top: 5px; width: 26px; height: 26px; border-radius: 50%; background: linear-gradient(135deg, var(--blue), var(--teal)); color: #fff; font-weight: 700; font-size: 13px; display: grid; place-items: center; }
        .ac-body table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 14px 0; font-size: 14px; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
        .ac-body th { background: #eef3ff; color: var(--navy); text-align: left; padding: 10px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; }
        .ac-body td { padding: 10px 12px; border-top: 1px solid #edf0f7; vertical-align: top; }
        .ac-body tbody tr:nth-child(even) td { background: #fafbfe; }

        /* ---------- avisos coloridos ---------- */
        .mk-note { border-radius: 14px; padding: 13px 16px; margin: 16px 0; border: 1px solid; border-left-width: 5px; font-size: 14.5px; }
        .mk-note > strong:first-child { display: block; margin-bottom: 3px; font-size: 14px; }
        .mk-note p { margin: 4px 0; }
        .mk-note.tip { background: #f0fdf4; border-color: #bbf7d0; border-left-color: #16a34a; } .mk-note.tip > strong:first-child { color: #15803d; }
        .mk-note.warn { background: #fffbeb; border-color: #fde68a; border-left-color: #d97706; } .mk-note.warn > strong:first-child { color: #b45309; }
        .mk-note.who { background: #eff6ff; border-color: #bfdbfe; border-left-color: #2563eb; } .mk-note.who > strong:first-child { color: #1d4ed8; }
        .mk-note.bad { background: #fef2f2; border-color: #fecaca; border-left-color: #dc2626; } .mk-note.bad > strong:first-child { color: #b91c1c; }
        .mk-menu { display: inline-flex; gap: 8px; align-items: center; background: #eef3ff; color: #1e3a8a; border: 1px solid #c7d7fb; border-radius: 999px; padding: 6px 14px; font-weight: 700; font-size: 13.5px; margin: 6px 0 4px; }

        /* ---------- mockups: moldura de tela ---------- */
        .mk-cap { font-size: 12px; color: #94a3b8; margin: -4px 0 14px; text-align: center; }
        .mk-screen { border: 1px solid #cfd8ea; border-radius: 14px; overflow: hidden; background: #fff; margin: 16px 0 6px; box-shadow: 0 8px 24px rgba(15,39,66,.10); font-size: 13px; color: #1e293b; }
        .mk-bar { display: flex; align-items: center; gap: 6px; background: linear-gradient(90deg, #0b1f3a, #12305a); color: #e2ebff; padding: 9px 14px; font-weight: 600; font-size: 12.5px; }
        .mk-bar i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; background: #ef4444; } .mk-bar i:nth-child(2) { background: #f59e0b; } .mk-bar i:nth-child(3) { background: #22c55e; margin-right: 8px; }
        .mk-body { padding: 14px 16px; }
        .mk-tabs { display: flex; gap: 4px; border-bottom: 2px solid #e8edf7; margin: -2px 0 12px; flex-wrap: wrap; }
        .mk-tab { padding: 7px 12px; font-size: 12.5px; color: #64748b; font-weight: 600; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .mk-tab.on { color: #2563eb; border-bottom-color: #2563eb; }
        .mk-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px 14px; }
        .mk-field label { display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 3px; }
        .mk-field div { border: 1px solid #cbd5e1; border-radius: 9px; padding: 8px 10px; background: #f8fafc; font-weight: 600; min-height: 34px; }
        .mk-field.hl div { border-color: #2563eb; background: #eff6ff; color: #1d4ed8; box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .mk-field.full { grid-column: 1 / -1; }
        .mk-btn { display: inline-block; padding: 7px 14px; border-radius: 9px; background: #2563eb; color: #fff; font-weight: 700; font-size: 12.5px; margin: 4px 6px 4px 0; }
        .mk-btn.green { background: #16a34a; } .mk-btn.red { background: #dc2626; } .mk-btn.amber { background: #d97706; } .mk-btn.gray { background: #e2e8f0; color: #334155; } .mk-btn.purple { background: #7c3aed; }
        .mk-chip { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; margin: 2px 4px 2px 0; }
        .mk-chip.green { background: #dcfce7; color: #166534; } .mk-chip.blue { background: #dbeafe; color: #1e40af; } .mk-chip.amber { background: #fef3c7; color: #92400e; }
        .mk-chip.red { background: #fee2e2; color: #991b1b; } .mk-chip.purple { background: #ede9fe; color: #5b21b6; } .mk-chip.gray { background: #e2e8f0; color: #334155; } .mk-chip.teal { background: #ccfbf1; color: #115e59; }
        .mk-table { width: 100%; border-collapse: collapse; font-size: 12.5px; margin: 6px 0; }
        .mk-table th { background: #f1f5f9; color: #475569; text-align: left; padding: 8px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        .mk-table td { padding: 8px 10px; border-top: 1px solid #eef2f7; }
        .mk-table tr.hl td { background: #eff6ff; }
        .mk-hl { background: #fef08a; padding: 0 4px; border-radius: 4px; }

        /* ---------- figuras: telas reais com destaques numerados ---------- */
        .mk-fig { margin: 18px 0 22px; border: 1px solid #cfd8ea; border-radius: 14px; overflow: hidden; background: #fff; box-shadow: 0 8px 24px rgba(15,39,66,.10); }
        .mk-fig img { display: block; width: 100%; height: auto; }
        .mk-phones { display: flex; gap: 22px; justify-content: center; flex-wrap: wrap; margin: 18px 0 22px; align-items: flex-start; }
        .mk-phonefig { width: 250px; max-width: 100%; text-align: center; margin: 0; }
        .mk-phoneframe { border: 8px solid #0b1f3a; border-radius: 30px; overflow: hidden; box-shadow: 0 12px 30px rgba(15,39,66,.25); background: #0b1f3a; }
        .mk-phoneframe img { display: block; width: 100%; height: auto; }
        .mk-phonefig figcaption { margin-top: 10px; font-size: 12.5px; color: #475569; line-height: 1.4; }
        .mk-phonefig figcaption b { display: block; color: #0b1f3a; font-size: 13px; margin-bottom: 2px; }
        .mk-fig.narrow { max-width: 420px; margin-left: auto; margin-right: auto; }
        .mk-fig figcaption { padding: 12px 16px 14px; background: #f8faff; border-top: 1px solid #e3e9f6; font-size: 13.5px; color: #334155; }
        .mk-fig figcaption > b { display: block; color: #0b1f3a; margin-bottom: 6px; font-size: 14px; }
        .mk-legend { list-style: none; margin: 0; padding: 0; counter-reset: lg; }
        .mk-legend li { position: relative; padding: 4px 0 4px 32px; counter-increment: lg; line-height: 1.45; }
        .mk-legend li::before { content: counter(lg); position: absolute; left: 0; top: 3px; width: 22px; height: 22px; border-radius: 50%; background: #ef4444; color: #fff; font-weight: 800; font-size: 12px; display: grid; place-items: center; }

        /* ---------- mockups: fluxo passo a passo ---------- */
        .mk-flow { display: flex; gap: 0; margin: 18px 0; flex-wrap: wrap; }
        .mk-step { flex: 1 1 150px; min-width: 150px; position: relative; padding: 14px 14px 14px 16px; background: #fff; border: 1px solid #d6e0f5; border-radius: 14px; margin: 0 14px 12px 0; box-shadow: 0 2px 8px rgba(15,39,66,.05); }
        .mk-step::after { content: "➜"; position: absolute; right: -17px; top: 50%; transform: translateY(-50%); color: #2563eb; font-size: 15px; font-weight: 700; }
        .mk-step:last-child { margin-right: 0; } .mk-step:last-child::after { display: none; }
        .mk-step .n { display: inline-grid; place-items: center; width: 26px; height: 26px; border-radius: 50%; color: #fff; font-weight: 800; font-size: 13px; background: linear-gradient(135deg, #2563eb, #0ea5a4); margin-bottom: 6px; }
        .mk-step b { display: block; font-size: 13.5px; color: #0b1f3a; margin-bottom: 2px; }
        .mk-step span.t { font-size: 12.5px; color: #64748b; line-height: 1.4; display: block; }
        .mk-step.green { border-color: #86efac; background: #f0fdf4; } .mk-step.amber { border-color: #fcd34d; background: #fffbeb; } .mk-step.red { border-color: #fca5a5; background: #fef2f2; } .mk-step.purple { border-color: #c4b5fd; background: #f5f3ff; }
        .mk-vflow { margin: 16px 0; border-left: 3px solid #dbe6ff; padding-left: 18px; }
        .mk-vflow > div { position: relative; padding: 4px 0 14px; }
        .mk-vflow > div::before { content: ""; position: absolute; left: -26px; top: 6px; width: 14px; height: 14px; border-radius: 50%; background: #2563eb; border: 3px solid #fff; box-shadow: 0 0 0 2px #2563eb; }
        .mk-vflow > div.green::before { background: #16a34a; box-shadow: 0 0 0 2px #16a34a; } .mk-vflow > div.amber::before { background: #d97706; box-shadow: 0 0 0 2px #d97706; } .mk-vflow > div.red::before { background: #dc2626; box-shadow: 0 0 0 2px #dc2626; } .mk-vflow > div.purple::before { background: #7c3aed; box-shadow: 0 0 0 2px #7c3aed; }
        .mk-vflow b { color: #0b1f3a; font-size: 14px; }

        /* ---------- mockups: kanban, celular, indicadores ---------- */
        .mk-kanban { display: flex; gap: 10px; overflow-x: auto; padding: 4px 2px 8px; }
        .mk-col { flex: 0 0 150px; background: #f1f5f9; border-radius: 12px; padding: 8px; }
        .mk-col h4 { margin: 2px 4px 8px; font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; color: #475569; display: flex; justify-content: space-between; }
        .mk-col.bottleneck { background: #fff7ed; outline: 2px dashed #fb923c; }
        .mk-card { background: #fff; border-radius: 9px; padding: 8px 9px; margin-bottom: 7px; box-shadow: 0 1px 3px rgba(15,39,66,.12); font-size: 12px; border-left: 4px solid #2563eb; }
        .mk-card.urgent { border-left-color: #dc2626; background: #fef2f2; } .mk-card b { display: block; color: #0b1f3a; }
        .mk-phone { width: 270px; max-width: 100%; margin: 14px auto; border: 8px solid #0b1f3a; border-radius: 30px; background: #f8fafc; box-shadow: 0 12px 30px rgba(15,39,66,.25); overflow: hidden; }
        .mk-phone .mk-ph-top { background: #0b1f3a; color: #fff; text-align: center; padding: 8px; font-size: 12px; font-weight: 700; }
        .mk-phone .mk-ph-body { padding: 12px; font-size: 12.5px; }
        .mk-phone .mk-ph-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 8px 10px; margin-bottom: 8px; display: flex; justify-content: space-between; gap: 8px; align-items: center; }
        .mk-phones { display: flex; gap: 18px; justify-content: center; flex-wrap: wrap; }
        .mk-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin: 8px 0; }
        .mk-kpi { border-radius: 11px; padding: 10px 12px; border: 1px solid #e2e8f0; background: #fff; }
        .mk-kpi small { display: block; font-size: 11px; color: #64748b; font-weight: 600; } .mk-kpi strong { font-size: 22px; color: #0b1f3a; }
        .mk-kpi.green { background: #f0fdf4; border-color: #bbf7d0; } .mk-kpi.blue { background: #eff6ff; border-color: #bfdbfe; } .mk-kpi.amber { background: #fffbeb; border-color: #fde68a; } .mk-kpi.red { background: #fef2f2; border-color: #fecaca; } .mk-kpi.purple { background: #f5f3ff; border-color: #ddd6fe; }
        .mk-prog { height: 9px; background: #e2e8f0; border-radius: 99px; overflow: hidden; margin: 4px 0 8px; } .mk-prog div { height: 9px; border-radius: 99px; }
        .mk-prog .g { background: #16a34a; } .mk-prog .a { background: #d97706; } .mk-prog .r { background: #dc2626; } .mk-prog .b { background: #2563eb; }
        .mk-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin: 14px 0; }
        .mk-card2 { border: 1px solid #dbe3f3; border-radius: 14px; padding: 14px 16px; background: #fff; box-shadow: 0 2px 8px rgba(15,39,66,.05); }
        .mk-card2 h4 { margin: 0 0 6px; font-size: 14.5px; color: #0b1f3a; } .mk-card2.green { border-top: 4px solid #16a34a; } .mk-card2.blue { border-top: 4px solid #2563eb; } .mk-card2.amber { border-top: 4px solid #d97706; } .mk-card2.red { border-top: 4px solid #dc2626; } .mk-card2.purple { border-top: 4px solid #7c3aed; }
        .mk-check { list-style: none; margin: 8px 0; padding: 0; } .mk-check li { padding: 6px 10px 6px 34px; position: relative; border-radius: 9px; margin: 4px 0; background: #f8fafc; } .mk-check li::before { content: "✓"; position: absolute; left: 10px; top: 5px; width: 18px; height: 18px; border-radius: 50%; background: #16a34a; color: #fff; display: grid; place-items: center; font-size: 11px; font-weight: 800; }
        .mk-check li.no::before { content: "✕"; background: #dc2626; } .mk-check li.wait::before { content: "…"; background: #d97706; }
        .mk-seq { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin: 10px 0; } .mk-seq .arr { color: #2563eb; font-weight: 800; }
        @media (max-width: 700px) { .mk-step { flex-basis: 100%; margin-right: 0; } .mk-step::after { display: none; } }
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
        @auth <button class="ac-burger" type="button" @click="open = !open" aria-label="Menu">☰</button> @endauth
        <a class="ac-brand" href="{{ url('/academia') }}"><i></i>Oravel <span>Academy</span></a>
        <div class="ac-top-right">
            @auth
                <a href="{{ url('/admin') }}">← Voltar<span class="ac-hide-sm"> ao sistema</span></a>
                <span class="ac-user">{{ auth()->user()->name }}</span>
            @else
                <a href="{{ url('/academia/inicio') }}" style="background:#fff;color:#0b1f3a;padding:7px 16px;border-radius:999px;font-weight:700">Entrar</a>
            @endauth
        </div>
    </header>

    <div class="ac-shell">
        @auth
            <aside class="ac-side" :class="{ 'ac-open': open }" @click="if ($event.target.closest('a')) open = false">
                <livewire:academy.sidebar />
            </aside>
            <div class="ac-backdrop" :class="{ 'ac-open': open }" @click="open = false"></div>
        @endauth
        <main class="ac-main" @guest style="margin-left:0;max-width:1100px;margin-inline:auto;width:100%" @endguest>{{ $slot }}</main>
    </div>

    @livewireScripts
</body>
</html>
