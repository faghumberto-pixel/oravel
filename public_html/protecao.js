/* Dificulta a cópia casual do conteúdo (selecionar, copiar, menu do botão direito, arrastar imagens).
   Não impede captura de tela nem "Ver código-fonte"; só aumenta o atrito. Campos de formulário continuam livres. */
(function () {
  var st = document.createElement('style');
  st.textContent = 'body{-webkit-user-select:none;-ms-user-select:none;user-select:none;-webkit-touch-callout:none}input,textarea,select,[contenteditable="true"]{-webkit-user-select:text;user-select:text}img{-webkit-user-drag:none}';
  document.head.appendChild(st);
  function campo(t) { return t && (['INPUT', 'TEXTAREA', 'SELECT'].indexOf(t.tagName) > -1 || t.isContentEditable); }
  ['copy', 'cut', 'contextmenu'].forEach(function (ev) {
    document.addEventListener(ev, function (e) { if (!campo(e.target)) e.preventDefault(); });
  });
  document.addEventListener('selectstart', function (e) { if (!campo(e.target)) e.preventDefault(); });
  document.addEventListener('dragstart', function (e) { if (e.target && e.target.tagName === 'IMG') e.preventDefault(); });
  document.addEventListener('keydown', function (e) {
    if (campo(e.target)) return;
    if ((e.ctrlKey || e.metaKey) && ['c', 'x', 's', 'u', 'a', 'p'].indexOf((e.key || '').toLowerCase()) > -1) e.preventDefault();
  });
})();
