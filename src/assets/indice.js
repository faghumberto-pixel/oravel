/* Indice Oravel: pesquisa publica e anonima. Uma pergunta por tela; a resposta so' sai do navegador no fim.
   Nada identifica o respondente: o contato (opcional) vai por outra chamada e nao se liga as respostas. */
(function () {
  var raiz = document.getElementById('indice');
  if (!raiz) return;

  var API = (raiz.getAttribute('data-api') || '').replace(/\/+$/, '');
  var SITEKEY = raiz.getAttribute('data-turnstile') || '';
  var $ = function (s, ctx) { return (ctx || raiz).querySelector(s); };
  var telas = {};
  Array.prototype.forEach.call(raiz.querySelectorAll('[data-tela]'), function (el) { telas[el.getAttribute('data-tela')] = el; });

  var estado = { edicao: null, perguntas: [], i: 0, respostas: {}, token: '', ultimo: null, vinculo: '' };
  var widgetId = null;

  /* Turnstile: o token vale para UM envio. Espera ele chegar e pede outro depois de cada chamada. */
  function tokenPronto(ms) {
    return new Promise(function (ok) {
      if (!SITEKEY) return ok(true);
      var t0 = Date.now();
      (function olhar() { if (estado.token) return ok(true); if (Date.now() - t0 > ms) return ok(false); setTimeout(olhar, 150); })();
    });
  }
  function renovarToken() {
    estado.token = '';
    if (SITEKEY && widgetId !== null && window.turnstile) { try { window.turnstile.reset(widgetId); } catch (e) { /* o widget pede outro sozinho */ } }
  }

  function evento(nome) { try { if (window.plausible) window.plausible(nome); } catch (e) { /* sem analytics, segue */ } }
  function jaParticipou() { try { return !!localStorage.getItem('indice-oravel-' + (estado.edicao && estado.edicao.id)); } catch (e) { return false; } }
  function marcarParticipou() { try { localStorage.setItem('indice-oravel-' + estado.edicao.id, '1'); } catch (e) { /* modo privado */ } }

  function mostrar(nome, foco) {
    Object.keys(telas).forEach(function (k) { telas[k].hidden = k !== nome; });
    estado.ultimo = nome;
    var alvo = foco !== false ? telas[nome].querySelector('[tabindex="-1"]') : null;
    if (alvo) { alvo.focus({ preventScroll: true }); }
    var topo = raiz.getBoundingClientRect().top + window.pageYOffset - 70;
    if (window.pageYOffset > topo) window.scrollTo({ top: Math.max(topo, 0), behavior: 'smooth' });
  }

  function chamar(caminho, opcoes) {
    return fetch(API + caminho, opcoes).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (corpo) { return { status: r.status, corpo: corpo }; });
    });
  }

  /* ---------- carregar a edicao ativa ---------- */
  function carregar() {
    mostrar('carregando', false);
    chamar('/', { headers: { Accept: 'application/json' } }).then(function (r) {
      if (r.status === 404) return mostrar('indisponivel');
      if (r.status !== 200 || !r.corpo.edicao || !r.corpo.perguntas || !r.corpo.perguntas.length) return erro('Não conseguimos carregar a pesquisa agora. Tente de novo em instantes.');
      estado.edicao = r.corpo.edicao; estado.perguntas = r.corpo.perguntas; estado.i = 0; estado.respostas = {};
      Array.prototype.forEach.call(raiz.querySelectorAll('[data-total]'), function (el) { el.textContent = String(estado.perguntas.length); });
      if (jaParticipou()) { return finalizar(true); }
      iniciarTurnstile();
      mostrar('intro');
    }).catch(function () { erro('Verifique a sua conexão e tente de novo.'); });
  }

  function erro(msg, retorno) {
    $('[data-msg-erro]').textContent = msg || 'Verifique a sua conexão e tente de novo. Suas respostas não se perderam.';
    estado.retorno = retorno || carregar;
    mostrar('erro');
  }

  /* ---------- perguntas ---------- */
  function pergunta() {
    var q = estado.perguntas[estado.i], n = estado.perguntas.length;
    $('[data-passo]').textContent = 'Você está no passo ' + (estado.i + 1) + ' de ' + n;
    var pct = Math.round((estado.i / n) * 100);
    $('[data-barra]').style.width = pct + '%';
    var barra = $('.ix-barra'); barra.setAttribute('aria-valuenow', String(pct));
    $('[data-pergunta]').textContent = q.texto;
    var box = $('[data-opcoes]'); box.innerHTML = '';
    box.setAttribute('aria-label', q.texto);
    q.opcoes.forEach(function (op) {
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'ix-opcao'; b.textContent = op;
      b.setAttribute('aria-pressed', estado.respostas[q.id] === op ? 'true' : 'false');
      b.addEventListener('click', function () { escolher(q, op, b); });
      box.appendChild(b);
    });
    $('[data-acao="voltar"]').hidden = estado.i === 0;
    mostrar('pergunta');
  }

  function escolher(q, op, botao) {
    estado.respostas[q.id] = op;
    Array.prototype.forEach.call($('[data-opcoes]').children, function (c) { c.setAttribute('aria-pressed', c === botao ? 'true' : 'false'); });
    var rapido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    setTimeout(function () {
      if (estado.i < estado.perguntas.length - 1) { estado.i += 1; pergunta(); } else { enviar(); }
    }, rapido ? 0 : 260);
  }

  /* ---------- envio ---------- */
  function corpoComToken(dados) { if (estado.token) dados['cf-turnstile-response'] = estado.token; return dados; }

  function enviar() {
    mostrar('enviando', false);
    var lista = estado.perguntas.map(function (q) { return { pergunta_id: q.id, valor: estado.respostas[q.id] }; });
    tokenPronto(12000).then(function (pronto) {
      if (!pronto) { return Promise.reject(new Error('sem-token')); }
      return chamar('/respostas', {
        method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(corpoComToken({ edicao_id: estado.edicao.id, versao: estado.edicao.versao, respostas: lista, website: '' })),
      });
    }).then(function (r) {
      renovarToken();
      if (r.status === 201) { estado.vinculo = (r.corpo && r.corpo.vinculo) || ''; marcarParticipou(); evento('Indice concluido'); return finalizar(false); }
      if (r.status === 409) { estado.respostas = {}; return erro('A pesquisa foi atualizada enquanto você respondia. Vamos recomeçar do início.', carregar); }
      if (r.status === 410) return mostrar('indisponivel');
      if (r.status === 429) return erro('Muitos envios em pouco tempo. Aguarde um minuto e tente de novo.', enviar);
      erro('Não foi possível enviar agora. Suas respostas estão guardadas nesta página; tente de novo.', enviar);
    }).catch(function (e) {
      renovarToken();
      erro(e && e.message === 'sem-token' ? 'A verificação de segurança ainda não terminou. Aguarde alguns segundos e tente de novo. Suas respostas estão guardadas nesta página.' : 'Verifique a sua conexão e tente de novo. Suas respostas estão guardadas nesta página.', enviar);
    });
  }

  function finalizar(jaTinha) {
    $('[data-ja]').hidden = !jaTinha;
    $('[data-bloco-contato]').hidden = !!jaTinha || !estado.vinculo;
    $('[data-sucesso-cadastro]').hidden = true;
    $('[data-explorar]').hidden = false;
    mostrar('obrigado');
  }

  /* ---------- cadastro no Observatorio (opcional, no fim) ---------- */
  var form = $('[data-form-cadastro]');
  function mensagensDoServidor(corpo) {
    if (corpo && corpo.errors) {
      var msgs = []; Object.keys(corpo.errors).forEach(function (k) { if (corpo.errors[k] && corpo.errors[k][0]) msgs.push(corpo.errors[k][0]); });
      if (msgs.length) return msgs.join(' ');
    }
    return (corpo && (corpo.mensagem || corpo.message)) || '';
  }
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var msg = $('[data-erro-cadastro]'); msg.hidden = true;
    var mostrarErro = function (t) { msg.textContent = t; msg.hidden = false; msg.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); };
    var d = {}; new FormData(form).forEach(function (valor, nome) { d[nome] = (nome === 'password' || nome === 'password_confirmation') ? String(valor) : String(valor).trim(); });
    var faltando = [['nome', 'o nome'], ['empresa', 'a empresa'], ['cnpj', 'o CNPJ'], ['cidade', 'a cidade'], ['uf', 'o estado'], ['email', 'o e-mail'], ['password', 'a senha']].filter(function (c) { return !d[c[0]]; });
    if (faltando.length) return mostrarErro('Preencha ' + faltando.map(function (c) { return c[1]; }).join(', ') + '.');
    if (d.password.length < 10 || !/[A-Za-z]/.test(d.password) || !/[0-9]/.test(d.password)) return mostrarErro('A senha precisa ter no mínimo 10 caracteres, com letras e números.');
    if (d.password !== d.password_confirmation) return mostrarErro('As senhas não conferem.');
    if (!form.elements.aceite_lgpd.checked || !form.elements.aceite_politica.checked) return mostrarErro('Marque as duas autorizações para criar o cadastro.');
    ['cargo', 'telefone'].forEach(function (k) { if (d[k] === '') delete d[k]; });
    d.aceite_lgpd = '1'; d.aceite_politica = '1'; d.vinculo = estado.vinculo; d.website = form.elements.website.value;
    var botao = form.querySelector('button[type="submit"]'); botao.disabled = true;
    tokenPronto(12000).then(function (pronto) {
      if (!pronto) { return Promise.reject(new Error('sem-token')); }
      return chamar('/cadastro', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(corpoComToken(d)) });
    }).then(function (r) {
      renovarToken(); botao.disabled = false;
      if (r.status === 201) { evento('Indice cadastro'); $('[data-bloco-contato]').hidden = true; $('[data-sucesso-cadastro]').hidden = false; $('[data-sucesso-cadastro]').scrollIntoView({ block: 'center', behavior: 'smooth' }); return; }
      if (r.status === 422 && r.corpo && r.corpo.erro === 'vinculo_invalido') return mostrarErro(r.corpo.mensagem || 'Sua resposta expirou. Responda de novo e se cadastre em seguida.');
      if (r.status === 429) return mostrarErro('Muitas tentativas em pouco tempo. Aguarde um minuto e tente de novo.');
      mostrarErro(mensagensDoServidor(r.corpo) || 'Não foi possível concluir o cadastro agora. Tente de novo em instantes.');
    }).catch(function (e) {
      renovarToken(); botao.disabled = false;
      mostrarErro(e && e.message === 'sem-token' ? 'A verificação de segurança ainda não terminou. Aguarde alguns segundos e tente de novo.' : 'Verifique a sua conexão e tente de novo.');
    });
  });

  /* ---------- acoes ---------- */
  raiz.addEventListener('click', function (ev) {
    var a = ev.target.closest('[data-acao]'); if (!a) return;
    var acao = a.getAttribute('data-acao');
    if (acao === 'iniciar') { evento('Indice inicio'); estado.i = 0; estado.respostas = {}; pergunta(); }
    else if (acao === 'voltar' && estado.i > 0) { estado.i -= 1; pergunta(); }
    else if (acao === 'tentar-de-novo') { (estado.retorno || carregar)(); }
    else if (acao === 'pular-cadastro') { $('[data-bloco-contato]').hidden = true; $('[data-explorar]').scrollIntoView({ behavior: 'smooth' }); }
  });

  /* ---------- Turnstile (so' com a chave configurada e so' quando ha edicao aberta) ---------- */
  var turnstileIniciado = false;
  function iniciarTurnstile() {
    if (!SITEKEY || turnstileIniciado) return;
    turnstileIniciado = true;
    window.__ixTurnstile = function () {
      var el = document.createElement('div'); el.style.cssText = 'position:fixed;bottom:8px;right:8px;z-index:50';
      document.body.appendChild(el);
      widgetId = window.turnstile.render(el, { sitekey: SITEKEY, callback: function (t) { estado.token = t; }, 'expired-callback': function () { estado.token = ''; } });
    };
    var s = document.createElement('script'); s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=__ixTurnstile'; s.async = true; document.head.appendChild(s);
  }

  carregar();
})();
