// Gera uma foto realista por artigo via API de imagens e grava `image:` no front matter.
// Uso: OPENAI_API_KEY=... node scripts/gerar-imagens.js [--forcar] [trecho-do-nome]
// ou:  GEMINI_API_KEY=... node scripts/gerar-imagens.js
const fs = require('fs'), path = require('path'), sharp = require('sharp');
const ROOT = path.join(__dirname, '..'), CONTENT = path.join(ROOT, 'src/content'), OUT = path.join(ROOT, 'src/assets/img');
const args = process.argv.slice(2), forcar = args.includes('--forcar'), filtro = args.find(a => !a.startsWith('--'));

const CENAS = {
  'reforma-tributaria': 'a rental company office desk with printed tax documents, a calculator and a laptop, heavy equipment visible through the window',
  'tecnologia': 'a tablet showing fleet telematics and GPS tracking inside the cab of an excavator on a construction site',
  'la-fora': 'a large equipment rental yard in the United States with rows of excavators, aerial lifts and loaders in morning light',
  'vendas': 'a rental counter at an equipment rental branch, hands exchanging a proposal and keys, no faces visible',
  'gestao-e-operacao': 'a maintenance workshop where a mechanic inspects a mini excavator with a checklist, face turned away',
  'comunidade': 'professionals of the equipment rental industry talking around a table at a trade meeting, seen from behind or in profile, no identifiable faces'
};
const ESTILO = 'Realistic editorial photograph, natural light, shallow depth of field, 3:2 landscape, professional magazine quality. No text, no letters, no logos, no brand names, no watermarks, no identifiable faces.';

function lerFM(s) { const m = s.match(/^---\n([\s\S]*?)\n---\n/); return m ? m[1] : ''; }
const campo = (fm, k) => ((fm.match(new RegExp('^' + k + ':\\s*(.*)$', 'm')) || [])[1] || '').replace(/^"|"$/g, '');

async function gerar(prompt) {
  if (process.env.OPENAI_API_KEY) {
    const r = await fetch('https://api.openai.com/v1/images/generations', {
      method: 'POST', headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + process.env.OPENAI_API_KEY },
      body: JSON.stringify({ model: 'gpt-image-1', prompt, size: '1536x1024', quality: 'medium', n: 1 })
    });
    const j = await r.json(); if (!r.ok) throw new Error(JSON.stringify(j.error || j));
    return Buffer.from(j.data[0].b64_json, 'base64');
  }
  if (process.env.GEMINI_API_KEY) {
    const r = await fetch('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent', {
      method: 'POST', headers: { 'Content-Type': 'application/json', 'x-goog-api-key': process.env.GEMINI_API_KEY },
      body: JSON.stringify({ contents: [{ parts: [{ text: prompt }] }] })
    });
    const j = await r.json(); if (!r.ok) throw new Error(JSON.stringify(j.error || j));
    const p = j.candidates[0].content.parts.find(x => x.inlineData); if (!p) throw new Error('sem imagem na resposta');
    return Buffer.from(p.inlineData.data, 'base64');
  }
  throw new Error('Defina OPENAI_API_KEY ou GEMINI_API_KEY');
}

(async () => {
  for (const tema of Object.keys(CENAS)) {
    const dir = path.join(CONTENT, tema);
    for (const f of fs.readdirSync(dir).filter(x => x.endsWith('.md'))) {
      if (filtro && !f.includes(filtro)) continue;
      const file = path.join(dir, f), s = fs.readFileSync(file, 'utf8'), fm = lerFM(s);
      if (/^image:/m.test(fm) && !forcar) continue;
      const slug = f.replace(/\.md$/, '').replace(/^\d{4}-\d\d-\d\dT[\d:]+-03:00-/, '');
      const prompt = `${ESTILO} Scene: ${CENAS[tema]}. Article topic for context: ${campo(fm, 'title')}.`;
      process.stdout.write(`${tema}/${slug} ... `);
      try {
        const buf = await gerar(prompt);
        await sharp(buf).resize(1200, 800, { fit: 'cover' }).jpeg({ quality: 80, mozjpeg: true }).toFile(path.join(OUT, slug + '.jpg'));
        const linha = `image: "/assets/img/${slug}.jpg"\n`;
        const novo = /^image:.*\n/m.test(fm) ? s.replace(/^image:.*\n/m, linha) : s.replace(/^---\n/, '---\n' + linha);
        fs.writeFileSync(file, novo); console.log('ok');
      } catch (e) { console.log('ERRO', e.message.slice(0, 200)); }
    }
  }
})();
