// Menu único do site (pedido do usuário 2026-08-21): antes existiam 2 cópias
// do mesmo menu -- inc/nav.php (PHP) e um bloco hardcoded dentro de
// index.html (HTML estático, não roda PHP) -- e ficaram desincronizadas (FAQ
// foi adicionado só numa delas). index.html não pode usar <?php include ?>,
// então a fonte única agora é inc/nav-fragment.html, buscada via fetch e
// injetada em todo <div id="onav-mount"></div> -- funciona igual em .php e
// .html. Reaplica os listeners do dropdown/burger depois de injetar, porque
// o HTML só existe no DOM depois do fetch resolver.
(function () {
  var mount = document.getElementById('onav-mount');
  if (!mount) return;

  fetch('/inc/nav-fragment.html')
    .then(function (r) { return r.text(); })
    .then(function (html) {
      mount.outerHTML = html;
      initNav();
    })
    .catch(function () {
      // Falha de rede/CDN: melhor não deixar o topo da página em branco.
      mount.outerHTML = '<nav class="onav"><div class="onav-wrap"><a href="/" class="onav-brand"><img class="onav-mark" src="/nova/assets/oravel-wordmark-only.png?v=1" alt="Oravel" /></a></div></nav>';
    });

  function initNav() {
    var dropdown = document.getElementById('onav-dropdown-modulos');
    var trigger = dropdown.querySelector('.onav-dropdown-trigger');
    var burger = document.getElementById('onav-burger');
    var navLinks = document.getElementById('onav-links');
    var planosLink = document.querySelector('[data-nav-planos]');

    // Na própria home, "Planos" deve rolar suave (#planos) em vez de
    // recarregar a página inteira (/#planos) -- ver pergunta ao usuário.
    if (planosLink && (location.pathname === '/' || location.pathname === '/index.html')) {
      planosLink.setAttribute('href', '#planos');
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = dropdown.classList.toggle('is-open');
      trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });

    burger.addEventListener('click', function () {
      var isOpen = navLinks.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (!isOpen) {
        dropdown.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();
