(function () {
  var d = document, $ = function (s, r) { return (r || d).querySelector(s); }, all = function (s) { return [].slice.call(d.querySelectorAll(s)); };
  var track = function (l) { try { if (window.ovTrack) { window.ovTrack(l); } } catch (e) {} };
  var dlg = $('#teste-gratis'), formsCad = all('form[data-cadastro]');

  // Turnstile (anti-robô): o script só carrega quando alguém mexe num formulário (ou o pop-up abre), para não pesar na página.
  var tsCarregado = false;
  function carregarTurnstile() {
    if (tsCarregado || !$('.cf-turnstile')) { return; }
    tsCarregado = true;
    var s = d.createElement('script'); s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js'; s.async = true; s.defer = true; d.head.appendChild(s);
  }

  // Botões "Começar teste grátis": o do topo leva ao formulário do hero; os demais abrem o pop-up. Sem JS, o link leva à tela de cadastro do app.
  all('[data-cta]').forEach(function (a) {
    a.addEventListener('click', function (e) {
      var pos = a.getAttribute('data-pos') || '';
      track('Teste grátis: clicou no botão (' + pos + ')');
      var hero = $('#cadastro');
      var noTopo = hero && hero.getBoundingClientRect().top > -hero.offsetHeight * 0.6 && hero.getBoundingClientRect().top < window.innerHeight;
      if (pos === 'topo' || noTopo) {
        if (!hero) { return; }
        e.preventDefault();
        hero.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var n = $('input[name=nome]', hero); if (n) { setTimeout(function () { n.focus({ preventScroll: true }); }, 350); }
        carregarTurnstile();
        return;
      }
      if (!dlg || typeof dlg.showModal !== 'function') { return; }
      e.preventDefault();
      dlg.showModal();
      carregarTurnstile();
      var n2 = $('input[name=nome]', dlg); if (n2) { n2.focus(); }
    });
  });
  all('[data-fechar]').forEach(function (b) { b.addEventListener('click', function () { dlg.close(); }); });
  if (dlg) { dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } }); }

  formsCad.forEach(function (form) {
    // Mostrar/ocultar a senha (ajuda a não errar a digitação, já que o cadastro tem um campo só)
    [].slice.call(form.querySelectorAll('[data-ver-senha]')).forEach(function (b) {
      b.addEventListener('click', function () {
        var campo = b.previousElementSibling, ver = campo.type === 'password';
        campo.type = ver ? 'text' : 'password'; b.textContent = ver ? 'Ocultar' : 'Mostrar';
        b.setAttribute('aria-pressed', ver ? 'true' : 'false'); b.setAttribute('aria-label', ver ? 'Ocultar a senha' : 'Mostrar a senha');
      });
    });
    form.addEventListener('focusin', carregarTurnstile, { once: true });
    form.addEventListener('submit', function (e) {
      // cada formulário tem o seu próprio desafio: o campo escondido só vem preenchido quando ele termina
      var widget = $('.cf-turnstile', form), tok = $('[name="cf-turnstile-response"]', form);
      if (widget && !(tok && tok.value)) {
        e.preventDefault();
        var a = $('[data-ts-aviso]', form);
        if (!a) { a = d.createElement('p'); a.setAttribute('data-ts-aviso', ''); a.setAttribute('role', 'alert'); a.className = 'aviso-ts'; a.textContent = 'Aguarde a verificação anti-robô terminar (alguns segundos) e envie de novo.'; widget.insertAdjacentElement('afterend', a); }
        a.hidden = false;
        carregarTurnstile();
        return;
      }
      track('Teste grátis: enviou o formulário');
      var b = $('button[type=submit]', form); if (b) { b.disabled = true; b.textContent = 'Enviando...'; }
    });
  });
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
