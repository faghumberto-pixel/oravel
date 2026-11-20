// Registro central do Centro de Ferramentas.
// Para criar uma ferramenta nova (padrao wizard):
//   1. adicionar uma entrada aqui;
//   2. criar src/assets/tools/<slug>.js chamando OravelTool.register({...});
//   3. criar src/ferramentas/<slug>/index.njk com layout: tool.njk e ferramenta: "<slug>".
// Ferramentas "legado" so aparecem no registro (relacionados, schema); o card do hub e manual.
module.exports = {
  // Sinais anonimos (ferramenta concluida, download) para a Central da Oravel (modulo Relacionamento).
  // So publicar o site com isto ligado DEPOIS de a Central estar no ar com a rota. Vazio = desligado.
  telemetriaUrl: "https://app.oravel.com.br/api/relacionamento/site-evento",
  // Visitas as paginas do oravel.org (mesmo coletor do institucional; aparece em Visitas do Site, coluna Site). Vazio = desligado.
  visitasUrl: "https://app.oravel.com.br/api/site-track",
  itens: {
    "margem-equipamento": {
      titulo: "Calculadora de Margem por Equipamento", resumo: "Veja a margem real de cada ativo da frota: receita gerada menos custo total de posse (depreciação, manutenção, seguro, rastreamento, capital). Identifique equipamentos que drenam caixa.",
      tipo: "formulario", categoria: "Gestão", tempo: "5 min", nivel: "Intermediário", status: "ativa", versao: "2.0", video: "",
      artigos: ["custo-por-hora-quanto-custa-de-verdade-operar-cada-maquina", "preco-da-diaria-a-conta-nao-termina-na-diaria", "por-que-a-manutencao-decide-o-resultado-da-locadora"]
    },
    "disponibilidade": {
      titulo: "Calculadora de Disponibilidade de Equipamentos", resumo: "Calcule a disponibilidade física e operacional da frota: MTBF, MTTR, tempo parado por manutenção, inspeção, logística. Veja o impacto financeiro da indisponibilidade na receita.",
      tipo: "formulario", categoria: "Gestão", tempo: "5 min", nivel: "Intermediário", status: "ativa", versao: "2.0", video: "",
      artigos: ["disponibilidade-da-frota-mtbf-mttr-e-como-calcular-por-categoria-de-maquina", "o-custo-escondido-da-frota-parada", "estoque-de-pecas-da-oficina-curva-abc-e-criticidade-na-locadora"]
    },
    "roi-manutencao": {
      titulo: "Calculadora de ROI de Manutenção", resumo: "Compare preventiva vs corretiva: calcule o ROI de investir em manutenção preventiva, preditiva ou RCM. Veja redução de falhas, ganho de disponibilidade, economia com peças e payback do programa.",
      tipo: "formulario", categoria: "Gestão", tempo: "6 min", nivel: "Avançado", status: "ativa", versao: "2.0", video: "",
      artigos: ["preventiva-ou-corretiva-qual-compensa", "por-que-a-manutencao-decide-o-resultado-da-locadora", "estoque-de-pecas-da-oficina-curva-abc-e-criticidade-na-locadora"]
    },
    "custo-propriedade": {
      titulo: "Calculadora de Custo Total de Propriedade (TCO)", resumo: "Calcule o TCO completo do equipamento: aquisição, depreciação, juros, seguro, manutenção, combustível, operador, rastreamento. Compare comprar vs alugar vs terceirizar. Inclui VPL e TIR.",
      tipo: "formulario", categoria: "Gestão", tempo: "6 min", nivel: "Avançado", status: "ativa", versao: "2.0", video: "",
      artigos: ["custo-por-hora-quanto-custa-de-verdade-operar-cada-maquina", "a-regra-72-20-8-como-as-locadoras-americanas-leem-a-propria-frota", "tarifas-e-custo-da-frota-nos-eua-como-as-maiores-locadoras-descrevem-o-risco"]
    },
    "viabilidade-frota": {
      titulo: "Calculadora de Viabilidade de Nova Frota", resumo: "Avalie a expansão da frota: NPV, TIR, payback, capacidade de pagamento, ponto de equilíbrio. Compare cenários de compra, aluguel ou consórcio. Inclui análise de sensibilidade e stress test.",
      tipo: "formulario", categoria: "Gestão", tempo: "8 min", nivel: "Avançado", status: "ativa", versao: "2.0", video: "",
      artigos: ["a-regra-72-20-8-como-as-locadoras-americanas-leem-a-propria-frota", "tarifas-e-custo-da-frota-nos-eua-como-as-maiores-locadoras-descrevem-o-risco", "custo-por-hora-quanto-custa-de-verdade-operar-cada-maquina"]
    },
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
