(function () {
  var d = document, $ = function (s, r) { return (r || d).querySelector(s); }, all = function (s) { return [].slice.call(d.querySelectorAll(s)); };
  var track = function (l) { try { if (window.ovTrack) { window.ovTrack(l); } } catch (e) {} };
  var dlg = $('#teste-gratis'), form = dlg && $('form', dlg);

  // CTA: abre o formulário sem sair do site. Sem suporte a <dialog> ou sem JS, o link leva à mesma tela de cadastro no app.
  all('[data-cta]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (!dlg || typeof dlg.showModal !== 'function') { return; }
      e.preventDefault();
      track('Teste grátis: abriu o formulário (' + (a.getAttribute('data-pos') || '') + ')');
      dlg.showModal();
      carregarTurnstile();
      var n = $('input[name=nome]', dlg); if (n) { n.focus(); }
    });
  });
  // Turnstile (anti-robô): o script só carrega quando o formulário abre, para não pesar na página. Sem o widget no HTML, nada acontece.
  var tsOk = false, tsCarregado = false;
  window.ovTsOk = function () { tsOk = true; var a = $('[data-ts-aviso]', dlg); if (a) { a.hidden = true; } };
  window.ovTsExp = function () { tsOk = false; };
  function carregarTurnstile() {
    if (tsCarregado || !$('.cf-turnstile', dlg)) { return; }
    tsCarregado = true;
    var s = d.createElement('script'); s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js'; s.async = true; s.defer = true; d.head.appendChild(s);
  }
  all('[data-fechar]').forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
  if (dlg) { dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } }); }
  if (form) {
    form.addEventListener('submit', function (e) {
      if ($('.cf-turnstile', form) && !tsOk) {
        e.preventDefault();
        var a = $('[data-ts-aviso]', form);
        if (!a) { a = d.createElement('p'); a.setAttribute('data-ts-aviso', ''); a.setAttribute('role', 'alert'); a.className = 'micro'; a.style.color = '#b91c1c'; a.textContent = 'Aguarde a verificação anti-robô terminar (alguns segundos) e envie de novo.'; $('.cf-turnstile', form).insertAdjacentElement('afterend', a); }
        a.hidden = false;
        return;
      }
      track('Teste grátis: enviou o formulário');
      var b = $('button[type=submit]', form); if (b) { b.disabled = true; b.textContent = 'Enviando...'; }
    });
  }
  all('[data-track-click]').forEach(function (a) { a.addEventListener('click', function () { track(a.getAttribute('data-track-click')); }); });

  // Calculadora (equipamento parado). O HTML já vem com a conta feita; aqui só recalcula.
  var brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });
  var q = $('#c-qtd'), di = $('#c-diaria'), ds = $('#c-dias');
  function calc() {
    var n = Math.max(0, parseFloat(q.value) || 0), v = Math.max(0, parseFloat(di.value) || 0), x = Math.min(31, Math.max(0, parseFloat(ds.value) || 0));
    var mes = n * v * x;
    $('#c-mes').textContent = brl.format(mes); $('#c-ano').textContent = brl.format(mes * 12);
  }
  if (q && di && ds) { [q, di, ds].forEach(function (i) { i.addEventListener('input', calc); }); }

  // Carrossel
  var car = $('.carrossel');
  if (car) {
    all('[data-car]').forEach(function (b) {
      b.addEventListener('click', function () {
        var s = $('.slide', car); var w = s ? s.getBoundingClientRect().width + 16 : 300;
        car.scrollBy({ left: (b.getAttribute('data-car') === 'prox' ? 1 : -1) * w, behavior: 'smooth' });
      });
    });
  }

  // Depoimentos: só aparecem se existir registro aprovado e autorizado no sistema. Sem registro, a seção continua oculta.
  var sec = $('#depoimentos');
  if (sec && window.fetch) {
    fetch(sec.getAttribute('data-fonte'), { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (lista) {
        if (!Array.isArray(lista) || !lista.length) { return; }
        var box = $('.deps', sec);
        lista.forEach(function (t) {
          var f = d.createElement('figure'); f.className = 'dep'; f.style.margin = '0';
          var p = d.createElement('p'); p.textContent = '“' + t.texto + '”';
          var ft = d.createElement('footer'); ft.textContent = [t.nome, t.cargo, t.empresa].filter(Boolean).join(', ');
          f.appendChild(p); f.appendChild(ft); box.appendChild(f);
        });
        sec.hidden = false;
      }).catch(function () {});
  }
})();
