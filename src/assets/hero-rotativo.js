/*
 * Hero: artigos em destaque (capa + titulo abaixo + link para ler), em rotacao.
 * O servidor renderiza o primeiro artigo; este script gira pelos demais (JSON em
 * #hero-dados) a cada data-intervalo ms.
 * - pausa com mouse de verdade ou foco de teclado dentro do cartao, e com a aba
 *   em segundo plano (em toque, a pausa por "hover" nao existe: ficaria presa)
 * - bolinhas para ir direto a um artigo
 * - com "reduzir movimento" no sistema, nao gira sozinho (so pelas bolinhas)
 * Sem JavaScript, fica o primeiro artigo renderizado no servidor.
 */
(function () {
  var raiz = document.getElementById('hero-rotativo');
  var dadosEl = document.getElementById('hero-dados');
  var link = document.getElementById('hero-artigo');
  var pontos = document.getElementById('hero-pontos');
  if (!raiz || !dadosEl || !link || !pontos) return;

  var dados;
  try { dados = JSON.parse(dadosEl.textContent); } catch (e) { return; }
  if (!Array.isArray(dados) || dados.length < 2) return;

  var intervalo = parseInt(raiz.getAttribute('data-intervalo'), 10) || 6000;
  var reduzido = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var indice = 0;
  var pausadoPorHover = false;
  var trocando = false;
  var timer = null;

  var imagem = link.querySelector('img');
  var tema = link.querySelector('.hero-artigo-tema');
  var titulo = link.querySelector('h3');
  var botoes = [];

  function marcarPonto() {
    botoes.forEach(function (b, i) {
      if (i === indice) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current');
    });
  }

  function montar() {
    var d = dados[indice];
    link.href = d.url;
    imagem.src = d.img;
    tema.textContent = d.tema;
    titulo.textContent = d.titulo;
    marcarPonto();
    // pre-carrega a proxima capa para a troca nao piscar
    var prox = dados[(indice + 1) % dados.length];
    var pre = new Image(); pre.src = prox.img;
  }

  function irPara(novo) {
    if (trocando || novo === indice) return;
    trocando = true;
    var animar = !reduzido;
    if (animar) link.classList.add('rot-saindo');
    setTimeout(function () {
      indice = (novo + dados.length) % dados.length;
      montar();
      link.classList.remove('rot-saindo');
      trocando = false;
    }, animar ? 250 : 0);
  }

  function ativo() { return !reduzido && !pausadoPorHover && !document.hidden; }

  dados.forEach(function (d, i) {
    var b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('aria-label', 'Mostrar o artigo ' + (i + 1) + ' de ' + dados.length);
    b.addEventListener('click', function () { irPara(i); });
    pontos.appendChild(b);
    botoes.push(b);
  });
  pontos.hidden = false;
  marcarPonto();

  raiz.addEventListener('pointerenter', function (e) { if (e.pointerType === 'mouse') pausadoPorHover = true; });
  raiz.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse') pausadoPorHover = false; });
  raiz.addEventListener('focusin', function (e) {
    var porTeclado = false;
    try { porTeclado = e.target.matches(':focus-visible'); } catch (err) { porTeclado = true; }
    if (porTeclado) pausadoPorHover = true;
  });
  raiz.addEventListener('focusout', function () { pausadoPorHover = false; });

  timer = setInterval(function () { if (ativo()) irPara(indice + 1); }, intervalo);
})();
