// Padrão visual da Gazeta Oravel (jornal em PDF). Cada edição fornece só o conteúdo (edicao-NN.js).
const fs = require('fs'), path = require('path');
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
// **negrito** e *itálico* inline (o asterisco de nota usa \* )
const inl = s => esc(s).replace(/\\\*/g, '&#42;').replace(/\*\*(.+?)\*\*/g, '<b>$1</b>').replace(/\*(.+?)\*/g, '<i>$1</i>');

const img64 = (dir, f) => 'data:image/jpeg;base64,' + fs.readFileSync(path.join(dir, f)).toString('base64');

const story = (ed, d, s, first) => `<section class="mat">
<div class="kick">${esc(s.kicker)}</div><h2 class="${s.big ? 'big' : ''}">${esc(s.title)}</h2>
${s.deck ? `<p class="deck">${inl(s.deck)}</p>` : ''}
${s.img ? `<figure><img src="${img64(d, s.img)}" alt=""><figcaption>${inl(s.caption || '')}</figcaption></figure>` : ''}
${s.paras.map((p, i) => `<p class="${i === 0 && s.dropcap ? 'cap' : ''}">${inl(p)}</p>`).join('\n')}
${s.quote ? `<blockquote>${inl(s.quote)}</blockquote>` : ''}
</section>`;

const topo = (p) => `<div class="topo"><div class="t1">${esc(p.titulo)}</div><div class="t2">${esc(p.sub)}</div></div>`;
const pe = (p, n) => `<div class="pe"><span>GAZETA ORAVEL • WWW.ORAVEL.ORG</span><span>${esc(p.rodape)}</span><span>PÁGINA ${n}</span></div>`;
const nota = p => p.nota ? `<div class="nota">${inl(p.nota)}</div>` : '';

module.exports = function render(ed, dir) {
  const capa = `<div class="pg capa"><img src="${img64(dir, 'capa.jpg')}" alt="Capa"></div>`;
  const pgs = ed.paginas.map((p, i) => {
    const n = i + 1;
    if (p.tipo === 'primeira') {
      return `<div class="pg"><div class="mast"><div class="m1">${esc(ed.linhaTopo)}</div><div class="m2">GAZETA ORAVEL</div><div class="m3">${inl(ed.lema)}</div>
<div class="m4"><span>${esc(ed.linhaEdicao)}</span><span>${esc(ed.linhaTemas)}</span><span>CIRCULAÇÃO ESPECIALIZADA</span></div></div>
<div class="cols">${p.col1.map(s => story(ed, dir, s)).join('')}</div><div class="cols2">${p.col2.map(s => story(ed, dir, s)).join('')}</div>${nota(p)}${pe(p, n)}</div>`;
    }
    if (p.tipo === 'caderno') {
      return `<div class="pg">${topo(p)}<div class="grid"><div>${p.col1.map(s => story(ed, dir, s)).join('')}</div><div>${p.col2.map(s => story(ed, dir, s)).join('')}</div></div>
${p.larga ? `<div class="larga">${p.larga.map(s => story(ed, dir, s)).join('')}</div>` : ''}${p.tabela ? tabela(p.tabela) : ''}${nota(p)}${pe(p, n)}</div>`;
    }
    // expediente
    return `<div class="pg">${topo(p)}<div class="grid"><div>${p.col1.map(b => bloco(b)).join('')}</div><div>${p.col2.map(b => bloco(b)).join('')}</div></div>
<div class="leia"><div class="kick">${esc(p.leia.titulo)}</div><p>${inl(p.leia.texto)}</p></div>
${p.refs ? `<div class="refs"><div class="kick">Referências consultadas</div><ol>${p.refs.map(x => `<li>${inl(x)}</li>`).join('')}</ol></div>` : ''}
<div class="exped"><div class="et">${esc(p.expediente.titulo)}</div><div class="eg"><div>${p.expediente.esq.map(x => `<p>${inl(x)}</p>`).join('')}</div><div>${p.expediente.dir.map(x => `<p>${inl(x)}</p>`).join('')}</div></div></div>${pe(p, n)}</div>`;
  });
  return `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Gazeta Oravel · ${esc(ed.linhaEdicao)}</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:ital,wght@0,300;0,400;0,700;1,300;1,400&family=Playfair+Display:wght@800;900&display=swap" rel="stylesheet">
<style>
@page{size:210mm 297mm;margin:0}*{box-sizing:border-box}html,body{margin:0;-webkit-print-color-adjust:exact;print-color-adjust:exact}
body{font-family:'Merriweather',Georgia,serif;color:#222;font-size:7.7pt;line-height:1.62;font-weight:300}
.pg{width:210mm;height:297mm;position:relative;overflow:hidden;background:#fcfbf9;padding:15mm 16mm 0;page-break-after:always;break-after:page}
.pg.capa{padding:0;background:#111}.capa img{width:210mm;height:297mm;object-fit:cover;display:block}
.mast{text-align:center}.m1{font:500 5.6pt Inter,Arial;letter-spacing:.34em;color:#333;margin-bottom:3mm}
.m2{font:900 36pt 'Playfair Display',Georgia,serif;line-height:1;letter-spacing:-.01em;color:#111}.m3{font:italic 400 8.2pt Merriweather,Georgia;margin-top:2.2mm;color:#222}
.m4{display:flex;justify-content:space-between;font:600 5.6pt Inter,Arial;letter-spacing:.07em;border-top:.35mm solid #111;border-bottom:.9mm double #111;padding:1.4mm 0;margin-top:3mm}
.cols{display:inline-block;vertical-align:top;width:94mm;margin-top:6mm}.cols2{display:inline-block;vertical-align:top;width:78mm;margin:6mm 0 0 5.6mm;border-left:.2mm solid #d9d4c8;padding-left:5.6mm;min-height:150mm}
.topo{border-top:.8mm solid #111;border-bottom:.3mm solid #111;padding:1.2mm 0 1.4mm;display:flex;justify-content:space-between;align-items:flex-start;gap:6mm}
.t1{font:700 9pt Inter,Arial;letter-spacing:.12em;color:#111;line-height:1.35;max-width:112mm}.t2{font:500 5.4pt Inter,Arial;letter-spacing:.07em;color:#555;max-width:55mm;line-height:1.5;padding-top:.6mm}
.grid{display:grid;grid-template-columns:90mm 82mm;gap:0 5.6mm;margin-top:5mm}.grid>div:nth-child(2){border-left:.2mm solid #d9d4c8;padding-left:5.6mm}
.mat{margin-bottom:5mm}.kick{font:700 5.6pt Inter,Arial;letter-spacing:.14em;color:#a8261d;text-transform:uppercase;margin-bottom:1.1mm}
h2{font:700 11.4pt/1.28 Merriweather,Georgia,serif;margin:0 0 2mm;color:#111;letter-spacing:-.005em}h2.big{font-size:19pt;line-height:1.25;margin-bottom:2.4mm}
.deck{font:italic 400 7.9pt/1.6 Merriweather,Georgia;color:#444;margin:0 0 3mm}
figure{margin:0 0 3mm}figure img{width:100%;height:auto;display:block;border:.2mm solid #cfc9bc}
figcaption{font:400 5.3pt/1.45 Inter,Arial;color:#555;margin-top:1mm;padding-bottom:1.2mm;border-bottom:.2mm solid #d9d4c8}figcaption b{color:#333}
p{margin:0 0 2.6mm;text-align:justify;hyphens:auto}p.cap::first-letter{font:900 25pt/.8 'Playfair Display',Georgia,serif;float:left;margin:1.2mm 1.4mm 0 0;color:#111}
blockquote{margin:2mm 0 0;padding-left:2.4mm;border-left:.8mm solid #111;font:italic 400 7pt/1.55 Merriweather,Georgia;color:#333}
.larga{margin-top:3mm;border-top:.2mm dashed #cfc9bc;padding-top:3mm}.larga h2{font-size:10.6pt}
table{width:100%;border-collapse:collapse;margin-top:2mm;font-size:6.8pt;line-height:1.5}th{font:700 5.8pt Inter,Arial;letter-spacing:.06em;text-align:left;padding:1.4mm 1.4mm;border-top:.3mm solid #111;border-bottom:.3mm solid #111;background:#f3f0ea}
td{padding:1.4mm;border-bottom:.2mm dotted #bbb;vertical-align:top}td:first-child{font-weight:700;width:34mm}
.tq{margin-top:4mm}.nota{position:absolute;left:16mm;right:16mm;bottom:19mm;font:italic 300 5.7pt/1.5 Merriweather,Georgia;color:#555;border-top:.2mm solid #cfc9bc;padding-top:1.4mm}
.pe{position:absolute;left:16mm;right:16mm;bottom:8mm;border-top:.35mm solid #111;padding-top:1.6mm;display:flex;justify-content:space-between;font:500 5.4pt Inter,Arial;letter-spacing:.07em;color:#333}
ul{margin:0 0 2.4mm;padding-left:3.6mm}li{margin-bottom:1.4mm;text-align:justify}
.leia{margin-top:5mm;border:.3mm solid #111;background:#f4f1ea;padding:3mm 4mm}.leia p{margin:0;font-size:7pt}
.refs{margin-top:4mm}.refs ol{margin:0;padding-left:4mm;font-size:6.1pt;line-height:1.5;color:#444}.refs li{margin-bottom:.8mm}
.exped{margin-top:4mm;border:.3mm solid #555;padding:3mm 4mm}.et{font:700 6.6pt Inter,Arial;letter-spacing:.1em;margin-bottom:2mm}.eg{display:grid;grid-template-columns:1fr 1fr;gap:6mm}.exped p{font-size:6.5pt;margin:0 0 1.6mm;text-align:left}
a.mail{color:#a8261d;font-weight:700;text-decoration:underline;font-size:8.2pt}
</style></head><body>${capa}${pgs.join('\n')}</body></html>`;
  function tabela(t) { return `<div class="tq"><div class="kick">${esc(t.titulo)}</div><table><tr>${t.cab.map(c => `<th>${esc(c)}</th>`).join('')}</tr>${t.linhas.map(l => `<tr>${l.map(c => `<td>${inl(c)}</td>`).join('')}</tr>`).join('')}</table></div>`; }
  function bloco(b) { return `<section class="mat"><div class="kick">${esc(b.kicker)}</div>${b.paras ? b.paras.map(x => `<p>${inl(x)}</p>`).join('') : ''}${b.lista ? `<ul>${b.lista.map(x => `<li>${inl(x)}</li>`).join('')}</ul>` : ''}${b.paras2 ? b.paras2.map(x => `<p>${inl(x)}</p>`).join('') : ''}</section>`; }
};
