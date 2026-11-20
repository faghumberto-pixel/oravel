/* Visitas ao oravel.org para a Central da Oravel (Central > Site Institucional > Visitas do Site, coluna Site).
 * Mesmo coletor do oravel.com.br (/api/site-track): pv = abriu a pagina | ping = tempo ativo (15 s) | leave = saiu |
 * click = link externo, download ou elemento com data-track.
 * SO FUNCIONA COM CONSENTIMENTO: usa o aviso do rel.js (OravelRel). Sem aceite, "Do Not Track" ou sem localStorage,
 * nada e criado nem enviado. O identificador do navegador e' o mesmo do rel.js, para a jornada ligar a pessoa
 * quando ela se identificar. Abrir qualquer pagina com ?notrack marca o aparelho para nao contar (?track desfaz):
 * a equipe navega sem entrar nas proprias estatisticas. */
(function () {
  'use strict';
  if (window.__oravelVisitas) return;
  window.__oravelVisitas = true;

  var meta = document.querySelector('meta[name="oravel-visitas"]');
  var ENDPOINT = meta && meta.content;
  if (!ENDPOINT) return;

  var SESSION_TTL = 30 * 60 * 1000; // nova visita apos 30 min parado
  var PING_EVERY = 15000;

  function ler(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function gravar(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function apagar(k) { try { localStorage.removeItem(k); } catch (e) {} }

  if (location.search.indexOf('notrack') !== -1) gravar('oravel:notrack', '1');
  else if (/[?&]track(=|&|$)/.test(location.search)) apagar('oravel:notrack');
  if (ler('oravel:notrack') === '1') return;

  function visitante() { return window.OravelRel ? window.OravelRel.token() : ''; }
  function novo() {
    try { if (window.crypto && crypto.randomUUID) return crypto.randomUUID(); } catch (e) {}
    return 'v' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
  }

  var iniciado = false, page = novo(), sess, activeMs = 0, lastTick = 0, maxScroll = 0, left = false, timer;

  function sessao() {
    var now = Date.now(), s = null;
    try { s = JSON.parse(ler('oravel:vs') || 'null'); } catch (e) { s = null; }
    if (!s || !s.id || now - s.ts > SESSION_TTL) s = { id: novo(), ts: now };
    s.ts = now; gravar('oravel:vs', JSON.stringify(s));
    return s;
  }

  function enviar(payload) {
    var v = visitante();
    if (!v) return; // sem consentimento (ou revogado): nao envia
    payload.v = v; payload.s = sess.id; payload.p = page;
    var body = JSON.stringify(payload);
    try { if (navigator.sendBeacon && navigator.sendBeacon(ENDPOINT, body)) return; } catch (e) {}
    try { fetch(ENDPOINT, { method: 'POST', body: body, keepalive: true, mode: 'no-cors' }); } catch (e) {}
  }

  function tick() { if (lastTick) { activeMs += Date.now() - lastTick; lastTick = Date.now(); } }
  function scrollPct() {
    var el = document.documentElement;
    var total = Math.max(el.scrollHeight, document.body ? document.body.scrollHeight : 0) - window.innerHeight;
    var pct = total <= 0 ? 100 : Math.round((window.scrollY / total) * 100);
    if (pct > maxScroll) maxScroll = Math.min(100, pct);
  }
  function relatar(tipo) { tick(); scrollPct(); enviar({ t: tipo, ms: activeMs, sc: maxScroll }); }
  function utm(n) { var m = new RegExp('[?&]' + n + '=([^&]+)').exec(location.search); return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')).slice(0, 200) : null; }

  function rotulo(el) {
    var label = el.getAttribute('data-track');
    var href = el.getAttribute('href') || '';
    if (label || !href) return label;
    if (/wa\.me|whatsapp/i.test(href)) return 'WhatsApp';
    if (/^mailto:/i.test(href)) return 'E-mail';
    if (/^tel:/i.test(href)) return 'Telefone';
    if (/^\/?(downloads|assets\/(materiais|gazeta))\//i.test(el.pathname || href)) return 'Download: ' + (el.pathname || href).slice(0, 100);
    if (/^https?:\/\//i.test(href) && el.hostname && el.hostname !== location.hostname) return 'Link externo: ' + href.replace(/^https?:\/\//, '').slice(0, 100);
    return null;
  }

  function iniciar() {
    if (iniciado || !visitante()) return;
    iniciado = true;
    sess = sessao();
    lastTick = document.visibilityState === 'visible' ? Date.now() : 0;

    enviar({
      t: 'pv', path: location.pathname, title: document.title, ref: document.referrer || null,
      utm_source: utm('utm_source'), utm_medium: utm('utm_medium'), utm_campaign: utm('utm_campaign'),
      utm_term: utm('utm_term'), utm_content: utm('utm_content')
    });

    timer = setInterval(function () {
      if (document.visibilityState === 'visible' && !left) { relatar('ping'); sess.ts = Date.now(); gravar('oravel:vs', JSON.stringify(sess)); }
    }, PING_EVERY);

    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'hidden') { tick(); lastTick = 0; relatar('leave'); } else { lastTick = Date.now(); }
    });
    window.addEventListener('pagehide', function () { if (left) return; left = true; clearInterval(timer); relatar('leave'); });
    window.addEventListener('scroll', scrollPct, { passive: true });
    document.addEventListener('click', function (ev) {
      var el = ev.target && ev.target.closest ? ev.target.closest('a,button,[data-track]') : null;
      var label = el ? rotulo(el) : null;
      if (label) enviar({ t: 'click', label: label, path: location.pathname });
    }, true);
  }

  // Ja consentiu antes: comeca agora. Aceita agora no aviso: comeca na pagina em que esta.
  iniciar();
  document.addEventListener('oravel:consent', function (e) { if (e.detail === 'sim') iniciar(); });
})();
