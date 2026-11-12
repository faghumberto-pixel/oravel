// Gera o PDF "Gazeta Oravel" (edição) com os artigos mais recentes, usando o Chrome do Windows (WSL).
// Uso: node scripts/gerar-jornal.js [numero-da-edicao]
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const matter = require('gray-matter'), MarkdownIt = require('markdown-it'), sharp = require('sharp');
const md = new MarkdownIt({ html: false, linkify: false });
const ROOT = path.join(__dirname, '..'), C = path.join(ROOT, 'src/content');
const NUM = process.argv[2] || '1';
const L = { 'reforma-tributaria': 'Reforma tributária', tecnologia: 'Tecnologia', 'la-fora': 'Lá fora', vendas: 'Vendas', 'gestao-e-operacao': 'Gestão e operação', comunidade: 'Comunidade' };
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const dataLonga = d => new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long', timeZone: 'America/Sao_Paulo' }).format(new Date(d));

const arts = [];
for (const t of Object.keys(L)) for (const f of fs.readdirSync(path.join(C, t)).filter(x => x.endsWith('.md'))) {
  const { data, content } = matter(fs.readFileSync(path.join(C, t, f), 'utf8'));
  arts.push({ t, f, data, content, date: new Date(data.date) });
}
arts.sort((a, b) => b.date - a.date);
const top = arts.slice(0, 8);

function trecho(content, maxPal = 130) {
  const blocos = content.split(/\n\s*\n/).filter(b => b.trim() && !/^(#|>|\||```|---|- |\* |\d+\. )/.test(b.trim()) && !/:\s*$/.test(b.trim()));
  let out = [], n = 0;
  for (const b of blocos) { out.push(b.trim()); n += b.split(/\s+/).length; if (n >= maxPal) break; }
  return md.render(out.join('\n\n'));
}

(async () => {
  const blocos = [];
  for (const [i, a] of top.entries()) {
    const slug = a.f.replace(/\.md$/, '');
    let img = '';
    if (a.data.image) {
      const p = path.join(ROOT, 'src', a.data.image);
      if (fs.existsSync(p)) { const b = await sharp(p).resize(900, 520, { fit: 'cover' }).jpeg({ quality: 72 }).toBuffer(); img = `<img src="data:image/jpeg;base64,${b.toString('base64')}" alt="">`; }
    }
    const url = `https://oravel.org/${a.t}/${slug}/`;
    blocos.push(`<article class="${i === 0 ? 'destaque' : 'item'}">
${img}<div class="tema">${esc(L[a.t])} · ${esc(dataLonga(a.date))}</div>
<h2>${esc(a.data.title)}</h2><p class="resumo">${esc(a.data.summary || '')}</p>
<div class="texto">${trecho(a.content, i === 0 ? 190 : 110)}</div>
<p class="ler"><a href="${esc(url)}">Leia o artigo completo em oravel.org →</a></p></article>`);
  }
  const destaque = blocos[0], resto = blocos.slice(1).join('\n');
  const html = `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Gazeta Oravel · Edição ${NUM}</title><style>
@page{size:A4;margin:14mm 14mm 16mm}
*{box-sizing:border-box}body{margin:0;font-family:Georgia,'Times New Roman',serif;color:#1b2230;font-size:10.6pt;line-height:1.5}
.capa{background:#12312e;color:#fff;padding:18px 22px;border-radius:6px;display:flex;justify-content:space-between;align-items:flex-end}
.capa h1{margin:0;font-size:34pt;letter-spacing:-.5px}.capa h1 span{color:#f59e0b}
.capa .ed{font:600 9pt Arial,sans-serif;letter-spacing:.14em;text-transform:uppercase;color:#9ad7cd;text-align:right}
.sub{font:9pt Arial,sans-serif;color:#4a5361;margin:8px 2px 14px;display:flex;justify-content:space-between;border-bottom:2px solid #1b2230;padding-bottom:6px}
.destaque{margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #d8d2c4}
.destaque img{width:100%;height:auto;border-radius:4px;margin-bottom:8px}
.destaque h2{font-size:22pt;line-height:1.12;margin:4px 0 6px}
.resumo{font-style:italic;color:#4a5361;margin:0 0 6px}
.cols{column-count:2;column-gap:7mm}
.item{break-inside:avoid;margin:0 0 12px;padding-bottom:10px;border-bottom:1px solid #d8d2c4}
.item img{width:100%;height:auto;border-radius:3px;margin-bottom:5px}
.item h2{font-size:13.5pt;line-height:1.15;margin:3px 0 4px}
.tema{font:700 7.8pt Arial,sans-serif;letter-spacing:.1em;text-transform:uppercase;color:#0b5f57}
.texto p{margin:0 0 5px;text-align:justify;hyphens:auto}.texto{font-size:9.8pt}
.ler{font:8.5pt Arial,sans-serif;margin:4px 0 0}.ler a{color:#0b5f57;font-weight:700;text-decoration:none}
.rodape{margin-top:10px;border-top:2px solid #1b2230;padding-top:6px;font:7.8pt Arial,sans-serif;color:#6b7280;text-align:center}
</style></head><body>
<div class="capa"><h1>Gazeta <span>Oravel</span></h1><div class="ed">Edição nº ${esc(NUM)}<br>${esc(dataLonga(new Date()))}</div></div>
<div class="sub"><span>Conhecimento para quem aluga equipamentos</span><span>oravel.org</span></div>
${destaque}
<div class="cols">${resto}</div>
<div class="rodape">Conteúdo informativo, não substitui a orientação de contador, advogado ou consultor. · Oravel Desenvolvimento de Software LTDA · CNPJ 65.707.953/0001-30 · Rua Pais Leme, 215, Conj. 1713, Pinheiros, São Paulo/SP</div>
</body></html>`;

  const tmpW = '/mnt/c/Users/Public/gazeta-oravel'; fs.mkdirSync(tmpW, { recursive: true });
  fs.writeFileSync(path.join(tmpW, 'edicao.html'), html);
  const outDir = path.join(ROOT, 'src/assets/gazeta'); fs.mkdirSync(outDir, { recursive: true });
  const pdfW = path.join(tmpW, `gazeta-oravel-edicao-${NUM}.pdf`); try { fs.unlinkSync(pdfW); } catch (e) {}
  execFileSync('/mnt/c/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--disable-gpu', '--no-pdf-header-footer', '--print-to-pdf=C:\\Users\\Public\\gazeta-oravel\\' + `gazeta-oravel-edicao-${NUM}.pdf`, 'file:///C:/Users/Public/gazeta-oravel/edicao.html'], { stdio: 'ignore', timeout: 90000 });
  fs.copyFileSync(pdfW, path.join(outDir, `gazeta-oravel-edicao-${NUM}.pdf`));
  console.log('PDF gerado:', `src/assets/gazeta/gazeta-oravel-edicao-${NUM}.pdf`, (fs.statSync(pdfW).size / 1024).toFixed(0) + ' KB');
})();
