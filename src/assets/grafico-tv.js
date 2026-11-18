/*
 * Graficos de mercado da TradingView (web components), carregados sob demanda.
 * Uso: <div data-grafico-tv data-elemento="tv-mini-chart" data-simbolo="BMFBOVESPA:INDX">fallback</div>
 * O script de terceiros so e baixado quando o grafico esta perto de aparecer na
 * tela, para nao enviar dados do visitante antes da hora nem pesar no
 * carregamento. Se falhar, ou sem JavaScript, fica o texto de fallback.
 */
(function () {
  var areas = document.querySelectorAll('[data-grafico-tv]');
  if (!areas.length) return;
  var BASE = 'https://widgets.tradingview-widget.com/w/br/';
  var permitidos = { 'tv-mini-chart': true, 'tv-advanced-chart': true };
  var scripts = {};

  function carregarScript(elemento) {
    if (scripts[elemento]) return;
    scripts[elemento] = true;
    var s = document.createElement('script');
    s.type = 'module';
    s.src = BASE + elemento + '.js';
    document.head.appendChild(s);
  }

  function montar(area) {
    var elemento = area.getAttribute('data-elemento');
    var simbolo = area.getAttribute('data-simbolo');
    if (!permitidos[elemento] || !simbolo) return;
    carregarScript(elemento);
    customElements.whenDefined(elemento).then(function () {
      var g = document.createElement(elemento);
      g.setAttribute('symbol', simbolo);
      var extras = area.getAttribute('data-atributos');
      if (extras) {
        try {
          var obj = JSON.parse(extras);
          Object.keys(obj).forEach(function (k) { g.setAttribute(k, obj[k]); });
        } catch (e) { /* ignora atributos invalidos */ }
      }
      while (area.firstChild) area.removeChild(area.firstChild);
      area.appendChild(g);
    });
  }

  if ('IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function (itens) {
      itens.forEach(function (i) {
        if (i.isIntersecting) { obs.unobserve(i.target); montar(i.target); }
      });
    }, { rootMargin: '200px' });
    areas.forEach(function (a) { obs.observe(a); });
  } else {
    areas.forEach(montar);
  }
})();
