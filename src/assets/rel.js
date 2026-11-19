/* Sinais anonimos do oravel.org para a Central da Oravel (modulo Relacionamento).
 * - OravelRel.token(): identificador anonimo do navegador (localStorage), usado pelas ferramentas;
 * - clique em download (/downloads/*, /assets/materiais/*) vira um evento "download".
 * Nao envia nome, e-mail nem telefone. Respeita "Do Not Track". Sem URL configurada (meta), nada e enviado. */
(function () {
  'use strict';
  var meta = document.querySelector('meta[name="oravel-rel"]');
  var url = meta && meta.content;
  var dnt = navigator.doNotTrack === '1' || window.doNotTrack === '1';
  var mem = null;

  function novo() {
    try { if (window.crypto && crypto.randomUUID) return crypto.randomUUID(); } catch (e) {}
    return 'v' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
  }

  function token() {
    if (dnt || !url) return '';
    try {
      var t = localStorage.getItem('oravel:vt');
      if (!t) { t = novo(); localStorage.setItem('oravel:vt', t); }
      return t;
    } catch (e) { return mem || (mem = novo()); }
  }

  function enviar(dados) {
    var t = token();
    if (!t || !navigator.sendBeacon) return;
    try { navigator.sendBeacon(url, JSON.stringify(Object.assign({ visitor: t }, dados))); } catch (e) {}
  }

  window.OravelRel = { token: token, enviar: enviar };

  document.addEventListener('click', function (ev) {
    var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a || a.hostname !== location.hostname) return;
    if (/^\/(downloads|assets\/materiais)\/[^/]+$/.test(a.pathname)) enviar({ evento: 'download', arquivo: a.pathname });
  }, true);
})();
