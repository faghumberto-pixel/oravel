/* Faixa de videos da home: carrossel por setas e janela de player (<dialog>).
 * Sem JavaScript, cada cartao e um link normal para o video no YouTube.
 * O iframe so e criado ao abrir o video (nada de terceiros carrega com a pagina). */
(function () {
  'use strict';
  var trilho = document.getElementById('vf-trilho');
  var dlg = document.getElementById('vf-dialog');
  if (!trilho || !dlg || typeof dlg.showModal !== 'function') return;
  var ant = document.querySelector('.vf-ant'), prox = document.querySelector('.vf-prox');
  var player = document.getElementById('vf-player'), titulo = document.getElementById('vf-dialog-titulo'), links = document.getElementById('vf-links');

  function evento(nome, props) { try { if (window.plausible) window.plausible(nome, { props: props }); } catch (e) {} }
  function el(tag, attrs, txt) { var e = document.createElement(tag); for (var k in attrs) e.setAttribute(k, attrs[k]); if (txt) e.textContent = txt; return e; }

  // Setas
  function atualizarSetas() {
    var max = trilho.scrollWidth - trilho.clientWidth - 2;
    ant.hidden = trilho.scrollLeft <= 2; prox.hidden = trilho.scrollLeft >= max || max <= 0;
  }
  function passo(dir) {
    var reduzido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    trilho.scrollBy({ left: dir * (trilho.clientWidth * 0.9), behavior: reduzido ? 'auto' : 'smooth' });
  }
  ant.addEventListener('click', function () { passo(-1); });
  prox.addEventListener('click', function () { passo(1); });
  trilho.addEventListener('scroll', function () { window.requestAnimationFrame(atualizarSetas); }, { passive: true });
  window.addEventListener('resize', atualizarSetas);
  atualizarSetas();

  // Player
  function fechar() { if (dlg.open) dlg.close(); }
  dlg.addEventListener('close', function () { player.textContent = ''; });
  dlg.querySelector('.vf-fechar').addEventListener('click', fechar);
  dlg.addEventListener('click', function (e) { if (e.target === dlg) fechar(); });

  trilho.addEventListener('click', function (e) {
    var a = e.target.closest('.vf-card');
    if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
    e.preventDefault();
    var d = a.dataset;
    titulo.textContent = d.titulo;
    player.textContent = '';
    var f = el('iframe', { src: 'https://www.youtube-nocookie.com/embed/' + encodeURIComponent(d.id) + '?autoplay=1&rel=0', title: 'Vídeo: ' + d.titulo, allow: 'autoplay; encrypted-media; picture-in-picture; fullscreen', allowfullscreen: '', referrerpolicy: 'strict-origin-when-cross-origin' });
    player.appendChild(f);
    links.textContent = '';
    if (d.artigoUrl) { var l1 = el('a', { class: 'btn', href: d.artigoUrl }, 'Ler o artigo'); l1.addEventListener('click', function () { evento('video_artigo', { video: d.titulo }); }); links.appendChild(l1); }
    if (d.ferrUrl) { var l2 = el('a', { href: d.ferrUrl }, 'Usar a ferramenta: ' + d.ferrTitulo); l2.addEventListener('click', function () { evento('video_ferramenta', { video: d.titulo }); }); links.appendChild(l2); }
    dlg.showModal();
    evento('video_play', { video: d.titulo });
  });
})();
