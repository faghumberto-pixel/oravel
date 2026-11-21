// Dados do Observatorio Oravel (hub /observatorio/, /observatorio/fontes/, /observatorio/participantes/).
// Regra: so entra aqui numero publicado por fonte identificavel. Nada de dado inventado.
module.exports = {
  // Indicadores em destaque (os mesmos do Painel do Mercado, com a fonte de cada um).
  indicadores: [
    { valor: "R$ 49,4 bi", rotulo: "Faturamento do setor de locação em 2025", fonte: "KPMG, Rental Market Report (a pedido da Analoc)", data: "26/03/2026" },
    { valor: "~30%", rotulo: "Participação da locação na mecanização no Brasil, contra até 60% em mercados globais", fonte: "Sobratema, via SICEPOT-RS", data: "02/02/2026" },
    { valor: "40%", rotulo: "Penetração da locação frente à propriedade (definição da KPMG, diferente da anterior)", fonte: "KPMG, Rental Market Report", data: "26/03/2026" },
    { valor: "10% a 14%", rotulo: "Carga tributária sobre a receita bruta de locadoras com a reforma tributária", fonte: "Analoc, via Revista Construa", data: "15/10/2025" }
  ],

  // Fontes e instituicoes. status: "utilizada" (ja citada em algum conteudo) ou "a incorporar".
  fontes: [
    { grupo: "Entidades do setor e estudos de mercado", itens: [
      { nome: "Analoc", desc: "Associação Nacional das Empresas de Locação de Equipamentos. Estudos e posicionamentos do setor.", status: "utilizada" },
      { nome: "Sobratema", desc: "Associação Brasileira de Tecnologia para Equipamentos e Manutenção. Estudo do Mercado Brasileiro de Equipamentos para Construção.", status: "utilizada" },
      { nome: "KPMG", desc: "Rental Market Report, encomendado pela Analoc.", status: "utilizada" },
      { nome: "SICEPOT-RS e Revista Construa", desc: "Veículos que divulgaram dados da Sobratema e da Analoc, sempre citados com a data.", status: "utilizada" }
    ]},
    { grupo: "Estatísticas oficiais e instituições públicas", itens: [
      { nome: "IBGE", desc: "Indicadores de construção, indústria e economia.", status: "a incorporar" },
      { nome: "Receita Federal", desc: "Dados de empresas e legislação tributária.", status: "a incorporar" },
      { nome: "Banco Central", desc: "Juros e crédito, determinantes do custo de capital da frota.", status: "a incorporar" },
      { nome: "BNDES", desc: "Linhas de financiamento de máquinas e equipamentos.", status: "a incorporar" },
      { nome: "IPEA", desc: "Pesquisas sobre investimento e infraestrutura.", status: "a incorporar" },
      { nome: "FGV", desc: "Indicadores de confiança e do setor da construção.", status: "a incorporar" }
    ]},
    { grupo: "Normas, legislação e literatura técnica", itens: [
      { nome: "Legislação", desc: "Leis e regulamentos citados nos artigos de Legislação e Tributação, com link para o texto oficial.", status: "utilizada" },
      { nome: "Normas técnicas", desc: "Normas de manutenção e segurança de equipamentos, citadas com número e ano.", status: "a incorporar" },
      { nome: "Artigos científicos e instituições internacionais", desc: "Estudos sobre gestão de ativos e manutenção, sempre com referência completa.", status: "a incorporar" }
    ]}
  ],

  // Cronologia publica. Cada marco tem estado: "feito" ou "previsto". Atualizar conforme acontecer.
  cronologia: [
    { marco: "Painel do Mercado publicado", estado: "feito", nota: "Indicadores do setor com fonte e data em cada gráfico." },
    { marco: "Metodologia pública do Painel", estado: "feito", nota: "Critérios de fonte, divergências e limites de interpretação." },
    { marco: "Centro de ferramentas gratuitas", estado: "feito", nota: "Calculadoras e checklists sem cadastro." },
    { marco: "Benchmark da locadora", estado: "feito", nota: "Comparação de indicadores com as metas da própria empresa." },
    { marco: "Primeiros participantes da base anônima", estado: "previsto", nota: "Contribuições de dados via Painel do Mercado." },
    { marco: "Primeiro relatório do Observatório", estado: "previsto", nota: "Depende do volume mínimo de participantes para garantir anonimato." }
  ],

  // Calendario editorial permanente.
  calendario: [
    { ciclo: "Mensal", itens: "Novos artigos, indicadores atualizados e revisão do Radar da Locação" },
    { ciclo: "Trimestral", itens: "Relatório temático do Observatório" },
    { ciclo: "Semestral", itens: "Panorama Nacional da Locação" },
    { ciclo: "Anual", itens: "State of Rental Brasil" }
  ],

  perguntas: [
    { q: "Como está o mercado?", a: "/painel-mercado/", rotulo: "Painel do Mercado" },
    { q: "Como está minha empresa em relação ao mercado?", a: "/ferramentas/benchmark/", rotulo: "Benchmark da Locadora" },
    { q: "Quais tendências merecem atenção?", a: "/la-fora/", rotulo: "Mercado e Tendências" },
    { q: "Quais decisões devo tomar agora?", a: "/ferramentas/", rotulo: "Centro de Ferramentas" }
  ]
};
