{{-- Mesmos tokens do exemplo aprovado (docs/dashboards/exemplo-rautec.html); o escuro segue a classe .dark do Filament. --}}
<style>
.odb{--odb-card:#fff;--odb-bg:#f4f2ea;--odb-fg:#1c1b18;--odb-muted:#5c5a52;--odb-accent:#d9520a;--odb-border:rgba(28,27,24,.16);--odb-ok:#0b7a56;--odb-warn:#8a6a00;--odb-crit:#c2262e;--odb-track:#e8e5da;color:var(--odb-fg);font-size:14px;line-height:1.45}
.dark .odb{--odb-card:#1a1a18;--odb-bg:#0b0f0d;--odb-fg:#f4f2ea;--odb-muted:#c9c5b6;--odb-accent:#ff8a3d;--odb-border:rgba(244,242,234,.16);--odb-ok:#10b981;--odb-warn:#e3b341;--odb-crit:#ff6b72;--odb-track:#2a2a27}
.odb-lead{color:var(--odb-muted);margin:0 0 14px;max-width:68ch}
.odb-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px}
.odb-w{grid-column:span var(--span,4);background:var(--odb-card);border:1px solid var(--odb-border);border-radius:8px;padding:14px;display:flex;flex-direction:column;gap:10px;min-width:0}
.odb-wh h3{margin:0;font-size:13px;font-weight:700;letter-spacing:.02em}
.odb-wh small{display:block;color:var(--odb-muted);font-size:12px;font-weight:400}
.odb-kpi{font-size:30px;line-height:1;font-weight:700;font-variant-numeric:tabular-nums}
.odb-kpi em{font-style:normal;font-size:14px;color:var(--odb-muted);font-weight:500}
.odb-dl{font-size:12px;font-weight:600}.odb-dl.up{color:var(--odb-ok)}.odb-dl.dn{color:var(--odb-crit)}.odb-dl.n{color:var(--odb-muted)}
.odb-hb{display:grid;gap:7px}
.odb-hb .r{display:grid;grid-template-columns:minmax(80px,38%) 1fr auto;gap:8px;align-items:center;font-size:12.5px}
.odb-tr{height:9px;background:var(--odb-track);border-radius:5px;overflow:hidden}.odb-tr i{display:block;height:100%;background:var(--odb-accent);border-radius:5px}
.odb-tr i.ok{background:var(--odb-ok)}.odb-tr i.warn{background:var(--odb-warn)}.odb-tr i.crit{background:var(--odb-crit)}
.odb-num{font-variant-numeric:tabular-nums;font-weight:600}
.odb table{width:100%;border-collapse:collapse;font-size:12.5px}
.odb th{text-align:left;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:var(--odb-muted);font-weight:600;padding:4px 8px 6px 0;border-bottom:1px solid var(--odb-border)}
.odb td{padding:6px 8px 6px 0;border-bottom:1px solid var(--odb-border);vertical-align:middle}
.odb tr:last-child td{border-bottom:0}
.odb td.r,.odb th.r{text-align:right;padding-right:0;font-variant-numeric:tabular-nums}
.odb-tw{overflow-x:auto}
.odb-pill{display:inline-block;font-size:10.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border:1px solid currentColor;border-radius:999px;padding:1px 7px;white-space:nowrap}
.odb-pill.ok{color:var(--odb-ok)}.odb-pill.warn{color:var(--odb-warn)}.odb-pill.crit{color:var(--odb-crit)}.odb-pill.n{color:var(--odb-muted)}
.odb svg{display:block;width:100%;height:auto}
.odb svg text{fill:var(--odb-muted);font:11px sans-serif}
.odb-legend{display:flex;gap:12px;font-size:12px;color:var(--odb-muted);flex-wrap:wrap}.odb-legend b{display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:5px}
.odb-alert{display:grid;gap:6px}
.odb-alert div{display:flex;gap:8px;align-items:flex-start;font-size:12.5px;padding:7px 9px;border-radius:6px;background:var(--odb-bg);border:1px solid var(--odb-border)}
.odb-fun{display:grid;gap:5px}
.odb-fun div{display:flex;justify-content:space-between;padding:7px 10px;border-radius:5px;font-size:12.5px;color:#fff;background:var(--odb-accent)}
.odb-empty{color:var(--odb-muted);font-size:12.5px;margin:0}
@media (max-width:820px){.odb-w{grid-column:span 12}.odb-w[style*="--span: 2"]{grid-column:span 6}}
</style>
