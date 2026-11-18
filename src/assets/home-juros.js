/*
 * Home: card "Juros, obra e câmbio".
 * Le tres series publicas do Banco Central (SGS) direto no navegador, so quando
 * o card esta perto de aparecer na tela:
 *   432  Selic, meta ao ano (%)
 *   192  INCC-M, variacao mensal (%) -> acumulado em 12 meses
 *   1    Dolar americano, venda (R$)
 * Cada numero mostra a data da propria serie. Se uma leitura falhar, aquele item
 * continua com "—" e nao afeta os outros. O resultado fica em sessionStorage por
 * 1 hora para nao repetir a consulta a cada navegacao.
 */
(function () {
  var card = document.getElementById('juros-card');
  if (!card) return;

  var BASE = 'https://api.bcb.gov.br/dados/serie/bcdata.sgs.';
  var CACHE_MS = 60 * 60 * 1000;
  var iniciado = false;

  function serie(codigo, ultimos) {
    var url = BASE + codigo + '/dados/ultimos/' + ultimos + '?formato=json';
    var chave = 'bcb:' + codigo + ':' + ultimos;
    try {
      var guardado = JSON.parse(sessionStorage.getItem(chave) || 'null');
      if (guardado && Date.now() - guardado.t < CACHE_MS) return Promise.resolve(guardado.d);
    } catch (e) { /* sem storage: segue sem cache */ }
    return fetch(url, { headers: { Accept: 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (d) {
        if (!Array.isArray(d) || !d.length) throw new Error('serie vazia');
        try { sessionStorage.setItem(chave, JSON.stringify({ t: Date.now(), d: d })); } catch (e) { /* ignora */ }
        return d;
      });
  }

  function numero(texto) { return parseFloat(String(texto)); } // a API usa ponto decimal
  function fmt(n, casas) { return n.toLocaleString('pt-BR', { minimumFractionDigits: casas, maximumFractionDigits: casas }); }

  // "dd/mm/aaaa" -> {d, m, a}
  function partes(data) {
    var p = String(data).split('/');
    return { d: +p[0], m: +p[1], a: +p[2] };
  }

  function preencher(chave, valor, rotuloData) {
    var item = card.querySelector('[data-juros="' + chave + '"]');
    if (!item) return;
    var v = item.querySelector('[data-valor]');
    var d = item.querySelector('[data-data]');
    if (v) { v.textContent = valor; v.removeAttribute('data-pendente'); }
    if (d) d.textContent = rotuloData;
  }

  function indisponivel(chave) {
    var item = card.querySelector('[data-juros="' + chave + '"]');
    if (!item) return;
    var v = item.querySelector('[data-valor]');
    var d = item.querySelector('[data-data]');
    if (v) { v.textContent = '—'; v.setAttribute('data-pendente', ''); }
    if (d) d.textContent = 'Indisponível no momento';
  }

  function selic() {
    return serie(432, 1).then(function (d) {
      var u = d[d.length - 1];
      preencher('selic', fmt(numero(u.valor), 2) + '%', 'Banco Central, ' + u.data);
    }).catch(function () { indisponivel('selic'); });
  }

  function incc() {
    return serie(192, 12).then(function (d) {
      if (d.length < 12) throw new Error('menos de 12 meses');
      // confere que sao 12 meses consecutivos antes de acumular
      for (var i = 1; i < d.length; i++) {
        var a = partes(d[i - 1].data), b = partes(d[i].data);
        if ((b.a * 12 + b.m) - (a.a * 12 + a.m) !== 1) throw new Error('meses nao consecutivos');
      }
      var acumulado = d.reduce(function (acc, x) { return acc * (1 + numero(x.valor) / 100); }, 1) - 1;
      var ult = partes(d[d.length - 1].data);
      var ref = ('0' + ult.m).slice(-2) + '/' + ult.a;
      preencher('incc', fmt(acumulado * 100, 2) + '%', 'Banco Central, até ' + ref);
    }).catch(function () { indisponivel('incc'); });
  }

  function dolar() {
    return serie(1, 1).then(function (d) {
      var u = d[d.length - 1];
      preencher('dolar', 'R$ ' + fmt(numero(u.valor), 2), 'Banco Central, ' + u.data);
    }).catch(function () { indisponivel('dolar'); });
  }

  function carregar() {
    if (iniciado) return;
    iniciado = true;
    selic(); incc(); dolar();
  }

  if ('IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function (itens) {
      if (itens.some(function (i) { return i.isIntersecting; })) { obs.disconnect(); carregar(); }
    }, { rootMargin: '200px' });
    obs.observe(card);
  } else {
    carregar();
  }
})();
