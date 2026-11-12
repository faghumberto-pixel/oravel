// Gera o PDF da Gazeta Oravel no padrão do jornal (capa + cadernos), via Chrome do Windows (WSL).
// Uso:  node scripts/gerar-jornal.js 1      -> lê scripts/jornal/edicao-01.js e imagens em scripts/jornal/img/ed01/
// Saídas: src/assets/gazeta/gazeta-oravel-edicao-N.pdf, gazeta-oravel-atual.pdf e capa-atual.jpg (se N for a maior edição)
// Próximas edições: copie edicao-01.js para edicao-02.js, troque o conteúdo e as imagens (img/ed02/) e rode com 2.
const fs = require('fs'), path = require('path'), { execFileSync } = require('child_process');
const sharp = require('sharp');
const N = parseInt(process.argv[2] || '1', 10), NN = String(N).padStart(2, '0');
const ROOT = path.join(__dirname, '..'), J = path.join(__dirname, 'jornal');
const ed = require(path.join(J, `edicao-${NN}.js`)), dir = path.join(J, 'img', `ed${NN}`);
const render = require(path.join(J, 'template.js'));

(async () => {
  const html = render(ed, dir);
  const tmp = '/mnt/c/Users/Public/gazeta-oravel'; fs.mkdirSync(tmp, { recursive: true });
  fs.writeFileSync(path.join(tmp, 'jornal.html'), html);
  const pdfName = `gazeta-oravel-edicao-${N}.pdf`, pdfW = path.join(tmp, pdfName); try { fs.unlinkSync(pdfW); } catch (e) {}
  execFileSync('/mnt/c/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--disable-gpu', '--no-pdf-header-footer', '--virtual-time-budget=15000', '--print-to-pdf=C:\\Users\\Public\\gazeta-oravel\\' + pdfName, 'file:///C:/Users/Public/gazeta-oravel/jornal.html'], { stdio: 'ignore', timeout: 120000 });
  const out = path.join(ROOT, 'src/assets/gazeta'); fs.mkdirSync(out, { recursive: true });
  fs.copyFileSync(pdfW, path.join(out, pdfName));
  const nums = fs.readdirSync(out).map(f => (f.match(/^gazeta-oravel-edicao-(\d+)\.pdf$/) || [])[1]).filter(Boolean).map(Number);
  if (N === Math.max(...nums)) {
    fs.copyFileSync(pdfW, path.join(out, 'gazeta-oravel-atual.pdf'));
    await sharp(path.join(dir, 'capa.jpg')).resize(900).jpeg({ quality: 82, mozjpeg: true }).toFile(path.join(out, 'capa-atual.jpg'));
  }
  console.log('PDF:', pdfName, (fs.statSync(pdfW).size / 1024).toFixed(0) + ' KB', '| html em', path.join(tmp, 'jornal.html'));
})();
