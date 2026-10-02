const { run } = require('./snap');
const ids = require('./ids.json');
const fld = (l) => `label:has-text("${l}") >> xpath=ancestor::div[contains(@class,"fi-fo-field-wrp")][1]`;
const scroll = (y) => async (page) => { await page.evaluate((y) => { document.querySelectorAll('*').forEach(e => { if (e.scrollHeight > e.clientHeight + 50 && getComputedStyle(e).overflowY !== 'visible') e.scrollTop = y; }); window.scrollTo(0, y); }, y); await page.waitForTimeout(600); };
run([
  { id: 'sup-materiais', path: '/admin/materials', h: 900, hl: [
    { n: 1, sel: 'text="Criar Material/Peças"', pad: 4 }, { n: 2, sel: '.fi-wi-stats-overview', pad: 4 },
    { n: 3, sel: 'text="Imprimir"', to: 'text="Exportar materials"', pad: 5 }, { n: 4, sel: 'text="Taxa de Estoque Saudável"', pad: 6 }, { n: 5, sel: 'th:has-text("Qtd. Atual")', pad: 3 } ] },
  { id: 'sup-material-novo', path: '/admin/materials/create', h: 1000, hl: [
    { n: 1, sel: fld('SKU / Código'), pad: 4 }, { n: 2, sel: fld('Grupos de Ativo Compatíveis'), pad: 4 }, { n: 3, sel: fld('Fornecedor Homologado'), pad: 4 } ] },
  { id: 'sup-material-estoque', path: '/admin/materials/create', h: 1000, before: scroll(900), hl: [
    { n: 1, sel: fld('Exige Nº de Série'), pad: 4 }, { n: 2, sel: fld('Dias de Garantia'), pad: 4 } ] },
  { id: 'sup-fornecedores', path: '/admin/suppliers', h: 900, hl: [
    { n: 1, sel: 'text="Criar Fornecedor"', pad: 4 }, { n: 2, sel: '.fi-wi-stats-overview', pad: 4 }, { n: 3, sel: 'text="Imprimir"', pad: 4 }, { n: 4, sel: 'text="Taxa de Compliance Completo"', pad: 6 } ] },
  { id: 'sup-fornecedor-novo', path: '/admin/suppliers/create', h: 800, hl: [
    { n: 1, sel: 'text="Identificação do Fornecedor"', pad: 6 }, { n: 2, sel: 'text="Dados Bancários"', pad: 6 }, { n: 3, sel: 'text="Critérios de Homologação"', pad: 6 }, { n: 4, sel: 'text="Arquivos e Certidões"', pad: 6 } ] },
  { id: 'sup-pecas', path: '/admin/parts-requests', h: 780, hl: [
    { n: 1, sel: '.fi-wi-stats-overview', pad: 4 }, { n: 2, sel: 'th:has-text("Situação")', pad: 3 }, { n: 3, sel: 'text="Atualizar" >> nth=0', pad: 4 },
    { n: 4, sel: 'text="Converter em Requisição" >> nth=0', pad: 4 }, { n: 5, sel: 'text="Imprimir"', pad: 4 } ] },
  { id: 'sup-requisicoes', path: '/admin/material-requests', h: 780, hl: [
    { n: 1, sel: 'text="Nova Requisição"', pad: 4 }, { n: 2, sel: 'th:has-text("Prioridade")', pad: 3 }, { n: 3, sel: 'th:has-text("Status")', pad: 3 } ] },
  { id: 'sup-req-cotada', path: `/admin/material-requests/${ids.req_cotada}/edit`, h: 1000, hl: [
    { n: 1, sel: fld('Prioridade'), pad: 4 }, { n: 2, sel: fld('Status'), pad: 4 }, { n: 3, sel: 'text="Itens da Requisição"', pad: 6 } ] },
  { id: 'sup-oc', path: '/admin/purchase-orders', h: 780, hl: [
    { n: 1, sel: 'text="Nova Ordem de Compra (Avulsa)"', pad: 4 }, { n: 2, sel: '.fi-wi-stats-overview', pad: 4 }, { n: 3, sel: 'th:has-text("Status")', pad: 3 } ] },
  { id: 'sup-oc-edit', path: `/admin/purchase-orders/${ids.oc_aberta}/edit`, h: 900, hl: [
    { n: 1, sel: 'text="Registrar Recebimento"', pad: 4 }, { n: 2, sel: 'text="Cancelar Ordem de Compra"', pad: 4 }, { n: 3, sel: 'text="Itens da Ordem de Compra"', pad: 6 }, { n: 4, sel: 'th:has-text("Pendente")', pad: 3 } ] },
  { id: 'sup-recebimento', path: '/admin/goods-receipts', h: 600, hl: [
    { n: 1, sel: 'text="Novo Recebimento"', pad: 4 }, { n: 2, sel: 'th:has-text("Nota Fiscal")', pad: 3 } ] },
  { id: 'sup-recebimento-novo', path: '/admin/goods-receipts/create', h: 760, hl: [
    { n: 1, sel: fld('Ordem de Compra'), pad: 4 }, { n: 2, sel: fld('Número da Nota Fiscal'), pad: 4 } ] },
], { w: 1366, h: 780 });
