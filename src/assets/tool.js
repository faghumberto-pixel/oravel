/* Runtime do Centro de Ferramentas (Oravel.org).
 * Cada ferramenta registra uma definicao: OravelTool.register({ slug, passos, calcular }).
 * O runtime cuida de: uma pergunta por etapa, progresso, autosave (localStorage),
 * resultado, PDF (impressao), compartilhamento e eventos de uso anonimos. */
(function () {
  'use strict';
  var defs = {};
  var $ = function (t, a, h) { var e = document.createElement(t); if (a) for (var k in a) { if (k === 'class') e.className = a[k]; else e.setAttribute(k, a[k]); } if (h != null) e.textContent = h; return e; };

  var fmt = {
    int: function (v) { return Math.round(v).toLocaleString('pt-BR'); },
    brl: function (v) { return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }); },
    pct: function (v) { return v.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%'; },
    num: function (v) { return v.toLocaleString('pt-BR', { maximumFractionDigits: 2 }); }
  };

  function store(key, val) {
    try { if (val === undefined) return JSON.parse(localStorage.getItem(key)); if (val === null) localStorage.removeItem(key); else localStorage.setItem(key, JSON.stringify(val)); } catch (e) { return null; }
  }

  function evento(wrap, nome, extra) {
    var slug = wrap.dataset.tool;
    try { if (window.plausible) window.plausible(nome, { props: Object.assign({ ferramenta: slug }, extra || {}) }); } catch (e) {}
    var url = wrap.dataset.telemetry;
    if (url && navigator.sendBeacon) {
      try { navigator.sendBeacon(url, JSON.stringify(Object.assign({ evento: nome, ferramenta: slug, em: new Date().toISOString() }, extra || {}))); } catch (e) {}
    }
  }

  function parseNum(s) { s = String(s).trim().replace(/\./g, '').replace(',', '.'); var n = parseFloat(s); return isNaN(n) ? null : n; }

  function iniciar(wrap, def) {
    var root = wrap.querySelector('#tool-root');
    var chave = 'oravel:tool:' + def.slug;
    var estado = store(chave) || { i: -1, v: {} };
    var tInicio = Date.now();
    var concluido = false;

    // Estado compartilhado no link: #r=<base64 json dos valores>
    var m = /[#&]r=([^&]+)/.exec(location.hash);
    if (m) { try { estado = { i: def.passos.length, v: JSON.parse(decodeURIComponent(escape(atob(m[1])))) }; } catch (e) {} }

    function salvar() { store(chave, estado); }
    function limpar() { store(chave, null); estado = { i: -1, v: {} }; history.replaceState(null, '', location.pathname); render(); }

    function barra(i) {
      var total = def.passos.length;
      var pct = Math.round((Math.max(i, 0) / total) * 100);
      var b = $('div', { class: 'tool-progress', role: 'progressbar', 'aria-valuemin': 0, 'aria-valuemax': total, 'aria-valuenow': Math.max(i, 0), 'aria-label': 'Progresso' });
      var f = $('div', { class: 'tool-progress-fill' }); f.style.width = pct + '%'; b.appendChild(f);
      var w = $('div', { class: 'tool-progress-wrap tool-noprint' });
      w.appendChild(b);
      w.appendChild($('p', { class: 'tool-progress-txt' }, 'Etapa ' + (Math.max(i, 0) + 1) + ' de ' + total));
      return w;
    }

    function renderIntro() {
      root.textContent = '';
      var c = $('div', { class: 'tool-card tool-intro' });
      c.appendChild($('h2', null, def.introTitulo || 'Vamos começar'));
      c.appendChild($('p', null, def.intro || ''));
      var temSalvo = estado.v && Object.keys(estado.v).length;
      var b = $('button', { class: 'btn', type: 'button' }, 'Começar'); b.onclick = function () { estado.i = 0; salvar(); evento(wrap, 'tool_start'); render(); };
      c.appendChild(b);
      if (temSalvo) {
        var r = $('button', { class: 'tool-link', type: 'button' }, 'Continuar de onde parei');
        r.onclick = function () { estado.i = Math.min(Object.keys(estado.v).length, def.passos.length - 1); render(); };
        c.appendChild(r);
      }
      root.appendChild(c);
    }

    function renderPasso() {
      var i = estado.i, p = def.passos[i];
      root.textContent = '';
      root.appendChild(barra(i));
      var c = $('form', { class: 'tool-card', novalidate: '' });
      var lab = $('label', { for: 'tool-campo', class: 'tool-pergunta' }, p.pergunta);
      c.appendChild(lab);
      if (p.ajuda) c.appendChild($('p', { class: 'tool-ajuda', id: 'tool-ajuda' }, p.ajuda));
      var campo;
      if (p.tipo === 'opcoes') {
        campo = $('select', { id: 'tool-campo', class: 'tool-input' });
        if (!p.obrigatorio) campo.appendChild($('option', { value: '' }, 'Prefiro não informar'));
        p.opcoes.forEach(function (o) { campo.appendChild($('option', { value: o }, o)); });
        campo.value = estado.v[p.id] != null ? estado.v[p.id] : '';
      } else {
        var wrapIn = $('div', { class: 'tool-input-wrap' });
        if (p.prefixo) wrapIn.appendChild($('span', { class: 'tool-affix' }, p.prefixo));
        campo = $('input', { id: 'tool-campo', class: 'tool-input', inputmode: 'decimal', autocomplete: 'off' });
        var atual = estado.v[p.id] != null ? estado.v[p.id] : p.padrao;
        campo.value = atual != null ? String(atual).replace('.', ',') : '';
        wrapIn.appendChild(campo);
        if (p.sufixo) wrapIn.appendChild($('span', { class: 'tool-affix' }, p.sufixo));
        c.appendChild(wrapIn);
      }
      if (p.ajuda) campo.setAttribute('aria-describedby', 'tool-ajuda');
      if (p.tipo === 'opcoes') c.appendChild(campo);
      var erro = $('p', { class: 'tool-erro', role: 'alert' }); c.appendChild(erro);

      var nav = $('div', { class: 'tool-nav' });
      var voltar = $('button', { class: 'tool-link', type: 'button' }, 'Voltar');
      voltar.onclick = function () { estado.i = i - 1; salvar(); render(); };
      var prox = $('button', { class: 'btn', type: 'submit' }, i === def.passos.length - 1 ? 'Ver resultado' : 'Continuar');
      nav.appendChild(voltar); nav.appendChild(prox); c.appendChild(nav);

      c.onsubmit = function (ev) {
        ev.preventDefault();
        var val;
        if (p.tipo === 'opcoes') { val = campo.value || null; if (p.obrigatorio && !val) { erro.textContent = 'Escolha uma opção.'; return; } }
        else {
          val = parseNum(campo.value);
          if (val == null) { if (p.obrigatorio === false) val = null; else { erro.textContent = 'Informe um número.'; campo.focus(); return; } }
          if (val != null && ((p.min != null && val < p.min) || (p.max != null && val > p.max))) {
            erro.textContent = 'Use um valor entre ' + (p.min != null ? p.min : '0') + ' e ' + (p.max != null ? p.max : 'sem limite') + '.'; campo.focus(); return;
          }
        }
        estado.v[p.id] = val; estado.i = i + 1; salvar(); render();
      };
      root.appendChild(c);
      campo.focus({ preventScroll: true });
    }

    function renderResultado() {
      var r = def.calcular(estado.v);
      root.textContent = '';
      if (!concluido) { concluido = true; evento(wrap, 'tool_complete', { segundos: Math.round((Date.now() - tInicio) / 1000), uf: estado.v.uf || '' }); }

      var cab = $('div', { class: 'tool-result-head' });
      cab.appendChild($('p', { class: 'tool-eyebrow' }, wrap.dataset.toolTitle));
      cab.appendChild($('h2', null, r.titulo));
      cab.appendChild($('p', { class: 'tool-resumo' }, r.resumo));
      root.appendChild(cab);

      var g = $('div', { class: 'tool-kpis' });
      r.indicadores.forEach(function (k) {
        var d = $('div', { class: 'tool-kpi' });
        d.appendChild($('span', null, k.rotulo));
        d.appendChild($('strong', null, fmt[k.formato || 'num'](k.valor)));
        if (k.nota) d.appendChild($('small', null, k.nota));
        g.appendChild(d);
      });
      root.appendChild(g);

      if (r.grafico) {
        var gc = $('section', { class: 'tool-card' });
        gc.appendChild($('h3', null, r.grafico.titulo));
        var max = Math.max.apply(null, r.grafico.itens.map(function (x) { return x.valor; })) || 1;
        var ul = $('ul', { class: 'tool-bars' });
        r.grafico.itens.forEach(function (x) {
          var li = $('li');
          li.appendChild($('span', { class: 'tool-bar-rot' }, x.rotulo));
          var tr = $('span', { class: 'tool-bar-track' }); var fl = $('span', { class: 'tool-bar-fill' }); fl.style.width = Math.max(2, (x.valor / max) * 100) + '%'; tr.appendChild(fl); li.appendChild(tr);
          li.appendChild($('span', { class: 'tool-bar-val' }, fmt[r.grafico.formato || 'num'](x.valor)));
          ul.appendChild(li);
        });
        gc.appendChild(ul); root.appendChild(gc);
      }

      [['Conclusões', r.conclusoes], ['Sugestões práticas', r.sugestoes]].forEach(function (par) {
        if (!par[1] || !par[1].length) return;
        var s = $('section', { class: 'tool-card' }); s.appendChild($('h3', null, par[0]));
        var ul = $('ul', { class: 'tool-lista' }); par[1].forEach(function (t) { ul.appendChild($('li', null, t)); }); s.appendChild(ul); root.appendChild(s);
      });

      var prem = $('details', { class: 'tool-card tool-premissas' });
      prem.appendChild($('summary', null, 'Premissas e dados informados'));
      var pl = $('ul', { class: 'tool-lista' });
      def.passos.forEach(function (p) { var v = estado.v[p.id]; pl.appendChild($('li', null, p.rotulo + ': ' + (v == null ? 'não informado' : (p.tipo === 'opcoes' ? v : fmt.num(v) + (p.sufixo ? ' ' + p.sufixo : ''))))); });
      (r.premissas || []).forEach(function (t) { pl.appendChild($('li', null, t)); });
      prem.appendChild(pl); root.appendChild(prem);

      root.appendChild($('p', { class: 'tool-print-rodape' }, 'Gerado gratuitamente pela Oravel.org · oravel.org' + wrap.dataset.tool.replace(/^/, '/ferramentas/') + '/ · ' + new Date().toLocaleDateString('pt-BR')));

      var acoes = $('div', { class: 'tool-acoes tool-noprint' });
      var pdf = $('button', { class: 'btn', type: 'button' }, 'Baixar PDF');
      pdf.onclick = function () { evento(wrap, 'tool_pdf'); document.title = def.slug + '-oravel'; var d = root.querySelector('details'); if (d) d.open = true; window.print(); };
      acoes.appendChild(pdf);
      acoes.appendChild(dropShare());
      var art = document.querySelector('.tool-related-list a');
      if (art) acoes.appendChild($('a', { class: 'tool-link', href: '#tool-rel-titulo' }, 'Ler artigo relacionado'));
      var vol = $('button', { class: 'tool-link', type: 'button' }, 'Refazer'); vol.onclick = limpar; acoes.appendChild(vol);
      root.appendChild(acoes);
      root.appendChild(dropEmailNota());
      root.querySelector('.tool-result-head').setAttribute('tabindex', '-1');
      root.querySelector('.tool-result-head').focus({ preventScroll: false });
    }

    function linkCompartilhar() {
      var h = btoa(unescape(encodeURIComponent(JSON.stringify(estado.v))));
      return location.origin + location.pathname + '#r=' + h;
    }

    function dropShare() {
      var box = $('div', { class: 'tool-share' });
      var txt = 'Calculei com a ' + wrap.dataset.toolTitle + ' da Oravel.org';
      [['WhatsApp', function (u) { return 'https://wa.me/?text=' + encodeURIComponent(txt + ' ' + u); }],
       ['LinkedIn', function (u) { return 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(u); }],
       ['Facebook', function (u) { return 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(u); }]
      ].forEach(function (n) {
        var a = $('a', { class: 'tool-share-btn', href: '#', target: '_blank', rel: 'noopener noreferrer' }, n[0]);
        a.onclick = function () { evento(wrap, 'tool_share', { canal: n[0] }); a.href = n[1](location.origin + location.pathname); };
        box.appendChild(a);
      });
      var cp = $('button', { class: 'tool-share-btn', type: 'button' }, 'Copiar link com resultado');
      cp.onclick = function () {
        var u = linkCompartilhar(); evento(wrap, 'tool_share', { canal: 'link' });
        (navigator.clipboard ? navigator.clipboard.writeText(u) : Promise.reject()).then(function () { cp.textContent = 'Link copiado'; }, function () { window.prompt('Copie o link:', u); });
      };
      box.appendChild(cp);
      return box;
    }

    function dropEmailNota() {
      var n = $('p', { class: 'tool-nota tool-noprint' }, 'Os cálculos rodam no seu navegador. Nenhum dado seu é enviado sem sua autorização.');
      return n;
    }

    function render() {
      if (estado.i < 0) renderIntro();
      else if (estado.i >= def.passos.length) renderResultado();
      else renderPasso();
      if (estado.i >= 0 && estado.i < def.passos.length) { /* foco ja tratado */ }
    }
    render();
  }

  window.OravelTool = {
    fmt: fmt,
    register: function (def) {
      defs[def.slug] = def;
      var w = document.querySelector('[data-tool="' + def.slug + '"]');
      if (w) iniciar(w, def);
    }
  };
})();
