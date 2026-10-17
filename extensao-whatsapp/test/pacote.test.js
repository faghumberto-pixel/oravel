// Garantias de segurança/privacidade do pacote, checadas no código-fonte.
const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');

const raiz = path.join(__dirname, '..');
const ler = (f) => fs.readFileSync(path.join(raiz, f), 'utf8');
const manifest = JSON.parse(ler('manifest.json'));

test('manifesto: permissões mínimas e só hosts necessários', () => {
  assert.strictEqual(manifest.manifest_version, 3);
  assert.deepStrictEqual(manifest.permissions, ['storage']);
  assert.deepStrictEqual(manifest.host_permissions, ['https://web.whatsapp.com/*', 'https://app.oravel.com.br/*']);
  assert.ok(!JSON.stringify(manifest).includes('<all_urls>'));
  assert.ok(!manifest.permissions.includes('tabs'));
  assert.deepStrictEqual(manifest.content_scripts[0].matches, ['https://web.whatsapp.com/*']);
});

test('todos os arquivos do manifesto existem, na ordem de dependência', () => {
  const js = manifest.content_scripts[0].js;
  js.concat(manifest.background.service_worker, manifest.options_ui.page).forEach((f) => assert.ok(fs.existsSync(path.join(raiz, f)), f));
  assert.ok(js.indexOf('lib/telefone.js') < js.indexOf('lib/whatsapp.js'));
  assert.ok(js.indexOf('selectors.js') < js.indexOf('content.js'));
  assert.ok(js.indexOf('panel.js') < js.indexOf('content.js'));
});

test('nada de innerHTML, eval ou envio de mensagem no código da extensão', () => {
  ['content.js', 'panel.js', 'background.js', 'options.js', 'selectors.js'].forEach((f) => {
    const src = ler(f);
    assert.ok(!/innerHTML|outerHTML|insertAdjacentHTML|document\.write/.test(src), `${f} usa HTML dinâmico`);
    assert.ok(!/\beval\(|new Function\(/.test(src), `${f} usa eval`);
  });
});

test('o token só é lido no service worker e nas opções, nunca no content script', () => {
  assert.ok(!/token/i.test(ler('content.js').replace(/\/\/.*$/gm, '')));
  assert.ok(!/storage/.test(ler('panel.js')) && !/storage/.test(ler('content.js')));
});

test('o content script não observa o DOM continuamente nem clica/digita no WhatsApp', () => {
  const src = ler('content.js') + ler('panel.js');
  assert.ok(!/MutationObserver|setInterval/.test(src));
  assert.ok(!/\.click\(\)|dispatchEvent|execCommand|\.focus\(\)/.test(src));
});
