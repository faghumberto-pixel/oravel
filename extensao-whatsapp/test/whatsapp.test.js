const test = require('node:test');
const assert = require('node:assert');
const W = require('../lib/whatsapp.js');

test('interpretarId distingue enviada/recebida e tipo de chat', () => {
  assert.deepStrictEqual(W.interpretarId('false_5519999990000@c.us_3EB0ABC'), { enviada: false, origem: '5519999990000', tipo: 'c.us' });
  assert.deepStrictEqual(W.interpretarId('true_5519999990000@c.us_3EB0ABC'), { enviada: true, origem: '5519999990000', tipo: 'c.us' });
  assert.strictEqual(W.interpretarId('false_120363@g.us_ABC').tipo, 'g.us');
  assert.strictEqual(W.interpretarId('lixo'), null);
  assert.strictEqual(W.interpretarId(undefined), null);
});

test('telefoneDosIds usa só conversa 1:1 e ignora grupo e contato oculto', () => {
  assert.strictEqual(W.telefoneDosIds(['false_5519999990000@c.us_A', 'true_5519999990000@c.us_B']), '5519999990000');
  assert.strictEqual(W.telefoneDosIds(['false_120363@g.us_A']), '');
  assert.strictEqual(W.telefoneDosIds(['false_98765432101@lid_A']), '');
  assert.strictEqual(W.telefoneDosIds([]), '');
});

test('ehGrupo', () => {
  assert.ok(W.ehGrupo(['false_5519999990000@g.us_A']));
  assert.ok(!W.ehGrupo(['false_5519999990000@c.us_A']));
});

test('interpretarPrefixo lê hora, data e autor', () => {
  assert.deepStrictEqual(W.interpretarPrefixo('[14:32, 16/10/2026] Maria Souza: '), { hora: '14:32', data: '16/10/2026', autor: 'Maria Souza' });
  assert.deepStrictEqual(W.interpretarPrefixo('[2:05 PM, 10/16/2026] João: '), { hora: '2:05 PM', data: '10/16/2026', autor: 'João' });
  assert.strictEqual(W.interpretarPrefixo('qualquer coisa'), null);
});

const linhas = [
  { id: 'false_5519999990000@c.us_1', pre: '[14:32, 16/10/2026] Maria: ', texto: 'Preciso de 2 geradores' },
  { id: 'true_5519999990000@c.us_2', pre: '[14:35, 16/10/2026] Eu: ', texto: 'Posso enviar a proposta hoje' },
  { id: 'false_5519999990000@c.us_3', pre: '', texto: '   ' },               // figurinha/áudio: sem texto
  { id: 'false_5519999990000@c.us_4', pre: '[14:40, 16/10/2026] Maria: ', texto: 'Pode sim' },
];

test('montarMensagens ignora mídia sem texto e marca a direção', () => {
  const m = W.montarMensagens(linhas);
  assert.strictEqual(m.length, 3);
  assert.deepStrictEqual(m.map((x) => x.enviada), [false, true, false]);
  assert.strictEqual(m[0].autor, 'Maria');
  assert.deepStrictEqual(W.montarMensagens(null), []);
});

test('formatarConversa e direcaoSugerida', () => {
  const m = W.montarMensagens(linhas);
  assert.strictEqual(W.formatarConversa(m, 'Fag'),
    '[16/10/2026 14:32] Maria: Preciso de 2 geradores\n[16/10/2026 14:35] Fag: Posso enviar a proposta hoje\n[16/10/2026 14:40] Maria: Pode sim');
  assert.strictEqual(W.direcaoSugerida(m), 'entrada');
  assert.strictEqual(W.direcaoSugerida(m.slice(0, 2)), 'saida');
  assert.strictEqual(W.direcaoSugerida([]), 'entrada');
});
