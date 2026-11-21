// Catalogo do painel /observatorio: KPIs, indicadores, series e CSVs.
//
// REGRA DE OURO: todo numero tem fonte e periodo, e cada item e de UM destes tipos:
//   "real"       valor publicado por uma fonte (as mesmas do Painel do Mercado, com o texto de fonte literal);
//   "estimativa" derivado de dado real por metodo declarado na propria tela;
//   "simulado"   valor ILUSTRATIVO, fabricado so' para mostrar o componente. Nunca entra em CSV e traz marca
//                visivel ("Simulacao"). Tudo que e' simulado esta concentrado neste arquivo, em `simulacao`.
//
// Para publicar SEM nenhuma simulacao (cada bloco volta ao estado "Em breve"):
//   OBSERVATORIO_SEM_SIMULACAO=1 npm run build
const SIMULACAO_ATIVA = process.env.OBSERVATORIO_SEM_SIMULACAO !== '1';

const fmt = (n, c = 1) => n.toLocaleString('pt-BR', { minimumFractionDigits: c, maximumFractionDigits: c });

const METODOLOGIA = '/painel-mercado/metodologia/';

// ---- Fontes (texto literal do Painel do Mercado) ----
const F_KPMG = 'KPMG, Rental Market Report (a pedido da Analoc, com apoio da Sobratema), divulgado pela Sobratema em 26/03/2026';
const F_SOBRATEMA_ANALOC = 'Estudo Sobratema do Mercado Brasileiro de Equipamentos para Construção, via Analoc (03/01/2025)';
const F_VENDAS = 'Estudo Sobratema do Mercado Brasileiro de Equipamentos para Construção (2023 e 2024, via Analoc, 03/01/2025; 2025, via SICEPOT-RS, 02/02/2026)';
const F_MECANIZACAO = 'Sobratema, via SICEPOT-RS (02/02/2026)';
const F_CARGA = 'Analoc, via Revista Construa (15/10/2025)';
const F_BCB = (cod, nome) => `Banco Central do Brasil, SGS série ${cod} (${nome})`;

// ---- Dados reais ----
const FATURAMENTO = { 2025: 49.4, 2026: 52.9 }; // R$ bilhoes; 2025 realizado, 2026 projecao
const REGIOES = [
  { id: 'sudeste', rot: 'Sudeste', pct: 60 }, { id: 'nordeste', rot: 'Nordeste', pct: 15 }, { id: 'sul', rot: 'Sul', pct: 13 },
  { id: 'centro-oeste', rot: 'Centro-Oeste', pct: 7 }, { id: 'norte', rot: 'Norte', pct: 5 }
];
const VENDAS = { anos: ['2023', '2024', '2025'], total: [53.5, 58.2, 56.3], amarela: [32.3, 36.6, 34.5] }; // mil unidades
const FROTA_PARADA = { anos: ['2017', '2023', '2024'], valores: [57, 19, 11] }; // %

// ---- Simulacoes (ILUSTRATIVAS) ----
const TAXA_RETRO = 0.07; // mesma taxa que a KPMG projeta para 2026, aplicada para tras
const retroFaturamento = {}; // 2021..2024 a partir do realizado de 2025
for (let a = 2024; a >= 2021; a--) retroFaturamento[a] = +(FATURAMENTO[2025] / Math.pow(1 + TAXA_RETRO, 2025 - a)).toFixed(1);

const simulacao = {
  ativa: SIMULACAO_ATIVA,
  rotulo: 'Simulação',
  aviso: 'Valores ilustrativos para demonstrar o painel. Não são dados publicados nem resultado de pesquisa.',
  // Historico do faturamento anterior a 2025 (nao existe serie publicada nas nossas fontes)
  faturamentoHistorico: {
    anos: ['2021', '2022', '2023', '2024'], valores: [2021, 2022, 2023, 2024].map(a => retroFaturamento[a]),
    metodo: `Retroprojeção a partir do realizado de 2025 (R$ ${fmt(FATURAMENTO[2025])} bi), aplicando para trás a taxa de ${TAXA_RETRO * 100}% ao ano que a KPMG projeta para 2026. Não há série histórica publicada nas fontes do Painel.`
  },
  // Vendas por regiao (as fontes nao divulgam). Mesmo metodo das estimativas regionais do faturamento.
  vendasRegionais: REGIOES.map(r => ({ rot: r.rot, total: +(VENDAS.total[2] * r.pct / 100).toFixed(1), amarela: +(VENDAS.amarela[2] * r.pct / 100).toFixed(1) })),
  vendasRegionaisMetodo: 'Vendas de 2025 (total de máquinas e linha amarela) multiplicadas pela participação regional no faturamento. As fontes não divulgam vendas por região.',
  // Dados proprios do Observatorio (ainda nao ha amostra minima de 10 empresas)
  proprios: {
    resumo: { empresas: 48, entrevistas: 52, respostas: 311, estados: 7 },
    porEstado: [{ rotulo: 'SP', empresas: 18, percentual: 40.9 }, { rotulo: 'MG', empresas: 11, percentual: 25.0 }, { rotulo: 'PR', empresas: 8, percentual: 18.2 }, { rotulo: 'RJ', empresas: 7, percentual: 15.9 }],
    porPorte: [{ rotulo: 'ME', empresas: 24, percentual: 50.0 }, { rotulo: 'EPP', empresas: 15, percentual: 31.3 }, { rotulo: 'Média', empresas: 9, percentual: 18.8 }],
    temas: [{ rotulo: 'Manutenção', empresas: 31, percentual: 64.6 }, { rotulo: 'Financeiro', empresas: 27, percentual: 56.3 }, { rotulo: 'Equipe', empresas: 22, percentual: 45.8 }, { rotulo: 'Tributário', empresas: 17, percentual: 35.4 }],
    radar: [{ tema: 'Tributário', status: 'Crescendo', variacao: '+12,0 p.p.' }, { tema: 'Tecnologia', status: 'Novo', variacao: '' }, { tema: 'Manutenção', status: 'Recorrente', variacao: '0,0 p.p.' }, { tema: 'Financeiro', status: 'Diminuindo', variacao: '−6,5 p.p.' }],
    benchmark: [
      { nome: 'Utilização física da frota', unidade: '%', n: 48, p25: 52, p50: 61, p75: 70 },
      { nome: 'Disponibilidade da frota', unidade: '%', n: 48, p25: 84, p50: 89, p75: 93 },
      { nome: 'Custo de manutenção sobre a receita', unidade: '%', n: 46, p25: 7, p50: 10, p75: 14 },
      { nome: 'Idade média da frota', unidade: 'anos', n: 44, p25: 4, p50: 6, p75: 9 }
    ]
  }
};

// ---- KPIs (faixa do topo) ----
const kpis = [
  { id: 'faturamento', rotulo: 'Faturamento do setor', valor: 'R$ 49,4 bi', periodo: '2025 (realizado)', variacao: '+7% projetado para 2026 (R$ 52,9 bi)', fonte: F_KPMG, regional: true },
  { id: 'penetracao', rotulo: 'Penetração da locação', valor: '40%', contexto: 'Locação vs. propriedade', periodo: 'Relatório divulgado em 26/03/2026', fonte: F_KPMG },
  { id: 'mecanizacao', rotulo: 'Locação na mecanização', valor: '≈ 30%', contexto: 'Até 60% em mercados globais', periodo: 'Divulgado em 02/02/2026', fonte: F_MECANIZACAO },
  { id: 'empresas', rotulo: 'Empresas no setor', valor: '50 mil', contexto: '200 mil funcionários', periodo: 'Relatório divulgado em 26/03/2026', fonte: F_KPMG },
  { id: 'pib', rotulo: 'Peso no PIB', valor: '0,4%', contexto: 'EUA 0,26% · Europa 0,15%', periodo: 'Relatório divulgado em 26/03/2026', fonte: F_KPMG },
  { id: 'carga', rotulo: 'Carga tributária na locação', valor: '10% a 14%', contexto: 'Da receita bruta, com a reforma tributária (até 14%)', periodo: 'Projeção divulgada em 15/10/2025', fonte: F_CARGA }
];

// ---- Cards do painel (grade de indicadores) ----
// tipo: "serie" (sparkline), "barras" (barras horizontais), "bcb" (serie ao vivo do Banco Central)
const indicadores = [
  { id: 'faturamento', titulo: 'Faturamento do setor', tipo: 'serie', valor: 'R$ 49,4 bi', unidade: 'R$ bilhões', periodo: '2025 (realizado) e 2026 (projeção)', variacao: '+7% projetado para 2026', fonte: F_KPMG, coleta: '26/03/2026', spark: { labels: ['2025', '2026 (proj.)'], valores: [49.4, 52.9] }, csv: 'faturamento-setor', regional: true },
  { id: 'participacao-regional', titulo: 'Participação por região', tipo: 'barras', valor: '60%', rotuloValor: 'Sudeste', unidade: '% do faturamento', periodo: '2025', fonte: F_KPMG, coleta: '26/03/2026', barras: REGIOES.map(r => ({ rot: r.rot, v: r.pct, txt: r.pct + '%', id: r.id })), max: 60, csv: 'participacao-regional', nota: 'Os valores em reais por região são estimativas (participação × faturamento nacional); a fonte não mede faturamento por região.' },
  { id: 'vendas-maquinas', titulo: 'Vendas de máquinas para construção', tipo: 'serie', valor: '56,3 mil', unidade: 'mil unidades por ano', periodo: '2023 a 2025', fonte: F_VENDAS, coleta: '02/02/2026', spark: { labels: VENDAS.anos, valores: VENDAS.total }, csv: 'vendas-maquinas', equipamento: true },
  { id: 'linha-amarela', titulo: 'Vendas da linha amarela', tipo: 'serie', valor: '34,5 mil', unidade: 'mil unidades por ano', periodo: '2023 a 2025', fonte: F_VENDAS, coleta: '02/02/2026', spark: { labels: VENDAS.anos, valores: VENDAS.amarela }, csv: 'vendas-linha-amarela', nota: 'A linha amarela de 2023 é aproximada ("quase 32,3 mil").', equipamento: true },
  { id: 'frota-parada', titulo: 'Frota parada', tipo: 'serie', valor: '11%', unidade: '% da frota, segundo o Estudo Sobratema', periodo: '2017, 2023 e 2024', fonte: F_SOBRATEMA_ANALOC, coleta: '03/01/2025', spark: { labels: FROTA_PARADA.anos, valores: FROTA_PARADA.valores }, csv: 'frota-parada' },
  { id: 'locacao-categoria', titulo: 'Locação nas vendas por categoria', tipo: 'barras', valor: '90% a 95%', rotuloValor: 'Plataformas aéreas', unidade: '% das vendas feitas para locadoras', periodo: 'Estudo divulgado em 03/01/2025', fonte: F_SOBRATEMA_ANALOC + '. Para guindastes e plataformas aéreas, a Analoc cita 90% a 95% das vendas (Revista Construa, 15/10/2025)', coleta: '03/01/2025', barras: [{ rot: 'Plataformas aéreas', v: 92.5, txt: '90% a 95%' }, { rot: 'Betoneiras', v: 72.5, txt: '70% a 75%' }], max: 100, csv: 'locacao-por-categoria', nota: 'Faixas informadas pela fonte; a barra marca o ponto médio.' },
  { id: 'financiamento', titulo: 'Forma de financiamento da compra de máquinas', tipo: 'barras', valor: '33%', rotuloValor: 'Capital próprio', unidade: '% dos entrevistados', periodo: 'Estudo divulgado em 03/01/2025', fonte: F_SOBRATEMA_ANALOC + '. Entre as modalidades citadas na fonte, as três de maior participação', coleta: '03/01/2025', barras: [{ rot: 'Capital próprio', v: 33, txt: '33%' }, { rot: 'Finame', v: 23, txt: '23%' }, { rot: 'CDC', v: 19, txt: '19%' }], max: 50, csv: 'financiamento-maquinas' },
  { id: 'mecanizacao', titulo: 'Locação na mecanização', tipo: 'barras', valor: '≈ 30%', rotuloValor: 'Brasil', unidade: '% da mecanização atendida por máquinas locadas', periodo: 'Divulgado em 02/02/2026', fonte: F_MECANIZACAO + '. A penetração de 40% (locação vs. propriedade) vem da KPMG e tem definição diferente', coleta: '02/02/2026', barras: [{ rot: 'Brasil', v: 30, txt: 'cerca de 30%' }, { rot: 'Mercados globais', v: 60, txt: 'até 60%' }], max: 70, csv: 'locacao-mecanizacao' },
  { id: 'pib', titulo: 'Peso do setor no PIB', tipo: 'barras', valor: '0,4%', rotuloValor: 'Brasil', unidade: '% do PIB', periodo: 'Relatório divulgado em 26/03/2026', fonte: F_KPMG, coleta: '26/03/2026', barras: [{ rot: 'Brasil', v: 0.4, txt: '0,4%' }, { rot: 'Estados Unidos', v: 0.26, txt: '0,26%' }, { rot: 'Europa', v: 0.15, txt: '0,15%' }], max: 0.5, csv: 'peso-pib' },
  { id: 'selic', titulo: 'Selic (meta)', tipo: 'bcb', codigo: 432, unidade: '% ao ano', periodo: 'Série diária', fonte: F_BCB(432, 'meta Selic'), rotuloFonte: 'Selic, meta ao ano' },
  { id: 'incc', titulo: 'INCC-M acumulado em 12 meses', tipo: 'bcb', codigo: 192, unidade: '% em 12 meses', periodo: 'Série mensal', fonte: F_BCB(192, 'INCC-M, variação mensal') + ', acumulado em 12 meses pela Oravel', rotuloFonte: 'INCC-M' },
  { id: 'dolar', titulo: 'Dólar americano (venda)', tipo: 'bcb', codigo: 1, unidade: 'R$ por US$', periodo: 'Série diária', fonte: F_BCB(1, 'dólar americano, venda'), rotuloFonte: 'Dólar' }
];

// ---- Indicadores do grafico principal ----
const grafico = [
  { id: 'selic', rot: 'Selic (meta, % a.a.)', tipo: 'bcb', codigo: 432, unidade: '% a.a.', casas: 2 },
  { id: 'incc', rot: 'INCC-M acumulado em 12 meses (%)', tipo: 'bcb', codigo: 192, unidade: '%', casas: 2 },
  { id: 'dolar', rot: 'Dólar americano (R$)', tipo: 'bcb', codigo: 1, unidade: 'R$', casas: 4 },
  { id: 'faturamento', rot: 'Faturamento do setor (R$ bi)', tipo: 'anual', unidade: 'R$ bi', casas: 1 },
  { id: 'vendas', rot: 'Vendas de máquinas (mil unidades)', tipo: 'anual', unidade: 'mil un.', casas: 1 },
  { id: 'frota', rot: 'Frota parada (%)', tipo: 'anual', unidade: '%', casas: 0 }
];
const periodos = [{ id: '6m', rot: '6 meses', meses: 6 }, { id: '12m', rot: '12 meses', meses: 12 }, { id: '24m', rot: '24 meses', meses: 24 }, { id: '5a', rot: '5 anos', meses: 60 }];

// ---- CSVs estaticos (somente dado REAL; nada simulado) ----
const csvLinha = (c) => c.map(x => `"${String(x).replace(/"/g, '""')}"`).join(';');
const cab = ['indicador', 'periodo', 'valor', 'unidade', 'fonte', 'data_da_fonte', 'observacao'];
const mk = (id, nome, unidade, fonte, data, linhas, obs = '') => ({
  id, nome, nomeArquivo: id + '.csv',
  conteudo: '﻿' + [csvLinha(cab), ...linhas.map(([periodo, valor, un]) => csvLinha([nome, periodo, String(valor).replace('.', ','), un || unidade, fonte, data, obs]))].join('\r\n') + '\r\n'
});
const csvs = [
  mk('faturamento-setor', 'Faturamento do setor de locação', 'R$ bilhões', F_KPMG, '26/03/2026', [['2025 (realizado)', 49.4], ['2026 (projeção)', 52.9]], 'Valores por região são estimativas e não constam deste arquivo.'),
  mk('participacao-regional', 'Participação regional no faturamento', '%', F_KPMG, '26/03/2026', REGIOES.map(r => [r.rot + ' (2025)', r.pct])),
  mk('vendas-maquinas', 'Vendas de máquinas para construção', 'mil unidades', F_VENDAS, '02/02/2026', VENDAS.anos.map((a, i) => [a, VENDAS.total[i]])),
  mk('vendas-linha-amarela', 'Vendas da linha amarela', 'mil unidades', F_VENDAS, '02/02/2026', VENDAS.anos.map((a, i) => [a, VENDAS.amarela[i]]), 'A linha amarela de 2023 é aproximada ("quase 32,3 mil").'),
  mk('frota-parada', 'Frota parada', '%', F_SOBRATEMA_ANALOC, '03/01/2025', FROTA_PARADA.anos.map((a, i) => [a, FROTA_PARADA.valores[i]])),
  mk('locacao-por-categoria', 'Participação da locação nas vendas por categoria', '% das vendas', F_SOBRATEMA_ANALOC, '03/01/2025', [['Plataformas aéreas (faixa)', '90 a 95'], ['Betoneiras (faixa)', '70 a 75']], 'Faixas informadas pela fonte.'),
  mk('financiamento-maquinas', 'Forma de financiamento da compra de máquinas', '% dos entrevistados', F_SOBRATEMA_ANALOC, '03/01/2025', [['Capital próprio', 33], ['Finame', 23], ['CDC', 19]]),
  mk('locacao-mecanizacao', 'Locação na mecanização', '% da mecanização', F_MECANIZACAO, '02/02/2026', [['Brasil', 'cerca de 30'], ['Mercados globais', 'até 60']]),
  mk('peso-pib', 'Peso do setor de locação no PIB', '% do PIB', F_KPMG, '26/03/2026', [['Brasil', '0,4'], ['Estados Unidos', '0,26'], ['Europa', '0,15']])
];

// ---- Fonte curta (visivel nos cards) e geometria dos sparklines (calculada no build, sem JS) ----
const KPMG_CURTA = 'KPMG, Rental Market Report · 26/03/2026';
const CURTA = {
  faturamento: KPMG_CURTA, 'participacao-regional': KPMG_CURTA, pib: KPMG_CURTA,
  'vendas-maquinas': 'Sobratema (Estudo do Mercado), via Analoc e SICEPOT-RS · 02/02/2026', 'linha-amarela': 'Sobratema (Estudo do Mercado), via Analoc e SICEPOT-RS · 02/02/2026',
  'frota-parada': 'Sobratema, via Analoc · 03/01/2025', 'locacao-categoria': 'Sobratema, via Analoc · 03/01/2025', financiamento: 'Sobratema, via Analoc · 03/01/2025',
  mecanizacao: 'Sobratema, via SICEPOT-RS · 02/02/2026',
  selic: 'Banco Central do Brasil · SGS 432', incc: 'Banco Central do Brasil · SGS 192', dolar: 'Banco Central do Brasil · SGS 1'
};
const KPI_CURTA = { faturamento: KPMG_CURTA, penetracao: KPMG_CURTA, mecanizacao: 'Sobratema, via SICEPOT-RS · 02/02/2026', empresas: KPMG_CURTA, pib: KPMG_CURTA, carga: 'Analoc, via Revista Construa · 15/10/2025' };
kpis.forEach(k => { k.fonteCurta = KPI_CURTA[k.id] || k.fonte; });

function sparkSvg(valores, projecaoNoFim) {
  const W = 120, H = 36, P = 4, n = valores.length;
  const min = Math.min(...valores), max = Math.max(...valores), faixa = (max - min) || 1;
  const pts = valores.map((v, i) => ({ x: +(P + (n === 1 ? (W - 2 * P) / 2 : i * (W - 2 * P) / (n - 1))).toFixed(1), y: +(H - P - ((v - min) / faixa) * (H - 2 * P)).toFixed(1) }));
  const str = (a) => a.map(p => p.x + ',' + p.y).join(' ');
  const reais = projecaoNoFim ? pts.slice(0, -1) : pts;
  return {
    w: W, h: H, pontosReais: str(reais), pontosProjecao: projecaoNoFim ? str(pts.slice(-2)) : '', ultimo: pts[pts.length - 1],
    alt: valores.length ? 'Evolução: ' + valores.map(v => fmt(v, Number.isInteger(v) ? 0 : 1)).join(', ') : ''
  };
}
indicadores.forEach(i => {
  i.fonteCurta = CURTA[i.id] || i.fonte;
  if (i.spark) {
    i.spark.svg = sparkSvg(i.spark.valores, i.id === 'faturamento');
    i.spark.svg.alt = i.titulo + '. ' + i.spark.labels.map((l, k) => l + ': ' + fmt(i.spark.valores[k], Number.isInteger(i.spark.valores[k]) ? 0 : 1)).join('; ');
  }
});

// Recorte enviado ao navegador (sem CSVs, sem os valores ilustrativos dos blocos proprios, que sao HTML estatico)
const cliente = {
  atualizadoEm: '26/03/2026', metodologia: METODOLOGIA, grafico, periodos, regioes: REGIOES, faturamento: FATURAMENTO, vendas: VENDAS, frotaParada: FROTA_PARADA,
  kpis: kpis.map(k => ({ id: k.id, valor: k.valor })),
  indicadores: indicadores.map(i => ({ id: i.id, titulo: i.titulo, tipo: i.tipo, valor: i.valor || null, rotuloValor: i.rotuloValor || null, unidade: i.unidade, periodo: i.periodo, variacao: i.variacao || null,
    fonte: i.fonte, fonteCurta: i.fonteCurta, coleta: i.coleta || null, nota: i.nota || null, csv: i.csv || null, codigo: i.codigo || null, barras: i.barras || null, max: i.max || null,
    spark: i.spark ? { labels: i.spark.labels, valores: i.spark.valores } : null })),
  simulacao: { ativa: simulacao.ativa, aviso: simulacao.aviso, faturamentoHistorico: simulacao.ativa ? simulacao.faturamentoHistorico : null,
    vendasRegionais: simulacao.ativa ? simulacao.vendasRegionais : null, vendasRegionaisMetodo: simulacao.vendasRegionaisMetodo }
};

module.exports = {
  atualizadoEm: '26/03/2026', // data da fonte estatica mais recente (KPMG). Series do Banco Central mostram a propria data.
  metodologia: METODOLOGIA, simulacao, kpis, indicadores, grafico, periodos, regioes: REGIOES, faturamento: FATURAMENTO,
  vendas: VENDAS, frotaParada: FROTA_PARADA, csvs, cliente
};
