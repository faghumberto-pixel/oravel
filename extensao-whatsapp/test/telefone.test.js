const test = require('node:test');
const assert = require('node:assert');
const { normalizarTelefone, pareceTelefone, formatarTelefone } = require('../lib/telefone.js');

test('normalizarTelefone adiciona 55 e remove máscara', () => {
  assert.strictEqual(normalizarTelefone('(19) 99999-0000'), '5519999990000');
  assert.strictEqual(normalizarTelefone('+55 19 99999-0000'), '5519999990000');
  assert.strictEqual(normalizarTelefone('1933334444'), '551933334444');
  assert.strictEqual(normalizarTelefone('5519999990000'), '5519999990000');
});

test('normalizarTelefone rejeita o que não é telefone', () => {
  assert.strictEqual(normalizarTelefone(''), '');
  assert.strictEqual(normalizarTelefone(null), '');
  assert.strictEqual(normalizarTelefone('123'), '');
  assert.strictEqual(normalizarTelefone('Maria Silva'), '');
  assert.strictEqual(normalizarTelefone('1234567890123456789'), '');
});

test('pareceTelefone distingue título numérico de nome', () => {
  assert.ok(pareceTelefone('+55 19 99999-0000'));
  assert.ok(pareceTelefone('(19) 3333-4444'));
  assert.ok(!pareceTelefone('Maria Silva'));
  assert.ok(!pareceTelefone('Oravel 2026'));
  assert.ok(!pareceTelefone(''));
});

test('formatarTelefone', () => {
  assert.strictEqual(formatarTelefone('5519999990000'), '+55 19 99999-0000');
  assert.strictEqual(formatarTelefone('551933334444'), '+55 19 3333-4444');
  assert.strictEqual(formatarTelefone('abc'), '');
});
