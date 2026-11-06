"""Mockups da home v2: telas ilustrativas ricas (dashboard, funil, mapa, IA no celular, implantação), cada uma com layout próprio.

Tudo em HTML/CSS/SVG puro (sem imagem, sem JS): carrega rápido e acompanha o tema. Os dados são fictícios e coerentes entre si
(mesma locadora de exemplo em todas as telas). Classes com prefixo v2- para não colidir com o CSS de tema.py.
"""
import math

# ------------------------------------------------------------------ CSS
CSS_V2 = """
/* ---- app (visual do produto: escuro) ---- */
.v2-app{--bg:#0b1220;--p:#111a2e;--p2:#16213a;--ln:rgba(148,163,184,.16);--tx:#e2e8f0;--mut:#94a3b8;--or:#f97316;--gr:#22c55e;--am:#f59e0b;--rd:#ef4444;--bl:#38bdf8;--vi:#a78bfa;
background:var(--bg);color:var(--tx);border:1px solid rgba(148,163,184,.28);border-radius:16px;overflow:hidden;font-size:12.5px;line-height:1.35;box-shadow:0 50px 90px -40px rgba(2,6,23,.85),0 0 0 1px rgba(255,255,255,.04)}
.v2-app *{box-sizing:border-box}
.v2-bar{display:flex;align-items:center;gap:6px;padding:10px 14px;background:#0a101d;border-bottom:1px solid var(--ln)}
.v2-bar i{width:10px;height:10px;border-radius:50%;background:#334155}.v2-bar i:nth-child(1){background:#f87171}.v2-bar i:nth-child(2){background:#fbbf24}.v2-bar i:nth-child(3){background:#4ade80}
.v2-bar b{margin-left:10px;font-weight:600;color:var(--mut);font-size:11.5px;background:#0f1a30;border:1px solid var(--ln);border-radius:7px;padding:3px 12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
.v2-bar em{margin-left:auto;font-style:normal;font-size:11px;font-weight:700;color:#0b1220;background:var(--or);border-radius:999px;padding:3px 10px;white-space:nowrap}
.v2-body{padding:14px;display:grid;gap:12px}
.v2-row{display:grid;gap:12px;min-width:0}
.v2-card{background:var(--p);border:1px solid var(--ln);border-radius:12px;padding:12px 13px;min-width:0}
.v2-card h6{margin:0 0 9px;font:800 11px/1.2 "Manrope","Inter",sans-serif;letter-spacing:.06em;text-transform:uppercase;color:var(--mut);display:flex;justify-content:space-between;gap:8px}
.v2-card h6 span{font-weight:600;letter-spacing:0;text-transform:none;color:#64748b}
.v2-kpi small{display:block;color:var(--mut);font-size:11px;font-weight:600}
.v2-kpi b{display:block;font:800 23px/1.1 "Manrope","Inter",sans-serif;letter-spacing:-.02em;margin:3px 0 2px;color:#fff;white-space:nowrap}
.v2-kpi .d{font-size:11px;font-weight:700}.d.up{color:var(--gr)}.d.dn{color:var(--rd)}.d.n{color:var(--mut)}
.v2-kpi svg{display:block;width:100%;height:30px;margin-top:6px}
.v2-bar2{height:6px;border-radius:99px;background:#1e293b;overflow:hidden;margin-top:8px}.v2-bar2 i{display:block;height:100%;border-radius:99px;background:var(--or)}
.v2-fun{display:grid;gap:5px}
.v2-fun div{display:flex;justify-content:space-between;align-items:center;gap:8px;padding:7px 10px;border-radius:7px;color:#0b1220;font-weight:800;font-size:11.5px;margin:0 auto;white-space:nowrap}
.v2-fun em{font-style:normal;font-weight:700;opacity:.75;font-size:10.5px;margin-left:8px}
.v2-lg{display:grid;gap:6px;margin:0;padding:0;list-style:none;font-size:11.5px}
.v2-lg li{display:flex;align-items:center;gap:8px}.v2-lg i{width:9px;height:9px;border-radius:3px;flex:none}.v2-lg b{margin-left:auto;font-variant-numeric:tabular-nums}
.v2-al{display:grid;gap:7px}
.v2-al div{display:flex;gap:9px;align-items:flex-start;padding:8px 9px;border-radius:8px;background:var(--p2);border:1px solid var(--ln)}
.v2-al b{display:block;font-size:12px;color:#fff}.v2-al small{color:var(--mut);font-size:11px}
.v2-tg{display:inline-block;flex:none;font-size:9.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;border-radius:6px;padding:2px 7px;white-space:nowrap}
.tg-r{background:rgba(239,68,68,.18);color:#fca5a5}.tg-a{background:rgba(245,158,11,.18);color:#fcd34d}.tg-g{background:rgba(34,197,94,.18);color:#86efac}.tg-b{background:rgba(56,189,248,.18);color:#7dd3fc}.tg-o{background:rgba(249,115,22,.2);color:#fdba74}.tg-v{background:rgba(167,139,250,.2);color:#c4b5fd}
.v2-ia{background:linear-gradient(135deg,rgba(249,115,22,.16),rgba(167,139,250,.14)),var(--p);border-color:rgba(249,115,22,.4)}
.v2-ia p{margin:0;color:#e2e8f0;font-size:12px}.v2-ia p b{color:#fff}
.v2-ia .ch{display:inline-flex;align-items:center;gap:6px;font:800 10.5px "Manrope",sans-serif;letter-spacing:.06em;text-transform:uppercase;color:#fdba74;margin-bottom:6px}
.v2-ia .ch::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--or);box-shadow:0 0 0 4px rgba(249,115,22,.25)}
.v2-nota{font-size:12px;color:#94a3b8;margin-top:12px}
/* hero: grade do painel (se adapta a largura do proprio painel, nao da janela) */
.hd-k{grid-template-columns:repeat(auto-fit,minmax(132px,1fr))}
.hd-2{grid-template-columns:repeat(auto-fit,minmax(min(100%,260px),1fr))}.hd-2>:first-child{grid-column:span 1}
.hd-3{grid-template-columns:repeat(auto-fit,minmax(min(100%,190px),1fr))}
@media(min-width:900px){.hd-2{grid-template-columns:1.5fr 1fr}.hd-3{grid-template-columns:.9fr 1.1fr 1.1fr}}
/* ---- CRM: funil em colunas (claro) ---- */
.v2-crm{background:#f8fafc;border:1px solid var(--line);border-radius:16px;box-shadow:0 40px 70px -40px rgba(15,23,42,.5);overflow:hidden;font-size:12.5px;color:var(--ink)}
.v2-ct{display:flex;flex-wrap:wrap;gap:10px 22px;padding:13px 16px;background:#fff;border-bottom:1px solid var(--line);align-items:center}
.v2-ct h5{margin:0;font:800 14px "Manrope",sans-serif}.v2-ct span{color:var(--ink-soft);font-size:12px}.v2-ct b{color:var(--ink);font-variant-numeric:tabular-nums}
.v2-ct .pill{margin-left:auto;background:var(--accent-soft);color:var(--accent-ink);border-radius:999px;padding:4px 11px;font-weight:800;font-size:11.5px}
.v2-kb{display:grid;gap:10px;padding:12px;overflow-x:auto;grid-template-columns:repeat(4,minmax(176px,1fr))}
.v2-col{background:#eef1f6;border-radius:12px;padding:9px;display:grid;gap:8px;align-content:start}
.v2-col h6{margin:2px 3px 2px;font:800 11px "Manrope",sans-serif;letter-spacing:.05em;text-transform:uppercase;color:#475569;display:flex;justify-content:space-between}
.v2-col h6 em{font-style:normal;color:#64748b;font-weight:700}
.v2-lead{background:#fff;border:1px solid var(--line);border-radius:10px;padding:9px 10px;box-shadow:0 1px 0 rgba(15,23,42,.04)}
.v2-lead b{display:block;font-size:12.5px;line-height:1.25}.v2-lead small{display:block;color:var(--ink-soft);font-size:11.5px;margin:2px 0 7px}
.v2-lead .f{display:flex;align-items:center;justify-content:space-between;gap:6px}
.v2-lead .v{font-weight:800;font-variant-numeric:tabular-nums}
.v2-lead .av{width:22px;height:22px;border-radius:50%;background:#0f172a;color:#fff;font:800 9.5px/22px "Manrope",sans-serif;text-align:center;flex:none}
.v2-lead .mq{display:inline-block;font-size:10px;font-weight:800;border-radius:6px;padding:2px 6px;margin-bottom:6px}
.mq-w{background:#fee2e2;color:#b91c1c}.mq-y{background:#fef3c7;color:#92400e}.mq-g{background:#dcfce7;color:#166534}.mq-b{background:#e0f2fe;color:#075985}
.v2-lead.ok{border-color:#86efac;background:#f0fdf4}
.v2-src{display:flex;flex-wrap:wrap;gap:6px;padding:0 12px 14px}.v2-src span{font-size:11.5px;color:var(--ink-soft);background:#fff;border:1px solid var(--line);border-radius:999px;padding:4px 10px}.v2-src b{color:var(--ink)}
/* ---- mapa + custo por equipamento (escuro) ---- */
.v2-map{display:grid;gap:12px;grid-template-columns:1fr}@media(min-width:620px){.v2-map{grid-template-columns:1.25fr 1fr}}
.v2-map svg.mp{display:block;width:100%;height:auto;background:#0d1628;border-radius:10px}
.v2-rk{display:grid;gap:9px}
.v2-rk div.r{display:grid;gap:4px}.v2-rk .l{display:flex;justify-content:space-between;gap:8px;align-items:center}.v2-rk .l b{font-size:12px;color:#fff}.v2-rk .l span{color:var(--mut);font-size:11px;white-space:nowrap}
.v2-rk .t{height:7px;border-radius:99px;background:#1e293b;overflow:hidden;position:relative}.v2-rk .t i{display:block;height:100%;border-radius:99px}
.v2-tl{list-style:none;margin:0;padding:0 0 0 12px;border-left:2px solid #1e293b;display:grid;gap:8px}
.v2-tl li{position:relative;padding-left:12px;font-size:11.5px;color:var(--tx)}.v2-tl li::before{content:"";position:absolute;left:-19px;top:3px;width:10px;height:10px;border-radius:50%;background:var(--or);border:2px solid var(--p)}
.v2-tl li.g::before{background:var(--gr)}.v2-tl li.r::before{background:var(--rd)}.v2-tl li.b::before{background:var(--bl)}
.v2-tl small{display:block;color:var(--mut);font-size:10.5px}
/* ---- celular com IA ---- */
.v2-ph{width:min(310px,100%);margin:0 auto;background:#0b1220;border:7px solid #1e293b;border-radius:38px;box-shadow:0 50px 80px -40px rgba(2,6,23,.8);overflow:hidden;color:#e2e8f0;font-size:12.5px;position:relative}
.v2-ph::before{content:"";display:block;width:84px;height:18px;margin:0 auto;background:#1e293b;border-radius:0 0 14px 14px}
.v2-ph .hd{padding:10px 16px 12px;border-bottom:1px solid rgba(148,163,184,.15);display:flex;justify-content:space-between;align-items:center}
.v2-ph .hd b{font:800 14px "Manrope",sans-serif}.v2-ph .hd span{font-size:10.5px;font-weight:800;color:#0b1220;background:#f97316;border-radius:999px;padding:3px 9px}
.v2-ph .bd{padding:12px;display:grid;gap:10px}
.v2-bub{max-width:88%;padding:9px 11px;border-radius:14px;line-height:1.4}
.v2-bub.u{background:#1e293b;justify-self:end;border-bottom-right-radius:4px}
.v2-bub.i{background:linear-gradient(135deg,rgba(249,115,22,.22),rgba(167,139,250,.18));border:1px solid rgba(249,115,22,.35);border-bottom-left-radius:4px}
.v2-bub.i b{color:#fff}.v2-bub .lb{display:block;font:800 9.5px "Manrope",sans-serif;letter-spacing:.07em;text-transform:uppercase;color:#fdba74;margin-bottom:4px}
.v2-bub ol{margin:6px 0 0;padding-left:17px;font-size:12px}.v2-bub li{margin-bottom:2px}
.v2-task{display:grid;grid-template-columns:auto 1fr auto;gap:9px;align-items:center;background:#111a2e;border:1px solid rgba(148,163,184,.16);border-radius:11px;padding:9px 10px}
.v2-task .n{width:22px;height:22px;border-radius:7px;background:#f97316;color:#0b1220;font:800 11px/22px "Manrope",sans-serif;text-align:center}
.v2-task b{display:block;font-size:12px;color:#fff}.v2-task small{color:#94a3b8;font-size:10.5px}
.v2-ph .ft{margin:2px 12px 14px;background:#f97316;color:#0b1220;font-weight:800;text-align:center;border-radius:12px;padding:11px;font-size:13px}
/* ---- implantacao (claro, outro layout) ---- */
.v2-imp{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 40px 70px -40px rgba(15,23,42,.5);overflow:hidden;color:var(--ink);font-size:12.5px}
.v2-imp .hd{display:flex;gap:12px;align-items:center;padding:14px 16px;border-bottom:1px solid var(--line);background:#f8fafc}
.v2-imp .av{width:40px;height:40px;border-radius:12px;background:var(--navy);color:#fff;font:800 14px/40px "Manrope",sans-serif;text-align:center;flex:none}
.v2-imp .hd b{display:block;font:800 14px "Manrope",sans-serif}.v2-imp .hd small{color:var(--ink-soft)}
.v2-imp .hd .sel{margin-left:auto;font-size:11px;font-weight:800;color:#166534;background:#dcfce7;border-radius:999px;padding:4px 10px;white-space:nowrap}
.v2-sem{display:grid;gap:0;padding:6px 16px 4px}
.v2-sem .s{display:grid;grid-template-columns:76px 1fr;gap:12px;padding:11px 0;border-bottom:1px dashed var(--line);align-items:start}.v2-sem .s:last-child{border-bottom:0}
.v2-sem .w{font:800 11px "Manrope",sans-serif;letter-spacing:.05em;text-transform:uppercase;color:var(--accent-ink);padding-top:2px}
.v2-sem b{display:block;font-size:13px}.v2-sem small{display:block;color:var(--ink-soft);margin-top:2px}
.v2-sem .dn .w{color:#16a34a}
.v2-ad{padding:4px 16px 16px;display:grid;gap:9px}
.v2-ad h6{margin:6px 0 2px;font:800 11px "Manrope",sans-serif;letter-spacing:.06em;text-transform:uppercase;color:#64748b}
.v2-ad .r{display:grid;grid-template-columns:96px 1fr 38px;gap:10px;align-items:center}.v2-ad .r span{font-weight:600}
.v2-ad .t{height:8px;border-radius:99px;background:#e8ecf2;overflow:hidden}.v2-ad .t i{display:block;height:100%;background:var(--accent);border-radius:99px}
.v2-ad .r b{text-align:right;font-variant-numeric:tabular-nums}
.v2-imp .chips{display:flex;flex-wrap:wrap;gap:6px;padding:0 16px 16px}.v2-imp .chips span{font-size:11.5px;font-weight:600;border:1px solid var(--line);border-radius:999px;padding:4px 10px;color:var(--ink-soft);background:#f8fafc}
/* ---- secoes novas ---- */
.v2-dor{display:grid;gap:16px}@media(min-width:760px){.v2-dor{grid-template-columns:repeat(2,1fr)}}@media(min-width:1100px){.v2-dor{grid-template-columns:repeat(4,1fr)}}
.v2-dor .p{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px;display:grid;gap:10px;align-content:start}
.v2-dor q{font:800 18px/1.25 "Manrope",sans-serif;letter-spacing:-.01em;quotes:none;display:block}
.v2-dor .a{font-size:14.5px;color:var(--ink-soft)}
.v2-dor .a b{color:var(--accent-ink)}
.v2-prova{display:grid;gap:18px;margin-top:34px}@media(min-width:800px){.v2-prova{grid-template-columns:repeat(3,1fr)}}
.v2-prova div{border-left:3px solid var(--accent);padding:2px 0 2px 16px}.v2-prova b{display:block;font:800 17px "Manrope",sans-serif}.v2-prova span{color:var(--ink-soft);font-size:15px}
.v2-split{display:grid;gap:40px;align-items:center;grid-template-columns:minmax(0,1fr)}.v2-split>*{min-width:0}@media(min-width:1000px){.v2-split{grid-template-columns:minmax(0,.9fr) minmax(0,1.25fr);gap:60px}.v2-split.rev{grid-template-columns:minmax(0,1.25fr) minmax(0,.9fr)}.v2-split.rev .t{order:2}}
.v2-split h2{font-size:clamp(26px,3.6vw,38px);line-height:1.12;margin:10px 0 16px}.v2-split p{color:var(--ink-soft);font-size:17px}
.v2-split.dk h2{color:#fff}.v2-split.dk p{color:rgba(226,232,240,.85)}
.v2-ck{list-style:none;padding:0;margin:20px 0 0;display:grid;gap:11px}
.v2-ck li{display:grid;grid-template-columns:22px 1fr;gap:10px;font-size:15.5px;line-height:1.45}
.v2-ck li::before{content:"";width:22px;height:22px;border-radius:7px;background:var(--accent-soft) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c2410c' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7'/%3E%3C/svg%3E") center/14px no-repeat}
.v2-ck b{color:var(--ink)}
.dk .v2-ck li{color:rgba(226,232,240,.92)}.dk .v2-ck b{color:#fff}
.v2-ck.dk li::before{background-color:rgba(249,115,22,.22)}
.v2-nums{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-top:26px}
.v2-nums div{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:14px 16px}.v2-nums b{display:block;white-space:nowrap;font:800 26px "Manrope",sans-serif;color:var(--accent-ink);letter-spacing:-.02em}.v2-nums span{font-size:13px;color:var(--ink-soft)}
.sec.dark .v2-nums div{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.14)}.sec.dark .v2-nums b{color:#fdba74}.sec.dark .v2-nums span{color:rgba(226,232,240,.8)}
"""


# ------------------------------------------------------------------ helpers de desenho
def _pts(vals, w=160, h=30, pad=3):
    mx, mn = max(vals), min(vals)
    span = (mx - mn) or 1
    passo = (w - 2 * pad) / (len(vals) - 1)
    return [(pad + i * passo, h - pad - (v - mn) / span * (h - 2 * pad)) for i, v in enumerate(vals)]


def spark(vals, cor='#f97316', w=160, h=30):
    p = _pts(vals, w, h)
    linha = ' '.join(f'{x:.1f},{y:.1f}' for x, y in p)
    area = f'{p[0][0]:.1f},{h} ' + linha + f' {p[-1][0]:.1f},{h}'
    uid = 'g' + cor.strip('#') + str(abs(hash(tuple(vals))) % 9999)
    return (f'<svg viewBox="0 0 {w} {h}" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="{uid}" x1="0" y1="0" x2="0" y2="1">'
            f'<stop offset="0" stop-color="{cor}" stop-opacity=".35"/><stop offset="1" stop-color="{cor}" stop-opacity="0"/></linearGradient></defs>'
            f'<polygon points="{area}" fill="url(#{uid})"/><polyline points="{linha}" fill="none" stroke="{cor}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>'
            f'<circle cx="{p[-1][0]:.1f}" cy="{p[-1][1]:.1f}" r="2.8" fill="{cor}"/></svg>')


def donut(partes, tamanho=96, raio=36, esp=13):
    """partes: [(rotulo, valor, cor)]. Devolve (svg, legenda)."""
    total = sum(v for _, v, _ in partes)
    c = 2 * math.pi * raio
    off, arcos = 0.0, ''
    for _, v, cor in partes:
        seg = v / total * c
        arcos += f'<circle r="{raio}" cx="{tamanho/2}" cy="{tamanho/2}" fill="none" stroke="{cor}" stroke-width="{esp}" stroke-dasharray="{seg-1.5:.2f} {c-seg+1.5:.2f}" stroke-dashoffset="{-off:.2f}" transform="rotate(-90 {tamanho/2} {tamanho/2})"/>'
        off += seg
    svg = (f'<svg viewBox="0 0 {tamanho} {tamanho}" width="{tamanho}" height="{tamanho}" aria-hidden="true">{arcos}'
           f'<text x="{tamanho/2}" y="{tamanho/2-1}" text-anchor="middle" fill="#fff" font-family="Manrope,sans-serif" font-weight="800" font-size="17">{total}</text>'
           f'<text x="{tamanho/2}" y="{tamanho/2+12}" text-anchor="middle" fill="#94a3b8" font-family="Inter,sans-serif" font-size="8.5">equip.</text></svg>')
    leg = '<ul class="v2-lg">' + ''.join(f'<li><i style="background:{cor}"></i>{r}<b>{round(v/total*100)}%</b></li>' for r, v, cor in partes) + '</ul>'
    return svg, leg


def barras_receita():
    meses = ['Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov']
    rec = [286, 301, 322, 340, 371, 412]
    cus = [52, 49, 57, 44, 41, 38]
    W, H, base, topo = 360, 150, 128, 14
    esc = (base - topo) / 440
    g = ''
    for y in (0, 110, 220, 330, 440):
        yy = base - y * esc
        g += f'<line x1="26" x2="{W}" y1="{yy:.1f}" y2="{yy:.1f}" stroke="#1e293b" stroke-width="1"/><text x="20" y="{yy+3:.1f}" text-anchor="end" fill="#64748b" font-size="8.5" font-family="Inter,sans-serif">{y}</text>'
    bw = 24
    for i, (m, r, c) in enumerate(zip(meses, rec, cus)):
        x = 42 + i * 55
        op = ' opacity=".55"' if i < 5 else ''
        g += f'<rect x="{x}" y="{base - r*esc:.1f}" width="{bw}" height="{r*esc:.1f}" rx="4" fill="#f97316"{op}/>'
        g += f'<rect x="{x+bw+3}" y="{base - c*esc:.1f}" width="{bw-8}" height="{c*esc:.1f}" rx="3" fill="#38bdf8"/>'
        g += f'<text x="{x+bw}" y="{base+14}" text-anchor="middle" fill="#94a3b8" font-size="9.5" font-family="Inter,sans-serif">{m}</text>'
    # linha de margem
    pts = ' '.join(f'{42+i*55+bw:.1f},{base - (r - c)*esc:.1f}' for i, (r, c) in enumerate(zip(rec, cus)))
    g += f'<polyline points="{pts}" fill="none" stroke="#22c55e" stroke-width="2" stroke-dasharray="4 3" stroke-linecap="round"/>'
    return (f'<svg viewBox="0 0 {W} {H}" width="100%" role="img" aria-label="Receita e custo de manutenção nos últimos seis meses">{g}</svg>'
            '<ul class="v2-lg" style="grid-auto-flow:column;justify-content:start;gap:16px;margin-top:4px"><li><i style="background:#f97316"></i>Receita (R$ mil)</li>'
            '<li><i style="background:#38bdf8"></i>Custo de manutenção</li><li><i style="background:#22c55e"></i>Saldo</li></ul>')


# ------------------------------------------------------------------ mockups
def hero_dashboard():
    k1 = spark([286, 301, 322, 340, 371, 412], '#f97316')
    k3 = spark([52, 49, 57, 44, 41, 38], '#38bdf8')
    k4 = spark([9, 14, 11, 18, 24, 22], '#ef4444')
    d_svg, d_leg = donut([('Locado', 13, '#22c55e'), ('Disponível', 8, '#38bdf8'), ('Em manutenção', 4, '#f59e0b')])
    return (
        '<div class="v2-app" role="img" aria-label="Painel do dono da locadora: receita, taxa de locação, custo de manutenção, funil de vendas, frota e alertas">'
        '<div class="v2-bar"><i></i><i></i><i></i><b>app.oravel.com.br · Visão do dono</b><em>Novembro</em></div><div class="v2-body">'
        '<div class="v2-row hd-k">'
        f'<div class="v2-card v2-kpi"><small>Receita do mês</small><b>R$ 412 mil</b><span class="d up">▲ 11% sobre outubro</span>{k1}</div>'
        '<div class="v2-card v2-kpi"><small>Taxa de locação</small><b>78%</b><span class="d up">▲ 6 pontos em 90 dias</span><div class="v2-bar2"><i style="width:78%"></i></div></div>'
        f'<div class="v2-card v2-kpi"><small>Custo de manutenção</small><b>R$ 38,4 mil</b><span class="d up">▼ 12% e 5,6% da receita</span>{k3}</div>'
        f'<div class="v2-card v2-kpi"><small>Contas em atraso</small><b>R$ 21,6 mil</b><span class="d dn">3 clientes · 1 acima de 30 dias</span>{k4}</div></div>'
        '<div class="v2-row hd-2">'
        f'<div class="v2-card"><h6>Receita x custo de manutenção <span>6 meses</span></h6>{barras_receita()}</div>'
        '<div class="v2-card"><h6>Funil de vendas <span>conversão 21%</span></h6><div class="v2-fun">'
        '<div style="width:100%;background:#fdba74">Leads<em>36</em></div><div style="width:90%;background:#fb923c">Propostas<em>14 · 39%</em></div>'
        '<div style="width:80%;background:#f97316">Negociação<em>7 · 50%</em></div><div style="width:70%;background:#22c55e">Fechado<em>5 · 71%</em></div></div>'
        '<p style="margin:10px 0 12px;color:#94a3b8;font-size:11px">Previsão do mês: <b style="color:#fff">R$ 640 mil</b></p>'
        '<div class="v2-al"><div><span class="v2-tg tg-r">3</span><span><b>Propostas sem retorno</b><small>R$ 355 mil parados há mais de 5 dias</small></span></div></div></div></div>'
        '<div class="v2-row hd-3">'
        f'<div class="v2-card"><h6>Onde está a frota</h6><div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">{d_svg}{d_leg}</div></div>'
        '<div class="v2-card"><h6>O que pede atenção <span>hoje</span></h6><div class="v2-al">'
        '<div><span class="v2-tg tg-r">Parado</span><span><b>Gerador 150 kVA</b><small>9 dias na oficina, aguardando peça</small></span></div>'
        '<div><span class="v2-tg tg-a">Vence</span><span><b>Revisão do Guindaste 01</b><small>em 3 dias, por horímetro</small></span></div>'
        '<div><span class="v2-tg tg-b">Retorno</span><span><b>Proposta Construtora Alvorada</b><small>R$ 177 mil sem resposta há 5 dias</small></span></div></div></div>'
        '<div class="v2-card v2-ia"><div class="ch">IA da Oravel</div><p><b>Gerador 150 kVA já custou 91% do valor.</b> Vale avaliar a troca ou a venda antes da próxima corretiva.</p>'
        '<p style="margin-top:9px"><b>Compressor 02 voltou 2 vezes por vazamento.</b> Programe uma revisão preventiva no cabeçote.</p>'
        '<p style="margin-top:9px"><b>Proposta da Construtora Alvorada está parada.</b> Sugestão: ligar hoje, antes de o prazo vencer.</p>'
        '<p style="margin-top:10px;color:#94a3b8;font-size:11px">Os números vêm do sistema; a IA interpreta e prioriza.</p></div></div>'
        '</div></div>')


def mk_crm():
    def lead(nome, eq, valor, quem, marca=None, tom='y', ok=False):
        m = f'<span class="mq mq-{tom}">{marca}</span>' if marca else ''
        return (f'<div class="v2-lead{" ok" if ok else ""}">{m}<b>{nome}</b><small>{eq}</small><div class="f"><span class="v">{valor}</span><span class="av">{quem}</span></div></div>')
    return (
        '<div class="v2-crm" role="img" aria-label="Funil de vendas em colunas, com leads, propostas e negociações"><div class="v2-ct"><h5>Funil de vendas</h5>'
        '<span>Em aberto <b>R$ 2,9 mi</b></span><span>Previsão do mês <b>R$ 640 mil</b></span><span>Conversão <b>21%</b></span><span class="pill">3 follow-ups hoje</span></div>'
        '<div class="v2-kb">'
        '<div class="v2-col"><h6>Novo contato <em>8</em></h6>'
        + lead('Incorporadora Zênite', 'Plataforma tesoura 10 m · 60 dias', 'R$ 127 mil', 'FL', 'Retornar hoje', 'b')
        + lead('Agro Rio Claro', 'Gerador 40 kVA para evento', 'R$ 58 mil', 'JP')
        + '</div><div class="v2-col"><h6>Proposta enviada <em>9</em></h6>'
        + lead('Construtora Alvorada', 'Torre de iluminação · obra noturna', 'R$ 177 mil', 'RM', 'Sem retorno há 5 dias', 'w')
        + lead('Transportes Nova Era', 'Munck 12 t · 3 meses', 'R$ 178 mil', 'FL', 'Vence amanhã', 'y')
        + '</div><div class="v2-col"><h6>Negociação <em>7</em></h6>'
        + lead('Engenharia Tupã', 'Compressor para jateamento', 'R$ 20 mil', 'RM', 'Retornar hoje', 'b')
        + lead('Eventos Diamante', 'Plataforma articulada 15 m', 'R$ 96 mil', 'JP')
        + '</div><div class="v2-col"><h6>Fechado no mês <em>5</em></h6>'
        + lead('Montagens Jatobá', 'Torre de iluminação · obra noturna', 'R$ 133 mil', 'FL', 'Contrato gerado', 'g', True)
        + lead('Construtora Aurora', 'Compressor de ar para jateamento', 'R$ 36,6 mil', 'JP', 'Contrato gerado', 'g', True)
        + '</div></div><div class="v2-src"><span>Origem dos leads: <b>indicação 28%</b></span><span><b>site 36%</b></span><span><b>contato frio 17%</b></span><span><b>evento 8%</b></span></div></div>')


def mk_dono():
    # mapa estilizado (sem imagem): malha, rodovias e pinos coloridos por situação
    pinos = [(70, 92, '#22c55e'), (112, 70, '#22c55e'), (150, 112, '#f59e0b'), (196, 60, '#22c55e'), (232, 98, '#38bdf8'), (264, 134, '#22c55e'),
             (98, 138, '#ef4444'), (176, 150, '#38bdf8'), (300, 76, '#22c55e'), (324, 116, '#22c55e'), (48, 128, '#38bdf8'), (214, 138, '#22c55e')]
    grade = ''.join(f'<line x1="{x}" y1="0" x2="{x}" y2="180" stroke="#16223d" stroke-width="1"/>' for x in range(0, 361, 40)) + \
        ''.join(f'<line x1="0" y1="{y}" x2="360" y2="{y}" stroke="#16223d" stroke-width="1"/>' for y in range(0, 181, 40))
    rod = ('<path d="M0 118 C70 96 120 140 190 100 S300 70 360 92" fill="none" stroke="#2a3a5c" stroke-width="5" stroke-linecap="round"/>'
           '<path d="M0 118 C70 96 120 140 190 100 S300 70 360 92" fill="none" stroke="#0d1628" stroke-width="1.6" stroke-dasharray="6 6"/>'
           '<path d="M120 0 C130 50 170 90 190 100 S230 150 250 180" fill="none" stroke="#2a3a5c" stroke-width="4" stroke-linecap="round"/>')
    marc = ''.join(f'<circle cx="{x}" cy="{y}" r="9" fill="{c}" opacity=".22"/><circle cx="{x}" cy="{y}" r="4.2" fill="{c}" stroke="#0d1628" stroke-width="1.5"/>' for x, y, c in pinos)
    rot = ('<text x="196" y="44" fill="#cbd5e1" font-size="9" font-family="Inter,sans-serif" font-weight="600">Campinas</text>'
           '<text x="236" y="86" fill="#94a3b8" font-size="8.5" font-family="Inter,sans-serif">Jundiaí</text><text x="268" y="152" fill="#94a3b8" font-size="8.5" font-family="Inter,sans-serif">Sorocaba</text>'
           '<text x="30" y="148" fill="#94a3b8" font-size="8.5" font-family="Inter,sans-serif">Piracicaba</text>')
    mapa = f'<svg class="mp" viewBox="0 0 360 180" role="img" aria-label="Mapa com a posição de cada equipamento">{grade}{rod}{marc}{rot}</svg>'
    return (
        '<div class="v2-app" role="img" aria-label="Mapa dos equipamentos, custo de manutenção por equipamento e linha do tempo do dia">'
        '<div class="v2-bar"><i></i><i></i><i></i><b>app.oravel.com.br · Onde está e quanto custa</b><em>25 equipamentos</em></div><div class="v2-body"><div class="v2-map">'
        f'<div class="v2-card"><h6>Mapa dos equipamentos <span>obra, pátio e rastreador</span></h6>{mapa}'
        '<ul class="v2-lg" style="grid-auto-flow:column;justify-content:start;gap:14px;margin-top:9px"><li><i style="background:#22c55e"></i>Locado</li><li><i style="background:#38bdf8"></i>Disponível</li>'
        '<li><i style="background:#f59e0b"></i>Manutenção</li><li><i style="background:#ef4444"></i>Parado</li></ul></div>'
        '<div class="v2-card"><h6>Custo de manutenção <span>sobre o valor do equipamento</span></h6><div class="v2-rk">'
        '<div class="r"><div class="l"><b>Gerador 150 kVA</b><span>91% · R$ 59 mil</span></div><div class="t"><i style="width:91%;background:#ef4444"></i></div><span class="v2-tg tg-r" style="justify-self:start">Trocar ou vender</span></div>'
        '<div class="r"><div class="l"><b>Plataforma articulada 20 m</b><span>65% · R$ 124 mil</span></div><div class="t"><i style="width:65%;background:#ef4444"></i></div></div>'
        '<div class="r"><div class="l"><b>Torre de andaime 6 m</b><span>56% · R$ 31 mil</span></div><div class="t"><i style="width:56%;background:#f59e0b"></i></div><span class="v2-tg tg-a" style="justify-self:start">Revisar</span></div>'
        '<div class="r"><div class="l"><b>Compressor 750 PCM</b><span>14% · R$ 9 mil</span></div><div class="t"><i style="width:14%;background:#22c55e"></i></div></div></div></div></div>'
        '<div class="v2-card"><h6>Hoje na operação <span>em ordem de horário</span></h6><ul class="v2-tl">'
        '<li class="g">08h00 · Torre de andaime saiu para a obra<small>Checklist e assinatura do cliente registrados</small></li>'
        '<li class="b">11h30 · Visita técnica no compressor do cliente<small>Técnico Carlos · 48 km · custo de deslocamento lançado</small></li>'
        '<li class="r">14h10 · Gerador 150 kVA continua parado<small>Peça pedida há 4 dias: aviso ao comercial</small></li></ul></div>'
        '</div></div>')


def mk_ia_celular():
    return (
        '<div class="v2-ph" role="img" aria-label="Celular do técnico com a IA sugerindo diagnóstico e organizando as tarefas do dia"><div class="hd"><b>Oravel · Campo</b><span>IA ligada</span></div><div class="bd">'
        '<div class="v2-bub u">Compressor 02 está vazando óleo perto do cabeçote.</div>'
        '<div class="v2-bub i"><span class="lb">Sugestão da IA</span><b>Provável falha na vedação do cabeçote.</b> Esse modelo já voltou 2 vezes por isso.'
        '<ol><li>Despressurize e confira a junta</li><li>Troque o kit de vedação (3 em estoque)</li><li>Registre fotos e horímetro</li></ol></div>'
        '<div class="v2-task"><span class="n">1</span><span><b>Compressor 02 · vazamento</b><small>Cliente Alvorada · 14 km</small></span><span class="v2-tg tg-r">Urgente</span></div>'
        '<div class="v2-task"><span class="n">2</span><span><b>Revisão do Guindaste 01</b><small>Vence em 3 dias · 22 km</small></span><span class="v2-tg tg-a">Hoje</span></div>'
        '<div class="v2-task"><span class="n">3</span><span><b>Troca de filtros · Gerador 03</b><small>Pátio · sem deslocamento</small></span><span class="v2-tg tg-b">Amanhã</span></div>'
        '</div><div class="ft">Abrir a ordem de serviço</div></div>')


def mk_implantacao():
    return (
        '<div class="v2-imp" role="img" aria-label="Plano de implantação em quatro semanas com consultor e adoção da equipe por função"><div class="hd"><span class="av">CI</span>'
        '<span><b>Plano de implantação</b><small>Consultor com vivência em locadora, ao seu lado</small></span><span class="sel">Semana 3 de 4</span></div>'
        '<div class="v2-sem">'
        '<div class="s dn"><span class="w">Semana 1</span><span><b>Diagnóstico da operação</b><small>Frota, processos e o que hoje se perde em papel e mensagem</small></span></div>'
        '<div class="s dn"><span class="w">Semana 2</span><span><b>Cadastro e primeiras ordens</b><small>Equipamentos importados, planos de revisão e checklists montados</small></span></div>'
        '<div class="s"><span class="w">Semana 3</span><span><b>Treinamento por função</b><small>Técnico, almoxarife, comercial e gestor, cada um com a sua rotina</small></span></div>'
        '<div class="s"><span class="w">Semana 4</span><span><b>Revisão dos resultados</b><small>O painel do dono com números reais e o plano dos próximos 90 dias</small></span></div></div>'
        '<div class="v2-ad"><h6>Adoção da equipe</h6>'
        '<div class="r"><span>Técnicos</span><div class="t"><i style="width:92%"></i></div><b>92%</b></div>'
        '<div class="r"><span>Almoxarife</span><div class="t"><i style="width:85%"></i></div><b>85%</b></div>'
        '<div class="r"><span>Comercial</span><div class="t"><i style="width:78%"></i></div><b>78%</b></div></div>'
        '<div class="chips"><span>Academia Oravel</span><span>Trilha por função</span><span>Prova de conhecimento</span><span>Acompanhamento semanal</span></div></div>')
