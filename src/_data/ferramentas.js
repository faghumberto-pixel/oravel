// Registro central do Centro de Ferramentas.
// Para criar uma ferramenta nova (padrao wizard):
//   1. adicionar uma entrada aqui;
//   2. criar src/assets/tools/<slug>.js chamando OravelTool.register({...});
//   3. criar src/ferramentas/<slug>/index.njk com layout: tool.njk e ferramenta: "<slug>".
// Ferramentas "legado" so aparecem no registro (relacionados, schema); o card do hub e manual.
module.exports = {
  // Telemetria anonima agregada (Worker). Vazio = desligada (nada e enviado).
  telemetriaUrl: "",
  itens: {
    "preco-diaria": {
      titulo: "Calculadora de Preço da Diária",
      resumo: "Calcule o preço mínimo e o sugerido da diária com base em custo de aquisição, vida útil, utilização, manutenção, seguro, rastreamento, custo de capital e margem desejada.",
      tipo: "formulario", categoria: "Vendas", tempo: "5 min", nivel: "Intermediário", status: "ativa", versao: "1.1", video: "",
      artigos: ["preco-da-diaria-a-conta-nao-termina-na-diaria", "estrategias-precificacao-locacao-2026-alem-diaria", "custo-por-hora-quanto-custa-de-verdade-operar-cada-maquina"]
    },
    "calculadora-rentabilidade": {
      titulo: "Calculadora de Rentabilidade",
      resumo: "Margem, retorno anual sobre a frota, payback e resultado depois do custo de capital.",
      tipo: "wizard", categoria: "Vendas", tempo: "4 min", nivel: "Intermediário", status: "ativa", versao: "1.0", video: "",
      artigos: ["preco-da-diaria-a-conta-nao-termina-na-diaria", "custo-por-hora-quanto-custa-de-verdade-operar-cada-maquina", "utilizacao-o-indicador-que-mais-importa"]
    },
    "checklist-vistoria": {
      titulo: "Checklist Inteligente de Vistoria",
      resumo: "Avalie o processo de vistoria na entrega e na devolução e veja o que corrigir primeiro para evitar disputa.",
      tipo: "wizard", categoria: "Gestão", tempo: "4 min", nivel: "Básico", status: "ativa", versao: "1.0", video: "",
      artigos: ["checklist-vistoria-entrega-devolucao-evitar-disputa", "como-inspecionar-na-devolucao-checklist", "vistoria-na-entrega-e-na-devolucao"]
    },
    "benchmark": {
      titulo: "Benchmark da Locadora",
      resumo: "Compare cinco indicadores da locadora com as suas metas e veja onde está a maior distância.",
      tipo: "wizard", categoria: "Gestão", tempo: "5 min", nivel: "Intermediário", status: "ativa", versao: "1.0", video: "",
      artigos: ["painel-mensal-da-locadora-oito-indicadores-e-formulas-modelo-para-copiar", "pesquisa-indicadores-locadora-questionario-anonimo-comparar-mercado", "grupos-benchmarking-locadores-20-groups-ara-como-adaptar-brasil"]
    },
    "equipamento-parado": {
      titulo: "Calculadora de Equipamento Parado",
      resumo: "Quanto a frota parada deixa de faturar por mês e por ano, e quanto dá para recuperar reduzindo a parada.",
      tipo: "wizard",
      categoria: "Gestão",
      tempo: "3 min",
      nivel: "Básico",
      status: "ativa",
      versao: "1.0",
      video: "", // ID do video no YouTube (ex.: "dQw4w9WgXcQ"). Vazio = mostra o espaco reservado.
      // slugs de artigos (fileSlug) em src/content/**
      artigos: [
        "o-custo-escondido-da-frota-parada",
        "disponibilidade-da-frota-mtbf-mttr-e-como-calcular-por-categoria-de-maquina",
        "custo-por-hora-quanto-custa-de-verdade-operar-cada-maquina"
      ]
    }
  }
};
