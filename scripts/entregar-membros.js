#!/usr/bin/env node
/**
 * Conteudo do Observatorio so' para cadastrados.
 *
 * O Eleventy gera esse conteudo em _site/_membros (fora do Firebase: firebase.json ignora a pasta e o deploy a apaga).
 * Este script monta um pacote proprio e, com --enviar, entrega-o ao portal (participe.oravel.org/observatorio/conteudo/),
 * que so' o mostra a quem esta logado.
 *
 *   npm run membros:empacotar   -> so' monta dist-membros/observatorio-membros.tar.gz e confere
 *   npm run membros:enviar      -> monta, confere e envia para a VM do Observatorio (troca atomica)
 */
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const RAIZ = path.resolve(__dirname, '..');
const DIST = path.join(RAIZ, 'dist-membros');
const ENVIAR = process.argv.includes('--enviar');
const VM = 'oravel-observatorio';
const ZONA = 'southamerica-east1-c';
const DESTINO = '/var/www/observatorio/shared/storage/app/membros';

function falhar(msg) { console.error('  ERRO ' + msg); process.exit(1); }
function ok(msg) { console.log('  ok   ' + msg); }
function rodar(cmd, args, opts) {
  const r = spawnSync(cmd, args, Object.assign({ cwd: RAIZ, encoding: 'utf8' }, opts));
  if (r.status !== 0) falhar(cmd + ' ' + args.join(' ') + ' falhou' + (r.stderr ? ': ' + r.stderr.trim().split('\n').slice(-3).join(' | ') : ''));
  return r;
}

console.log('1. Build do conteudo de cadastrados');
fs.rmSync(DIST, { recursive: true, force: true });
fs.mkdirSync(DIST, { recursive: true });
const build = path.join(DIST, 'build');
rodar('npx', ['@11ty/eleventy', '--output=' + build]);
const base = path.join(build, '_membros', 'observatorio');
if (!fs.existsSync(path.join(base, 'index.html'))) falhar('_membros/observatorio/index.html nao foi gerado.');
ok('conteudo gerado');

console.log('2. Montagem do pacote');
const pacote = path.join(DIST, 'observatorio');
fs.mkdirSync(path.join(pacote, 'assets'), { recursive: true });
fs.copyFileSync(path.join(base, 'index.html'), path.join(pacote, 'index.html'));
if (fs.existsSync(path.join(base, 'dados'))) fs.cpSync(path.join(base, 'dados'), path.join(pacote, 'dados'), { recursive: true });
for (const f of ['observatorio-painel.css', 'observatorio-painel.js']) {
  const origem = path.join(build, 'assets', f);
  if (!fs.existsSync(origem)) falhar('falta assets/' + f);
  fs.copyFileSync(origem, path.join(pacote, 'assets', f));
}

console.log('3. Conferencia');
const html = fs.readFileSync(path.join(pacote, 'index.html'), 'utf8');
const proibido = [
  [/(href|src)="\/(?!\/)/g, 'caminho iniciado em "/" (nao existe dentro do portal)'],
  [/class="op-sub"/, 'barra do site publico'],
  [/id="dados-observatorio"[^>]*>[\s\S]{0,400}Simula/, 'bloco de simulacao'],
];
for (const [re, motivo] of proibido) if (re.test(html)) falhar('pacote invalido: ' + motivo);
if (!/Observat.rio Oravel/.test(html)) falhar('titulo do Observatorio ausente.');
ok('index.html valido (' + Math.round(html.length / 1024) + ' KB), sem caminhos quebrados');

const tar = path.join(DIST, 'observatorio-membros.tar.gz');
rodar('tar', ['-czf', tar, '-C', DIST, 'observatorio']);
ok('pacote: ' + path.relative(RAIZ, tar) + ' (' + Math.round(fs.statSync(tar).size / 1024) + ' KB)');

if (!ENVIAR) { console.log('\nPacote pronto. Para entregar ao portal: npm run membros:enviar'); process.exit(0); }

console.log('4. Envio para a VM do Observatorio');
const gc = ['--zone', ZONA, '--tunnel-through-iap', '--quiet'];
rodar('gcloud', ['compute', 'scp', ...gc, tar, VM + ':/tmp/observatorio-membros.tar.gz']);
const remoto = [
  `set -e`,
  `sudo mkdir -p ${DESTINO}`,
  `sudo rm -rf ${DESTINO}/.novo && sudo mkdir -p ${DESTINO}/.novo`,
  `sudo tar -xzf /tmp/observatorio-membros.tar.gz -C ${DESTINO}/.novo`,
  `sudo chown -R www-data:www-data ${DESTINO}/.novo`,
  `test -f ${DESTINO}/.novo/observatorio/index.html`,
  `sudo rm -rf ${DESTINO}/.antigo; [ -d ${DESTINO}/observatorio ] && sudo mv ${DESTINO}/observatorio ${DESTINO}/.antigo || true`,
  `sudo mv ${DESTINO}/.novo/observatorio ${DESTINO}/observatorio`,
  `sudo rm -rf ${DESTINO}/.novo ${DESTINO}/.antigo /tmp/observatorio-membros.tar.gz`,
  `ls -la ${DESTINO}/observatorio | head -8`,
].join(' && ').replace('set -e && ', 'set -e; ');
const r = rodar('gcloud', ['compute', 'ssh', VM, ...gc, '--command', remoto]);
console.log(r.stdout.split('\n').filter(l => l && !/NumPy|WARNING|iap\/docs/.test(l)).join('\n'));
ok('conteudo entregue em ' + DESTINO + '/observatorio');
