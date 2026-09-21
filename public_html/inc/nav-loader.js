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
    var dropdowns = Array.prototype.slice.call(document.querySelectorAll('.onav-dropdown'));
    var burger = document.getElementById('onav-burger');
    var navLinks = document.getElementById('onav-links');
    var planosLink = document.querySelector('[data-nav-planos]');

    function closeDropdown(dd) {
      dd.classList.remove('is-open');
      var t = dd.querySelector('.onav-dropdown-trigger');
      if (t) t.setAttribute('aria-expanded', 'false');
    }
    function closeAll() { dropdowns.forEach(closeDropdown); }

    // Na própria home, "Planos" deve rolar suave (#planos) em vez de
    // recarregar a página inteira (/#planos) -- ver pergunta ao usuário.
    if (planosLink && (location.pathname === '/' || location.pathname === '/index.html')) {
      planosLink.setAttribute('href', '#planos');
    }

    // Um dropdown aberto por vez (Módulos, Segmentos, ...).
    dropdowns.forEach(function (dd) {
      var trigger = dd.querySelector('.onav-dropdown-trigger');
      trigger.addEventListener('click', function (e) {
        e.stopPropagation();
        var willOpen = !dd.classList.contains('is-open');
        closeAll();
        if (willOpen) {
          dd.classList.add('is-open');
          trigger.setAttribute('aria-expanded', 'true');
        }
      });
    });

    document.addEventListener('click', function (e) {
      dropdowns.forEach(function (dd) { if (!dd.contains(e.target)) closeDropdown(dd); });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeAll();
    });

    burger.addEventListener('click', function () {
      var isOpen = navLinks.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      if (!isOpen) closeAll();
    });
  }
})();
