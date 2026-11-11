// Gera gazeta/email-boas-vindas.html (e-mail da Gazeta) com os 5 artigos mais recentes.
// Rode antes de publicar as funções:  node scripts/gerar-gazeta.js
const fs = require('fs'), path = require('path');
const ROOT = path.join(__dirname, '..'), C = path.join(ROOT, 'src/content');
const L = { 'reforma-tributaria': 'Reforma tributária', tecnologia: 'Tecnologia', 'la-fora': 'Lá fora', vendas: 'Vendas', 'gestao-e-operacao': 'Gestão e operação', comunidade: 'Comunidade' };
const esc = s => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const itens = [];
for (const t of Object.keys(L)) for (const f of fs.readdirSync(path.join(C, t)).filter(x => x.endsWith('.md'))) {
  const s = fs.readFileSync(path.join(C, t, f), 'utf8'), fm = (s.match(/^---\n([\s\S]*?)\n---/) || [])[1] || '';
  const g = k => ((fm.match(new RegExp('^' + k + ':\\s*(.*)$', 'm')) || [])[1] || '').replace(/^"|"$/g, '');
  itens.push({ t, title: g('title'), summary: g('summary'), date: g('date'), img: g('image'), url: `https://oravel.org/${t}/${f.replace(/\.md$/, '')}/` });
}
itens.sort((a, b) => new Date(b.date) - new Date(a.date));
const top = itens.slice(0, 5);
const bloco = top.map((p, i) => `
<tr><td style="padding:22px 0;border-top:1px solid #e4dfd3">
${p.img && i === 0 ? `<a href="${p.url}"><img src="https://oravel.org${p.img}" alt="" width="536" style="width:100%;max-width:536px;height:auto;border-radius:10px;display:block;margin-bottom:14px"></a>` : ''}
<div style="font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#0b5f57">${esc(L[p.t])}</div>
<a href="${p.url}" style="display:block;margin-top:6px;font-family:Georgia,serif;font-size:${i === 0 ? 24 : 20}px;line-height:1.25;font-weight:700;color:#1b2230;text-decoration:none">${esc(p.title)}</a>
<p style="margin:8px 0 0;font-size:15px;line-height:1.55;color:#4a5361">${esc(p.summary)}</p>
<a href="${p.url}" style="display:inline-block;margin-top:10px;font-size:14px;font-weight:600;color:#0b5f57">Ler o artigo →</a>
</td></tr>`).join('');
const html = `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gazeta Oravel</title></head>
<body style="margin:0;background:#f6f3ec;font-family:Arial,Helvetica,sans-serif;color:#1b2230">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:14px;overflow:hidden">
<tr><td style="background:#12312e;padding:22px 32px"><span style="font-family:Georgia,serif;font-size:30px;font-weight:700;color:#fff">oravel<span style="color:#f59e0b">.</span>org</span><div style="margin-top:4px;font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:#9ad7cd">Gazeta Oravel</div></td></tr>
<tr><td style="padding:28px 32px 8px"><h1 style="margin:0;font-family:Georgia,serif;font-size:26px;line-height:1.2;font-weight:700">Bem-vindo à Gazeta Oravel</h1>
<p style="margin:12px 0 0;font-size:16px;line-height:1.6;color:#4a5361">Uma edição por semana com o que mudou no setor de locação de equipamentos. Curta, objetiva e com fonte. Estes são os destaques mais recentes:</p></td></tr>
<tr><td style="padding:8px 32px 8px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">${bloco}</table></td></tr>
<tr><td style="padding:8px 32px 28px"><a href="https://oravel.org/publicacoes/" style="display:inline-block;background:#0b5f57;color:#fff;text-decoration:none;font-weight:600;padding:12px 22px;border-radius:8px">Ver todas as publicações</a></td></tr>
<tr><td style="background:#fbfaf6;padding:20px 32px;font-size:12px;line-height:1.6;color:#6b7280">Você recebeu este e-mail porque se inscreveu na Gazeta Oravel em oravel.org. Conteúdo informativo, não substitui orientação de contador, advogado ou consultor.<br>Oravel Desenvolvimento de Software LTDA · CNPJ 65.707.953/0001-30 · Rua Pais Leme, 215, Conj. 1713, Pinheiros, São Paulo/SP<br><a href="{{ unsubscribe }}" style="color:#6b7280">Cancelar inscrição</a></td></tr>
</table></td></tr></table></body></html>`;
fs.writeFileSync(path.join(ROOT, 'gazeta/email-boas-vindas.html'), html);
console.log('gazeta/email-boas-vindas.html gerado com', top.length, 'destaques');
