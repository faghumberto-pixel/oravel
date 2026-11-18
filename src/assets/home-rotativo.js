/*
 * Home: "Você não pode deixar de ler".
 * O servidor renderiza a primeira janela (1 destaque + 3 artigos). Este script
 * gira por uma lista maior de artigos publicados (JSON em #rotativo-dados).
 * - gira sozinho a cada data-intervalo ms
 * - pausa com o mouse ou o foco de teclado dentro do bloco, e com a aba em segundo plano
 * - botoes Anterior / Pausar / Proximo
 * - com "reduzir movimento" no sistema, nao gira sozinho (so pelos botoes)
 * Sem JavaScript, fica a primeira janela renderizada no servidor.
 */
(function () {
  var raiz = document.getElementById('rotativo');
  var dadosEl = document.getElementById('rotativo-dados');
  var destaque = document.getElementById('rot-destaque');
  var lista = document.getElementById('rot-lista');
  if (!raiz || !dadosEl || !destaque || !lista) return;

  var dados;
  try { dados = JSON.parse(dadosEl.textContent); } catch (e) { return; }
  var JANELA = 4; // 1 destaque + 3 itens
  if (!Array.isArray(dados) || dados.length <= JANELA) return;

  var intervalo = parseInt(raiz.getAttribute('data-intervalo'), 10) || 8000;
  var reduzido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var indice = 0;
  var timer = null;
  var pausadoPeloUsuario = reduzido; // com movimento reduzido, comeca pausado
  var pausadoPorHover = false;
  var trocando = false;

  var controles = document.getElementById('rot-controles');
  var btnAnterior = document.getElementById('rot-anterior');
  var btnPausa = document.getElementById('rot-pausa');
  var btnProximo = document.getElementById('rot-proximo');
  var alvos = [destaque, lista];

  function elemento(tag, classe, texto) {
    var e = document.createElement(tag);
    if (classe) e.className = classe;
    if (texto != null) e.textContent = texto;
    return e;
  }

  function relogio() {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    var c = document.createElementNS(ns, 'circle');
    c.setAttribute('cx', '12'); c.setAttribute('cy', '12'); c.setAttribute('r', '10');
    var p = document.createElementNS(ns, 'path');
    p.setAttribute('d', 'M12 6v6l4 2');
    svg.appendChild(c); svg.appendChild(p);
    return svg;
  }

  function item(d) {
    var a = elemento('a', 'article-item');
    a.setAttribute('role', 'listitem');
    a.href = d.url;
    var img = elemento('img', 'article-thumb');
    img.src = d.img; img.alt = ''; img.width = 135; img.height = 90; img.loading = 'lazy';
    var conteudo = elemento('div', 'article-content');
    conteudo.appendChild(elemento('h3', null, d.titulo));
    conteudo.appendChild(elemento('div', 'article-meta', d.tema + (d.leitura ? ' · ' + d.leitura + ' de leitura' : '')));
    a.appendChild(img); a.appendChild(conteudo);
    return a;
  }

  function montar() {
    var d0 = dados[indice % dados.length];
    // destaque
    var bg = destaque.querySelector('.featured-card-bg img');
    if (bg) bg.src = d0.img;
    var tag = destaque.querySelector('.featured-card-tag');
    if (tag) tag.textContent = String(d0.tema).toUpperCase();
    var link = destaque.querySelector('h2 a');
    if (link) { link.textContent = d0.titulo; link.href = d0.url; }
    var meta = destaque.querySelector('.featured-card-meta');
    if (d0.leitura) {
      if (!meta) {
        meta = elemento('div', 'featured-card-meta');
        meta.appendChild(relogio());
        meta.appendChild(elemento('span'));
        destaque.querySelector('.featured-card-content').appendChild(meta);
      }
      meta.querySelector('span').textContent = d0.leitura + ' de leitura';
    } else if (meta) {
      meta.parentNode.removeChild(meta);
    }
    // lista
    while (lista.firstChild) lista.removeChild(lista.firstChild);
    for (var i = 1; i < JANELA; i++) lista.appendChild(item(dados[(indice + i) % dados.length]));
  }

  function ir(delta) {
    if (trocando) return;
    trocando = true;
    var animar = !reduzido;
    if (animar) alvos.forEach(function (e) { e.classList.add('rot-saindo'); });
    setTimeout(function () {
      indice = (indice + delta + dados.length) % dados.length;
      montar();
      alvos.forEach(function (e) { e.classList.remove('rot-saindo'); });
      trocando = false;
    }, animar ? 250 : 0);
  }

  function ativo() { return !pausadoPeloUsuario && !pausadoPorHover && !document.hidden; }

  function agendar() {
    if (timer) clearInterval(timer);
    timer = setInterval(function () { if (ativo()) ir(1); }, intervalo);
  }

  function atualizarBotaoPausa() {
    btnPausa.textContent = pausadoPeloUsuario ? 'Reproduzir' : 'Pausar';
    btnPausa.setAttribute('aria-pressed', pausadoPeloUsuario ? 'true' : 'false');
  }

  // Pausa so com mouse de verdade e com foco de teclado. Em tela de toque, o mouseenter
  // emulado nao teria mouseleave e deixaria a rotacao pausada para sempre.
  raiz.addEventListener('pointerenter', function (e) { if (e.pointerType === 'mouse') pausadoPorHover = true; });
  raiz.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse') pausadoPorHover = false; });
  raiz.addEventListener('focusin', function (e) {
    var porTeclado = false;
    try { porTeclado = e.target.matches(':focus-visible'); } catch (err) { porTeclado = true; }
    if (porTeclado) pausadoPorHover = true;
  });
  raiz.addEventListener('focusout', function () { pausadoPorHover = false; });

  btnAnterior.addEventListener('click', function () { ir(-1); });
  btnProximo.addEventListener('click', function () { ir(1); });
  btnPausa.addEventListener('click', function () { pausadoPeloUsuario = !pausadoPeloUsuario; atualizarBotaoPausa(); });

  controles.hidden = false;
  atualizarBotaoPausa();
  agendar();
})();
