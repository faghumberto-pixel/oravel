/*!
 * Oravel -- analytics próprio do site institucional (oravel.com.br).
 * Sem cookies: usa localStorage para identificar o visitante e a sessão.
 * Envia pequenos eventos para app.oravel.com.br/api/site-track:
 *   pv = abriu a página | ping = tempo ativo (a cada 15 s) | leave = saiu | click = clicou num CTA/link externo
 * Campanhas (02/10/2026): guarda gclid/utm (localStorage, 90 dias) e os põe em campos ocultos dos formulários,
 * para o lead chegar ao CRM com a origem do anúncio; mostra o aviso de cookies; carrega o
 * Google Analytics (GA4) por padrão (salvo "Recusar") e dispara generate_lead / whatsapp_click / contact_click.
 * Páginas com formulário por fetch chamam window.oravelLead('nome') quando o envio dá certo; páginas com redirecionamento
 * (?enviado=1) ou com .form-success (contato.php) são detectadas aqui mesmo.
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
    if (label) {
      send({ t: 'click', label: label, path: location.pathname });
      if (label === 'WhatsApp') { ga('whatsapp_click', { link_url: href.slice(0, 200), page_path: location.pathname }); }
      else if (label === 'Telefone' || label === 'E-mail') { ga('contact_click', { method: label, page_path: location.pathname }); }
    }
  }, true);

  // Widget flutuante de WhatsApp das páginas do site: não é <a>, abre o chat com window.open('https://wa.me/...') ao
  // enviar a mensagem. O listener de clique acima não o enxerga, então contamos a abertura aqui (1 por envio).
  var nativeOpen = window.open;
  window.open = function (url) {
    try {
      if (typeof url === 'string' && /wa\.me|api\.whatsapp\.com|whatsapp\.com\/send/i.test(url)) {
        send({ t: 'click', label: 'WhatsApp', path: location.pathname });
        ga('whatsapp_click', { link_url: url.slice(0, 200), page_path: location.pathname });
      }
    } catch (e) { /* medir nunca pode impedir o chat de abrir */ }
    return nativeOpen.apply(this, arguments);
  };

  // ---- Campanhas: gclid/utm, aviso de cookies, Google (GA4) após consentimento, lead enviado ----
  var GA_ID = 'G-L79HHRE3ZC';
  var ATTR_TTL = 90 * 24 * 3600 * 1000;
  var ATTR_KEYS = ['gclid', 'gbraid', 'wbraid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

  function param(name) {
    try { return utm(name); } catch (e) { return null; }
  }

  function readAttr() {
    try {
      var a = JSON.parse(store('ov_attr') || 'null');
      if (a && a.ts && Date.now() - a.ts < ATTR_TTL) { return a; }
    } catch (e) { /* ignora */ }
    return null;
  }

  // Parâmetros de campanha da URL. Só sobrescreve o que está guardado quando a URL traz algum (o último clique vale).
  (function captureAttr() {
    var found = {}; var any = false;
    ATTR_KEYS.forEach(function (k) { var v = param(k); if (v) { found[k] = v.slice(0, 200); any = true; } });
    if (any) { found.landing = location.pathname; found.ts = Date.now(); store('ov_attr', JSON.stringify(found)); }
  })();

  // Põe a origem do anúncio em todo formulário POST da página (o servidor repassa ao CRM).
  function fillForms() {
    var a = readAttr() || {};
    var fields = { landing_url: a.landing ? location.origin + a.landing : location.href };
    ATTR_KEYS.forEach(function (k) { if (a[k]) { fields[k] = a[k]; } });
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) {
      var f = forms[i];
      if ((f.getAttribute('method') || 'get').toLowerCase() !== 'post') { continue; }
      Object.keys(fields).forEach(function (k) {
        var el = f.querySelector('input[name="' + k + '"]');
        if (!el) { el = document.createElement('input'); el.type = 'hidden'; el.name = k; f.appendChild(el); }
        el.value = fields[k];
      });
    }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', fillForms); } else { fillForms(); }
  document.addEventListener('submit', fillForms, true);

  // Google Analytics: só depois do aceite.
  function loadGa() {
    if (window.__ovGa) { return; }
    window.__ovGa = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
    window.gtag('consent', 'default', { ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted', analytics_storage: 'granted' });
    window.gtag('js', new Date());
    window.gtag('config', GA_ID);
    var s = document.createElement('script');
    s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA_ID;
    document.head.appendChild(s);
  }

  function ga(name, params) {
    if (store('ov_consent') !== 'denied' && window.gtag) { try { window.gtag('event', name, params || {}); } catch (e) { /* ignora */ } }
  }

  function showBanner() {
    if (document.getElementById('ov-cookie-banner')) { return; }
    var box = document.createElement('div');
    box.id = 'ov-cookie-banner';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', 'Aviso de cookies');
    box.style.cssText = 'position:fixed;left:16px;bottom:16px;right:16px;max-width:460px;z-index:2147483000;background:#0b1f3a;color:#fff;border-radius:12px;padding:16px 18px;box-shadow:0 10px 30px rgba(0,0,0,.35);font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif';
    box.innerHTML = '<div style="margin-bottom:12px">Usamos cookies do Google (Analytics e Ads) para medir o site e nossos anúncios. Você pode recusar a qualquer momento. <a href="/politica-de-privacidade/" style="color:#9cc2ff;text-decoration:underline">Saiba mais</a>.</div>'
      + '<div style="display:flex;gap:10px;flex-wrap:wrap">'
      + '<button type="button" data-ov="granted" style="background:#2563eb;color:#fff;border:0;border-radius:8px;padding:9px 18px;font-weight:600;cursor:pointer">Entendi</button>'
      + '<button type="button" data-ov="denied" style="background:transparent;color:#fff;border:1px solid rgba(255,255,255,.45);border-radius:8px;padding:9px 18px;font-weight:600;cursor:pointer">Recusar</button>'
      + '</div>';
    box.addEventListener('click', function (e) {
      var v = e.target && e.target.getAttribute ? e.target.getAttribute('data-ov') : null;
      if (!v) { return; }
      store('ov_consent', v);
      box.parentNode.removeChild(box);
      if (v === 'granted') { loadGa(); }
    });
    (document.body || document.documentElement).appendChild(box);
  }

  // Permite ao visitante rever a escolha (ex.: link "Preferências de cookies" no rodapé: onclick="oravelCookies()").
  window.oravelCookies = function () { try { localStorage.removeItem('ov_consent'); } catch (e) { /* ignora */ } showBanner(); };

  // GA4 carrega por padrão; só deixa de carregar se o visitante recusou. O aviso aparece até ele escolher.
  var choice = store('ov_consent');
  if (choice !== 'denied') { loadGa(); }
  if (!choice) {
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', showBanner); } else { showBanner(); }
  }

  // Lead enviado com sucesso (uma vez por página/sessão, para F5 não contar de novo).
  function leadOnce(label) {
    var key = 'ov_lead_' + label + '_' + location.pathname;
    try { if (sessionStorage.getItem(key)) { return; } sessionStorage.setItem(key, '1'); } catch (e) { /* sem sessionStorage: segue */ }
    send({ t: 'click', label: 'Lead enviado: ' + label, path: location.pathname });
    ga('generate_lead', { lead_source: label, page_path: location.pathname });
  }
  window.oravelLead = leadOnce;

  function detectLead() {
    if (/[?&]enviado=1(&|#|$)/.test(location.search)) { leadOnce('formulario'); }
    else if (document.querySelector('.form-success')) { leadOnce('contato'); }
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', detectLead); } else { detectLead(); }
})();
