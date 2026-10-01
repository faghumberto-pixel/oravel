/*!
 * Oravel -- analytics próprio do site institucional (oravel.com.br).
 * Sem cookies: usa localStorage para identificar o visitante e a sessão.
 * Envia pequenos eventos para app.oravel.com.br/api/site-track:
 *   pv = abriu a página | ping = tempo ativo (a cada 15 s) | leave = saiu | click = clicou num CTA/link externo
 * Não coleta nada se o navegador pede "Do Not Track" ou se o aparelho foi marcado
 * (abrir qualquer página do site com ?notrack liga a marca; ?track a remove) --
 * assim a equipe pode navegar sem contar nas próprias estatísticas.
 */
(function () {
  'use strict';

  // Trava contra o script rodar duas vezes na mesma página (tag duplicada no site): contaria 2 visualizações.
  if (window.__oravelTracking) { return; }
  window.__oravelTracking = true;

  var ENDPOINT = 'https://app.oravel.com.br/api/site-track';
  var SESSION_TTL = 30 * 60 * 1000; // nova visita após 30 min parado
  var PING_EVERY = 15000;

  try {
    if (location.search.indexOf('notrack') !== -1) { localStorage.setItem('ov_notrack', '1'); }
    if (location.search.indexOf('track') !== -1 && location.search.indexOf('notrack') === -1) { localStorage.removeItem('ov_notrack'); }
    if (localStorage.getItem('ov_notrack') === '1') { return; }
  } catch (e) { /* sem localStorage: segue sem persistência */ }

  if (navigator.doNotTrack === '1' || window.doNotTrack === '1') { return; }

  function uuid() {
    if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
      var r = Math.random() * 16 | 0;
      return (c === 'x' ? r : (r & 3 | 8)).toString(16);
    });
  }

  function store(key, value) {
    try { if (value === undefined) { return localStorage.getItem(key); } localStorage.setItem(key, value); } catch (e) { return null; }
  }

  // Visitante (persistente) e sessão (renovada por atividade).
  var visitor = store('ov_v') || uuid(); store('ov_v', visitor);
  var now = Date.now();
  var sess = null;
  try { sess = JSON.parse(store('ov_s') || 'null'); } catch (e) { sess = null; }
  if (!sess || !sess.id || now - sess.ts > SESSION_TTL) { sess = { id: uuid(), ts: now }; }
  sess.ts = now; store('ov_s', JSON.stringify(sess));

  var page = uuid();
  var activeMs = 0;
  var lastTick = document.visibilityState === 'visible' ? Date.now() : 0;
  var maxScroll = 0;
  var left = false;

  function send(payload) {
    payload.v = visitor; payload.s = sess.id; payload.p = page;
    var body = JSON.stringify(payload);
    try {
      if (navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, body)) { return; }
    } catch (e) { /* cai no fetch */ }
    try { fetch(ENDPOINT, { method: 'POST', body: body, keepalive: true, mode: 'no-cors' }); } catch (e) { /* ignora */ }
  }

  function tick() {
    if (lastTick) { activeMs += Date.now() - lastTick; lastTick = Date.now(); }
  }

  function scrollPct() {
    var el = document.documentElement;
    var total = Math.max(el.scrollHeight, document.body ? document.body.scrollHeight : 0) - window.innerHeight;
    var pct = total <= 0 ? 100 : Math.round((window.scrollY / total) * 100);
    if (pct > maxScroll) { maxScroll = Math.min(100, pct); }
  }

  function utm(name) {
    var m = new RegExp('[?&]' + name + '=([^&#]*)').exec(location.search);
    return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : null;
  }

  function report(type) {
    tick(); scrollPct();
    send({ t: type, ms: activeMs, sc: maxScroll });
  }

  send({
    t: 'pv',
    path: location.pathname,
    title: document.title,
    ref: document.referrer || null,
    utm_source: utm('utm_source'), utm_medium: utm('utm_medium'), utm_campaign: utm('utm_campaign'),
    utm_term: utm('utm_term'), utm_content: utm('utm_content')
  });

  var timer = setInterval(function () {
    if (document.visibilityState === 'visible' && !left) { report('ping'); sess.ts = Date.now(); store('ov_s', JSON.stringify(sess)); }
  }, PING_EVERY);

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
      tick(); lastTick = 0; report('leave');
    } else {
      lastTick = Date.now();
    }
  });

  window.addEventListener('pagehide', function () {
    if (left) { return; }
    left = true; clearInterval(timer); report('leave');
  });

  window.addEventListener('scroll', function () { scrollPct(); }, { passive: true });

  // Cliques que importam: WhatsApp, e-mail, telefone, links externos e qualquer elemento com data-track.
  document.addEventListener('click', function (ev) {
    var el = ev.target && ev.target.closest ? ev.target.closest('a,button,[data-track]') : null;
    if (!el) { return; }
    var label = el.getAttribute('data-track');
    var href = el.getAttribute('href') || '';
    if (!label && href) {
      if (/wa\.me|whatsapp/i.test(href)) { label = 'WhatsApp'; }
      else if (/^mailto:/i.test(href)) { label = 'E-mail'; }
      else if (/^tel:/i.test(href)) { label = 'Telefone'; }
      else if (/^https?:\/\//i.test(href) && href.indexOf(location.host) === -1) { label = 'Link externo: ' + href.replace(/^https?:\/\//, '').slice(0, 100); }
      else if (/assinar|plano=|\/contato|solicitar|proposta|agendar|demonstra/i.test(href)) { label = 'CTA: ' + href.replace(/^https?:\/\/[^/]+/, '').slice(0, 100); }
    }
    if (label) { send({ t: 'click', label: label, path: location.pathname }); }
  }, true);
})();
