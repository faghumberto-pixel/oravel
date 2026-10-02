const { run } = require('./snap');
const nav = (t) => `.fi-sidebar a:has-text("${t}")`;
const H = (n, t) => ({ n, sel: nav(t), pad: -2, inside: true });
run([
  { id: 'sup-menu-compras', path: '/admin/material-requests', side: true, h: 1000, clip: { x: 0, y: 205, width: 330, height: 440 }, hl: [
    H(1, 'Requisições De Compra'), H(2, 'Ordens De Compra'), H(3, 'Recebimentos De Compra'), H(4, 'Solicitações de peças'), H(5, 'Fornecedores') ] },
], { w: 1366, h: 780 });
