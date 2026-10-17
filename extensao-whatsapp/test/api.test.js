const test = require('node:test');
const assert = require('node:assert');
const { criarCliente, normalizarBase, ApiErro } = require('../lib/api.js');

function fakeFetch(resposta, status = 200) {
  const chamadas = [];
  const f = async (url, init) => { chamadas.push({ url, init }); return { ok: status < 400, status, json: async () => resposta }; };
  f.chamadas = chamadas;
  return f;
}

test('normalizarBase exige https, aceita localhost e tira o caminho', () => {
  assert.strictEqual(normalizarBase('https://app.oravel.com.br/'), 'https://app.oravel.com.br');
  assert.strictEqual(normalizarBase('https://app.oravel.com.br/admin?x=1'), 'https://app.oravel.com.br');
  assert.strictEqual(normalizarBase('http://localhost:8000'), 'http://localhost:8000');
  assert.strictEqual(normalizarBase('http://127.0.0.1:8000'), 'http://127.0.0.1:8000');
  assert.strictEqual(normalizarBase('http://app.oravel.com.br'), null);
  assert.strictEqual(normalizarBase('javascript:alert(1)'), null);
  assert.strictEqual(normalizarBase('não é url'), null);
});

test('criarCliente recusa URL insegura', () => {
  assert.throws(() => criarCliente({ base: 'http://exemplo.com', token: 't' }), ApiErro);
});

test('buscar envia Bearer e telefone codificado e devolve data', async () => {
  const f = fakeFetch({ data: [{ tipo: 'lead', id: '1' }] });
  const api = criarCliente({ base: 'https://app.oravel.com.br', token: 'TOK', fetchImpl: f });
  const r = await api.buscar('5519999990000');
  assert.deepStrictEqual(r, [{ tipo: 'lead', id: '1' }]);
  assert.strictEqual(f.chamadas[0].url, 'https://app.oravel.com.br/api/crm/v1/contatos/buscar?telefone=5519999990000');
  assert.strictEqual(f.chamadas[0].init.headers.Authorization, 'Bearer TOK');
  assert.strictEqual(f.chamadas[0].init.method, 'GET');
});

test('cnpj manda só dígitos; oportunidades codifica o id', async () => {
  const f = fakeFetch({ data: {} });
  const api = criarCliente({ base: 'https://x.com', token: 't', fetchImpl: f });
  await api.cnpj('11.222.333/0001-81');
  await api.oportunidades('lead', 'a/b');
  assert.ok(f.chamadas[0].url.endsWith('/cnpj/11222333000181'));
  assert.ok(f.chamadas[1].url.endsWith('/contatos/lead/a%2Fb/oportunidades'));
});

test('criarLead e moverEtapa usam JSON e o verbo certo', async () => {
  const f = fakeFetch({ data: { id: '9' } });
  const api = criarCliente({ base: 'https://x.com', token: 't', fetchImpl: f });
  await api.criarLead({ nome: 'Maria' });
  await api.moverEtapa('9', { etapa_id: 'e' });
  assert.strictEqual(f.chamadas[0].init.method, 'POST');
  assert.strictEqual(f.chamadas[0].init.body, '{"nome":"Maria"}');
  assert.strictEqual(f.chamadas[0].init.headers['Content-Type'], 'application/json');
  assert.strictEqual(f.chamadas[1].init.method, 'PATCH');
  assert.ok(f.chamadas[1].url.endsWith('/leads/9/etapa'));
});

test('registrarInteracao sem áudio é JSON; com áudio é multipart sem Content-Type manual', async () => {
  const f = fakeFetch({ data: {} });
  const api = criarCliente({ base: 'https://x.com', token: 't', fetchImpl: f });
  await api.registrarInteracao('1', { canal: 'whatsapp', resumo: 'oi' });
  await api.registrarInteracao('1', { canal: 'whatsapp', resumo: 'oi', vazio: '' }, { blob: new Blob(['x'], { type: 'audio/ogg' }), nome: 'a.ogg' });
  assert.strictEqual(typeof f.chamadas[0].init.body, 'string');
  assert.ok(f.chamadas[1].init.body instanceof FormData);
  assert.strictEqual(f.chamadas[1].init.body.get('resumo'), 'oi');
  assert.ok(f.chamadas[1].init.body.get('audio'));
  assert.strictEqual(f.chamadas[1].init.body.has('vazio'), false);
  assert.strictEqual(f.chamadas[1].init.headers['Content-Type'], undefined);
});

test('erros da API viram ApiErro com código, mensagem e campos', async () => {
  const f = fakeFetch({ erro: { codigo: 'duplicado', mensagem: 'Já existe.', campos: { documento: ['x'] } } }, 409);
  const api = criarCliente({ base: 'https://x.com', token: 't', fetchImpl: f });
  await assert.rejects(api.criarLead({}), (e) => e instanceof ApiErro && e.status === 409 && e.codigo === 'duplicado' && e.message === 'Já existe.' && e.campos.documento[0] === 'x');
});

test('401, 429 e corpo não-JSON ganham mensagem útil', async () => {
  const mk = (resp, st) => criarCliente({ base: 'https://x.com', token: 't', fetchImpl: fakeFetch(resp, st) });
  await assert.rejects(mk({ erro: { codigo: 'nao_autenticado' } }, 401).eu(), (e) => /expirado/.test(e.message));
  await assert.rejects(mk(null, 429).eu(), (e) => e.codigo === 'muitas_requisicoes' && /Aguarde/.test(e.message));
  const html = { ok: false, status: 502, json: async () => { throw new Error('html'); } };
  await assert.rejects(criarCliente({ base: 'https://x.com', token: 't', fetchImpl: async () => html }).eu(), (e) => e.codigo === 'erro_502');
});

test('falha de rede vira sem_conexao', async () => {
  const api = criarCliente({ base: 'https://x.com', token: 't', fetchImpl: async () => { throw new TypeError('network'); } });
  await assert.rejects(api.eu(), (e) => e.codigo === 'sem_conexao');
});
