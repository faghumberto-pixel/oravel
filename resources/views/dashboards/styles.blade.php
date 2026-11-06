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
.odb td.r,.odb th.r{text-align:right;padding-right:0;padding-left:10px;white-space:nowrap;font-variant-numeric:tabular-nums}
.odb-tw{overflow-x:auto}
.odb-pill{display:inline-block;font-size:10.5px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border:1px solid currentColor;border-radius:999px;padding:1px 7px;white-space:nowrap}
.odb-pill.ok{color:var(--odb-ok)}.odb-pill.warn{color:var(--odb-warn)}.odb-pill.crit{color:var(--odb-crit)}.odb-pill.n{color:var(--odb-muted)}
.odb svg{display:block;width:100%;height:auto}
.odb svg text{fill:var(--odb-muted);font:11px sans-serif}
.odb-legend{display:flex;gap:12px;font-size:12px;color:var(--odb-muted);flex-wrap:wrap}.odb-legend b{display:inline-block;width:9px;height:9px;border-radius:2px;margin-right:5px}
.odb-alert{display:grid;gap:6px}
.odb-alert div{display:flex;gap:8px;align-items:flex-start;font-size:12.5px;padding:7px 9px;border-radius:6px;background:var(--odb-bg);border:1px solid var(--odb-border)}
.odb-fun{display:grid;gap:5px;justify-items:center}
.odb-fun div{display:flex;justify-content:space-between;padding:8px 12px;border-radius:6px;font-size:12.5px;color:#fff;background:var(--odb-accent);margin:0 auto;min-width:150px}.odb-fun em{font-style:normal;font-weight:500;opacity:.85;margin-left:6px;font-size:11px}
.odb-empty{color:var(--odb-muted);font-size:12.5px;margin:0}
.odb-spark{margin-top:2px}.odb-spark svg{width:100%}
.odb-donut{display:grid;gap:10px;justify-items:center}
.odb-dlg{list-style:none;margin:0;padding:0;display:grid;gap:5px;width:100%;font-size:12.5px}
.odb-dlg li{display:grid;grid-template-columns:auto 1fr auto;gap:8px;align-items:center}
.odb-dlg b{display:inline-block;width:10px;height:10px;border-radius:3px}
.odb-heat{display:grid;gap:3px;min-width:440px;font-size:12px}
.odb-heat-h,.odb-heat-r{display:grid;grid-template-columns:minmax(118px,1.3fr) repeat(var(--cols),minmax(13px,1fr)) minmax(70px,.8fr);gap:3px;align-items:center}
.odb-heat-h .m{font-size:10px;color:var(--odb-muted);text-align:center;text-transform:uppercase}
.odb-heat-h .t,.odb-heat-r .t{text-align:right;font-variant-numeric:tabular-nums;font-weight:600;font-size:11.5px}
.odb-heat-h .t{color:var(--odb-muted);font-size:10px;text-transform:uppercase;font-weight:600}
.odb-heat-r .l{min-width:0;display:flex;flex-direction:column;line-height:1.2}.odb-heat-r .l strong{font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.odb-heat-r .l small{color:var(--odb-muted);font-size:10.5px}
.odb-heat-r .c{height:26px;border-radius:4px;background:var(--odb-track)}
.odb-scale{width:120px;height:8px;border-radius:4px;background:linear-gradient(90deg,var(--odb-track),var(--odb-crit))}
.odb-rk{display:grid;gap:11px}
.odb-rk-t{display:flex;justify-content:space-between;align-items:baseline;gap:8px;font-size:12.5px}
.odb-rk-t small{display:block;color:var(--odb-muted);font-size:11px}
.odb-num.ok{color:var(--odb-ok)}.odb-num.warn{color:var(--odb-warn)}.odb-num.crit{color:var(--odb-crit)}
.odb-tr-m{position:relative;height:12px;margin-top:5px;overflow:visible}
.odb-tr-m s{position:absolute;top:-3px;bottom:-3px;width:2px;background:var(--odb-fg);opacity:.45;border-radius:1px}
.odb-map-w{border-radius:8px;overflow:hidden;border:1px solid var(--odb-border)}
.odb-map{height:320px;width:100%;z-index:1}
.odb .odb-map svg{display:inline;width:auto;height:auto;max-width:none}.odb .odb-map .leaflet-overlay-pane svg{display:block}
.odb-link{margin-left:auto;color:var(--odb-accent);font-weight:600;text-decoration:none}
.odb-tl{list-style:none;margin:0;padding:0;position:relative;display:grid;gap:12px}
.odb-tl:before{content:"";position:absolute;left:86px;top:6px;bottom:6px;width:2px;background:var(--odb-border)}
.odb-tl li{display:grid;grid-template-columns:78px 1fr;gap:20px;position:relative;font-size:12.5px;line-height:1.35}
.odb-tl li .d{font-size:11px;font-weight:700;color:var(--odb-muted);text-align:right;padding-top:2px;font-variant-numeric:tabular-nums}
.odb-tl li:before{content:"";position:absolute;left:80px;top:4px;width:14px;height:14px;border-radius:50%;background:var(--odb-accent);border:3px solid var(--odb-card);box-shadow:0 0 0 1px var(--odb-border)}
.odb-tl li.ok:before{background:var(--odb-ok)}.odb-tl li.warn:before{background:var(--odb-warn)}.odb-tl li.crit:before{background:var(--odb-crit)}.odb-tl li.n:before{background:var(--odb-muted)}
.odb-tl small{display:block;color:var(--odb-muted);font-size:11.5px}
@media (max-width:820px){.odb-w{grid-column:span 12}.odb-w[style*="--span: 2"]{grid-column:span 6}}
</style>
