#!/usr/bin/env node
/**
 * Publica o site no Firebase Hosting (projeto oravel-org), com conferencias
 * antes e depois da publicacao.
 *
 * Uso:
 *   npm run deploy                      confere, constroi, confere a home, publica e verifica
 *   npm run deploy:conferir             faz tudo, menos publicar
 *   npm run deploy -- --sujo            permite arvore git com alteracoes nao commitadas
 *   npm run deploy -- --permitir-remocoes   permite que URLs que estao no ar deixem de existir
 *
 * Conferencias (a publicacao e abortada se alguma falhar):
 *   1. arvore git sem alteracoes pendentes (o que vai ao ar esta commitado)
 *   2. build completo (Eleventy + pagefind)
 *   3. home gerada tem site-header, destaques, hero e sidebar
 *   4. nenhum dado de demonstracao (ranking.json com demo: true) vai ao ar
 *   5. nenhuma URL do sitemap que esta no ar deixaria de existir
 * Depois do deploy, compara a home publicada com a gerada localmente.
 */
const { spawnSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const RAIZ = path.join(__dirname, '..');
const SITE_URL = 'https://oravel.org';
const PROJETO = 'oravel-org';
const args = process.argv.slice(2);
const SO_CONFERIR = args.includes('--so-conferir');
const PERMITIR_SUJO = args.includes('--sujo');
const PERMITIR_REMOCOES = args.includes('--permitir-remocoes');

const MARCADORES_HOME = ['site-header', 'class="destaques"', 'class="hero"', 'class="sidebar"'];

function rodar(cmd, cmdArgs, opcoes = {}) {
  return spawnSync(cmd, cmdArgs, { cwd: RAIZ, encoding: 'utf8', ...opcoes });
}

function falhar(msg) {
  console.error('\nPUBLICACAO ABORTADA: ' + msg);
  process.exit(1);
}

function ok(msg) { console.log('  ok   ' + msg); }
function aviso(msg) { console.log('  aviso ' + msg); }

async function baixar(url) {
  const res = await fetch(url, {
    headers: { 'Cache-Control': 'no-cache', 'User-Agent': 'oravel-deploy-check' },
    signal: AbortSignal.timeout(30000),
    redirect: 'follow'
  });
  if (!res.ok) throw new Error('HTTP ' + res.status + ' em ' + url);
  return res.text();
}

// Marcadores da home que precisam existir (regra do projeto: nao mudar o layout por engano).
function marcadoresFaltando(html) {
  return MARCADORES_HOME.filter(m => !html.includes(m));
}

// Linhas presentes em "a" e ausentes em "b" (comparacao por multiconjunto).
function linhasExclusivas(a, b) {
  const cont = new Map();
  for (const l of b.split('\n')) cont.set(l, (cont.get(l) || 0) + 1);
  let n = 0;
  for (const l of a.split('\n')) {
    const c = cont.get(l) || 0;
    if (c > 0) cont.set(l, c - 1); else n++;
  }
  return n;
}

function extrairLocs(xml) {
  return new Set([...xml.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m => m[1].trim()));
}

// Dados de demonstracao nao podem ir ao ar.
function demoVazando(html) {
  const problemas = [];
  if (html.includes('ranking-demo')) problemas.push('nota de demonstracao presente na home');
  try {
    const ranking = JSON.parse(fs.readFileSync(path.join(RAIZ, 'src/_data/ranking.json'), 'utf8'));
    if (ranking.demo && (html.includes('id="lidos-title"') || html.includes('id="avaliados-title"'))) {
      problemas.push('secoes de ranking com dados de demonstracao (ranking.json com demo: true)');
    }
  } catch (e) { /* sem ranking.json: nada a conferir */ }
  return problemas;
}

async function principal() {
  console.log('Deploy do oravel.org (Firebase, projeto ' + PROJETO + ')' + (SO_CONFERIR ? ' [so conferir]' : ''));

  // 1. Git
  console.log('\n1. Git');
  const branch = rodar('git', ['rev-parse', '--abbrev-ref', 'HEAD']).stdout.trim();
  const head = rodar('git', ['log', '-1', '--format=%h %s']).stdout.trim();
  console.log('  branch ' + branch + ' | HEAD ' + head);
  const pendentes = rodar('git', ['status', '--porcelain']).stdout.trim();
  if (pendentes && !PERMITIR_SUJO) {
    falhar('ha alteracoes nao commitadas:\n' + pendentes.split('\n').slice(0, 10).map(l => '   ' + l).join('\n') +
      '\n  Faca o commit ou use --sujo.');
  }
  if (pendentes) aviso('publicando com alteracoes nao commitadas (--sujo)');
  else ok('arvore limpa');
  const adiante = rodar('git', ['rev-list', '--count', '@{u}..HEAD']);
  if (adiante.status === 0 && Number(adiante.stdout) > 0) {
    aviso(adiante.stdout.trim() + ' commit(s) local(is) ainda nao enviados ao GitHub');
  }

  // 2. Build
  console.log('\n2. Build (Eleventy + pagefind)');
  // Limpa a saida antes: o Eleventy nao apaga arquivos de builds anteriores, e eles iriam ao ar.
  fs.rmSync(path.join(RAIZ, '_site'), { recursive: true, force: true });
  const build = rodar('npm', ['run', 'build'], { stdio: 'inherit' });
  if (build.status !== 0) falhar('o build falhou.');
  const caminhoHome = path.join(RAIZ, '_site', 'index.html');
  if (!fs.existsSync(caminhoHome)) falhar('_site/index.html nao foi gerado.');
  ok('build concluido');

  // 3 e 4. Home local
  console.log('\n3. Home gerada');
  const homeLocal = fs.readFileSync(caminhoHome, 'utf8');
  const faltando = marcadoresFaltando(homeLocal);
  if (faltando.length) falhar('a home gerada perdeu elementos do layout: ' + faltando.join(', '));
  ok('site-header, destaques, hero e sidebar presentes');
  const demo = demoVazando(homeLocal);
  if (demo.length) falhar('dados de demonstracao iriam ao ar: ' + demo.join('; '));
  ok('sem dados de demonstracao');

  // 5. Comparacao com o que esta no ar
  console.log('\n4. Comparacao com o site no ar (' + SITE_URL + ')');
  let homeNoAr = null;
  try {
    homeNoAr = await baixar(SITE_URL + '/?v=' + Date.now());
    const removidas = linhasExclusivas(homeNoAr, homeLocal);
    const acrescentadas = linhasExclusivas(homeLocal, homeNoAr);
    console.log('  home: ' + removidas + ' linha(s) removida(s), ' + acrescentadas + ' acrescentada(s) em relacao ao ar');
    if (removidas > 0) aviso('a home perde linhas em relacao ao ar; confirme que e intencional');
    const faltandoNoAr = marcadoresFaltando(homeNoAr);
    if (faltandoNoAr.length) aviso('a home que esta no ar ja nao tinha: ' + faltandoNoAr.join(', '));
  } catch (e) {
    aviso('nao foi possivel ler a home no ar (' + e.message + ')');
  }
  try {
    const sitemapNoAr = extrairLocs(await baixar(SITE_URL + '/sitemap.xml?v=' + Date.now()));
    const caminhoSitemap = path.join(RAIZ, '_site', 'sitemap.xml');
    const sitemapLocal = fs.existsSync(caminhoSitemap) ? extrairLocs(fs.readFileSync(caminhoSitemap, 'utf8')) : new Set();
    const sumiriam = [...sitemapNoAr].filter(u => !sitemapLocal.has(u));
    const novas = [...sitemapLocal].filter(u => !sitemapNoAr.has(u));
    console.log('  sitemap: ' + sitemapNoAr.size + ' URL(s) no ar, ' + sitemapLocal.size + ' no build, ' + novas.length + ' nova(s)');
    if (sumiriam.length && !PERMITIR_REMOCOES) {
      falhar(sumiriam.length + ' URL(s) que esta(ao) no ar deixariam de existir:\n' +
        sumiriam.slice(0, 15).map(u => '   ' + u).join('\n') + '\n  Se for intencional, use --permitir-remocoes.');
    }
    if (sumiriam.length) aviso(sumiriam.length + ' URL(s) serao removidas (--permitir-remocoes)');
    else ok('nenhuma URL do ar deixa de existir');
  } catch (e) {
    aviso('nao foi possivel comparar o sitemap (' + e.message + ')');
  }

  if (SO_CONFERIR) {
    console.log('\nConferencias concluidas. Nada foi publicado (--so-conferir).');
    return;
  }

  // 6. Deploy
  console.log('\n5. Publicando no Firebase Hosting');
  const firebase = path.join(RAIZ, 'node_modules', '.bin', 'firebase');
  const deploy = rodar(firebase, ['deploy', '--only', 'hosting', '--project', PROJETO], { stdio: 'inherit' });
  if (deploy.status !== 0) falhar('o firebase deploy falhou (codigo ' + deploy.status + '). Se pediu login: npx firebase login');

  // 7. Verificacao pos-deploy
  console.log('\n6. Verificacao no ar');
  await new Promise(r => setTimeout(r, 5000));
  try {
    const depois = await baixar(SITE_URL + '/?v=' + Date.now());
    const f = marcadoresFaltando(depois);
    if (f.length) {
      console.error('  ATENCAO: a home no ar nao tem: ' + f.join(', ') + '. Reverta pelo console do Firebase (Hosting > historico de versoes).');
      process.exitCode = 2;
    } else {
      ok('home no ar com site-header, destaques, hero e sidebar');
    }
    if (depois === homeLocal) ok('home no ar identica ao build publicado');
    else aviso('home no ar difere do build (pode ser cache; confira novamente em instantes)');
  } catch (e) {
    aviso('nao foi possivel verificar o site no ar (' + e.message + ')');
  }
  console.log('\nDeploy concluido. Para voltar atras: console do Firebase > Hosting > historico de versoes > Reverter.');
}

module.exports = { marcadoresFaltando, linhasExclusivas, extrairLocs, demoVazando };

if (require.main === module) {
  principal().catch(e => falhar(e.stack || String(e)));
}
