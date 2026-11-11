// Liga as imagens salvas em src/assets/img/<slug>.(jpg|jpeg|png|webp) aos artigos:
// redimensiona para 1200x800 (JPG otimizado) e grava `image:` no front matter.
const fs = require('fs'), path = require('path'), sharp = require('sharp');
const ROOT = path.join(__dirname, '..'), CONTENT = path.join(ROOT, 'src/content'), IMG = path.join(ROOT, 'src/assets/img');
const exts = ['jpg', 'jpeg', 'png', 'webp'];
(async () => {
  let n = 0;
  for (const tema of fs.readdirSync(CONTENT)) {
    const dir = path.join(CONTENT, tema);
    if (tema === 'pages' || !fs.statSync(dir).isDirectory()) continue;
    for (const f of fs.readdirSync(dir).filter(x => x.endsWith('.md'))) {
      const slug = f.replace(/\.md$/, '').replace(/^\d{4}-\d\d-\d\dT[\d:]+-03:00-/, '');
      const ext = exts.find(e => fs.existsSync(path.join(IMG, `${slug}.${e}`)));
      if (!ext) continue;
      const orig = path.join(IMG, `${slug}.${ext}`), dest = path.join(IMG, `${slug}.jpg`);
      const buf = fs.readFileSync(orig);
      await sharp(buf).resize(1200, 800, { fit: 'cover' }).jpeg({ quality: 80, mozjpeg: true }).toFile(dest + '.tmp');
      fs.renameSync(dest + '.tmp', dest);
      if (ext !== 'jpg') fs.unlinkSync(orig);
      const file = path.join(dir, f), s = fs.readFileSync(file, 'utf8'), linha = `image: "/assets/img/${slug}.jpg"\n`;
      const novo = /^image:.*\n/m.test(s.split('\n---\n')[0] + '\n') ? s.replace(/^image:.*\n/m, linha) : s.replace(/^---\n/, '---\n' + linha);
      if (novo !== s) fs.writeFileSync(file, novo);
      console.log('ligada:', tema + '/' + slug); n++;
    }
  }
  console.log(n + ' imagem(ns) ligada(s).');
})();
