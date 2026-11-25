/* Sinais anonimos do oravel.org para a Central da Oravel (modulo Relacionamento), SOMENTE com consentimento.
 * - aviso discreto "Aceitar / Recusar" na primeira visita (cobre tambem visitas.js, o rastreio de paginas); sem escolha ou com "Recusar", nada e criado nem enviado;
 * - OravelRel.token(): identificador anonimo do navegador (localStorage), usado pelas ferramentas;
 * - clique em download (/downloads/*, /assets/materiais/*) vira um evento "download".
 * Nao envia nome, e-mail nem telefone. Respeita "Do Not Track". Sem URL configurada (meta), nao faz nada.
 * A escolha fica em localStorage ('oravel:consent' = sim|nao); sem localStorage nao ha como lembrar, entao nao rastreia. */
(function () {
  'use strict';
  var meta = document.querySelector('meta[name="oravel-rel"]');
  var url = meta && meta.content;
  var dnt = navigator.doNotTrack === '1' || window.doNotTrack === '1';
  // Chave v2: o aceite passou a cobrir tambem as visitas as paginas (IP, cidade/estado, origem, tempo); quem aceitou so' as ferramentas e' perguntado de novo.
  var CONSENT = 'oravel:consent2', TOKEN = 'oravel:vt';

  function ler(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function gravar(k, v) { try { localStorage.setItem(k, v); return true; } catch (e) { return false; } }
  function apagar(k) { try { localStorage.removeItem(k); } catch (e) {} }

  function ativo() { return !!url && !dnt && ler('oravel:notrack') !== '1' && ler(CONSENT) === 'sim'; }

  function novo() {
    try { if (window.crypto && crypto.randomUUID) return crypto.randomUUID(); } catch (e) {}
    return 'v' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
  }

  function token() {
    if (!ativo()) return '';
    var t = ler(TOKEN);
    if (!t) { t = novo(); if (!gravar(TOKEN, t)) return ''; }
    return t;
  }

  function enviar(dados) {
    var t = token();
    if (!t || !navigator.sendBeacon) return;
    try { navigator.sendBeacon(url, JSON.stringify(Object.assign({ visitor: t }, dados))); } catch (e) {}
  }

  function fechar() { var b = document.getElementById('oravel-consent'); if (b) b.remove(); }

  function escolher(valor) {
    if (!gravar(CONSENT, valor)) return fechar();
    if (valor !== 'sim') apagar(TOKEN);
    fechar();
    try { document.dispatchEvent(new CustomEvent('oravel:consent', { detail: valor })); } catch (e) {}
  }

  function aviso() {
    if (document.getElementById('oravel-consent')) return;
    var b = document.createElement('div');
    b.id = 'oravel-consent';
    b.setAttribute('role', 'region');
    b.setAttribute('aria-label', 'Aviso de privacidade');
    b.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:9999;background:#12302d;color:#fff;padding:14px 16px;box-shadow:0 -4px 16px rgba(0,0,0,.25);font:15px/1.45 Inter,system-ui,sans-serif';
    var wrap = document.createElement('div');
    wrap.style.cssText = 'max-width:960px;margin:0 auto;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between';
    var p = document.createElement('p');
    p.style.cssText = 'margin:0;flex:1 1 320px';
    p.appendChild(document.createTextNode('Para melhorar o conteúdo, podemos guardar no seu navegador um identificador anônimo e registrar as páginas que você visita, de onde veio, o aparelho, o tempo de leitura e sua localização aproximada (pelo IP), além das ferramentas e materiais que você usa. Se você se identificar depois, podemos ligar esse histórico ao seu e-mail. '));
    var a = document.createElement('a');
    a.href = '/privacidade/#uso-de-ferramentas-e-materiais'; a.textContent = 'Saiba mais'; a.style.cssText = 'color:#fff;text-decoration:underline';
    p.appendChild(a);
    var box = document.createElement('div');
    box.style.cssText = 'display:flex;gap:8px;flex:0 0 auto';
    function botao(txt, valor, destaque) {
      var x = document.createElement('button');
      x.type = 'button'; x.textContent = txt;
      x.style.cssText = 'cursor:pointer;border-radius:8px;padding:10px 18px;font:600 15px Inter,system-ui,sans-serif;border:2px solid #fff;' + (destaque ? 'background:#fff;color:#12302d' : 'background:transparent;color:#fff');
      x.onclick = function () { escolher(valor); };
      return x;
    }
    box.appendChild(botao('Recusar', 'nao', false));
    box.appendChild(botao('Aceitar', 'sim', true));
    wrap.appendChild(p); wrap.appendChild(box); b.appendChild(wrap);
    document.body.appendChild(b);
  }

  function iniciar() {
    // Pergunta so' se faz sentido: ha destino, sem Do Not Track, localStorage funciona e ainda nao houve escolha.
    if (url && !dnt && ler(CONSENT) === null && gravar('oravel:teste', '1')) { apagar('oravel:teste'); aviso(); }
  }

  window.OravelRel = {
    token: token,
    enviar: enviar,
    consentimento: function () { return ler(CONSENT); },
    // Link "Alterar minha escolha" da politica de privacidade.
    reabrir: function () { apagar(CONSENT); apagar(TOKEN); aviso(); }
  };

  document.addEventListener('click', function (ev) {
    var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
    if (!a || a.hostname !== location.hostname) return;
    if (/^\/(downloads|assets\/materiais)\/[^/]+$/.test(a.pathname)) enviar({ evento: 'download', arquivo: a.pathname });
  }, true);

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
