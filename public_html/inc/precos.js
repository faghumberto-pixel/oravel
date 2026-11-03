(function () {
  var API = 'https://app.oravel.com.br/api/public/planos';
  var CICLOS = { monthly: 'mês', mensal: 'mês', yearly: 'ano', anual: 'ano' };
  function brl(v) { return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; }

  function preco(box, p) {
    var un = CICLOS[p.ciclo] || 'mês';
    var rec = el('div', 'pv-rec');
    var cheio = el('s', 'pv-cheio', brl(p.valor_cheio));
    rec.appendChild(cheio);
    rec.appendChild(el('b', 'pv-valor', ' ' + brl(p.valor_recorrente)));
    rec.appendChild(el('span', 'pv-un', '/' + un));
    rec.appendChild(el('span', 'pv-chip', p.desconto_percentual + '% de desconto no cartão recorrente'));
    box.appendChild(rec);
    box.appendChild(el('div', 'pv-linha', 'Ou ' + brl(p.valor_cheio) + '/' + un + ' no boleto ou Pix, sem compromisso: 30 dias de acesso a cada pagamento.'));
    if (p.implantacao) {
      var i = p.implantacao;
      var t = (i.opcional ? 'Implantação opcional: ' : 'Implantação: ') + brl(i.valor) + (i.parcelas_max > 1 ? ' (à vista ou em até ' + i.parcelas_max + 'x)' : '');
      box.appendChild(el('div', 'pv-linha', t));
    }
    box.hidden = false;
  }

  function taxas(box, lista) {
    if (!lista.length) return;
    box.appendChild(el('h3', 'pv-tit', 'Taxas de configuração · opcionais, pagamento único'));
    var ul = el('ul', 'pv-taxas');
    lista.forEach(function (t) {
      var li = el('li');
      var topo = el('div', 'pv-t-topo');
      topo.appendChild(el('b', 'pv-t-nome', t.nome));
      topo.appendChild(el('span', 'pv-t-valor', brl(t.valor)));
      li.appendChild(topo);
      if (t.descricao) li.appendChild(el('span', 'pv-t-desc', t.descricao));
      ul.appendChild(li);
    });
    box.appendChild(ul);
    box.appendChild(el('p', 'pv-linha', 'Prefere configurar sozinho? Veja o treinamento na Academia Oravel.'));
    box.hidden = false;
  }

  function iniciar() {
    var caixas = document.querySelectorAll('[data-preco], [data-taxas]');
    if (!caixas.length || !window.fetch) return;
    fetch(API, { headers: { Accept: 'application/json' } }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
      if (!j) return;
      var porCodigo = {};
      (j.data || []).forEach(function (p) { porCodigo[p.codigo] = p; });
      caixas.forEach(function (box) {
        if (box.hasAttribute('data-taxas')) { taxas(box, j.taxas || []); return; }
        var p = porCodigo[box.getAttribute('data-preco')];
        if (p) preco(box, p);
      });
    }).catch(function () { /* sem a API o bloco continua oculto e os botões seguem funcionando */ });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar); else iniciar();
})();
