// Funções puras de telefone. Sem DOM, sem rede: rodam no navegador e no Node (testes).
(function (root, factory) {
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.OravelLib = Object.assign(root.OravelLib || {}, api);
})(typeof self !== 'undefined' ? self : globalThis, function () {
  /** Só dígitos; 10/11 dígitos (DDD + número) ganham o 55 do Brasil. Devolve '' se não parecer telefone. */
  function normalizarTelefone(texto) {
    var d = String(texto == null ? '' : texto).replace(/\D/g, '');
    if (d.length === 10 || d.length === 11) d = '55' + d;
    if (d.length < 12 || d.length > 15) return '';
    return d;
  }

  /** O título do chat é um telefone (contato não salvo)? Ex.: "+55 19 99999-0000". */
  function pareceTelefone(titulo) {
    var t = String(titulo || '').trim();
    return /^\+?[\d\s().-]{10,20}$/.test(t) && normalizarTelefone(t) !== '';
  }

  /** Telefone com máscara simples para exibir. */
  function formatarTelefone(tel) {
    var d = normalizarTelefone(tel);
    if (!d) return '';
    var ddd = d.slice(2, 4), n = d.slice(4);
    return '+55 ' + ddd + ' ' + (n.length === 9 ? n.slice(0, 5) + '-' + n.slice(5) : n.slice(0, 4) + '-' + n.slice(4));
  }

  return { normalizarTelefone: normalizarTelefone, pareceTelefone: pareceTelefone, formatarTelefone: formatarTelefone };
});
