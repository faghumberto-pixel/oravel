OravelTool.register({
  slug: 'margem-equipamento',
  modo: 'formulario',
  // Formulas portadas sem alteracao da versao anterior.
  passos: [
 {
  "id": "codEquipamento",
  "grupo": "Identificação",
  "pergunta": "Código / Tag do equipamento",
  "tipo": "texto",
  "padrao": ""
 },
 {
  "id": "categoriaEquip",
  "grupo": "Identificação",
  "pergunta": "Categoria",
  "tipo": "opcoes",
  "opcoes": [
   {
    "valor": "escavadeira",
    "rotulo": "Escavadeira"
   },
   {
    "valor": "pacarreira",
    "rotulo": "Pá Carregadeira"
   },
   {
    "valor": "motoniveladora",
    "rotulo": "Motoniveladora"
   },
   {
    "valor": "rolo",
    "rotulo": "Rolo Compactador"
   },
   {
    "valor": "plataforma",
    "rotulo": "Plataforma Tesoura/Articulada"
   },
   {
    "valor": "guindaste",
    "rotulo": "Guindaste"
   },
   {
    "valor": "gerador",
    "rotulo": "Gerador"
   },
   {
    "valor": "compressor",
    "rotulo": "Compressor"
   },
   {
    "valor": "outro",
    "rotulo": "Outro"
   }
  ],
  "padrao": "escavadeira"
 },
 {
  "id": "marcaModelo",
  "grupo": "Identificação",
  "pergunta": "Marca / Modelo",
  "tipo": "texto",
  "padrao": ""
 },
 {
  "id": "anoFabricacao",
  "grupo": "Identificação",
  "pergunta": "Ano de fabricação",
  "tipo": "numero",
  "min": 1990,
  "max": 2030,
  "padrao": 2022
 },
 {
  "id": "custoAquisicao",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Custo de aquisição (R$)",
  "tipo": "numero",
  "min": 0,
  "padrao": 280000
 },
 {
  "id": "vidaUtilHoras",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Vida útil total estimada (horas)",
  "tipo": "numero",
  "min": 1000,
  "max": 50000,
  "padrao": 12000
 },
 {
  "id": "horasAtuais",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Horas atuais no hodômetro",
  "tipo": "numero",
  "min": 0,
  "max": 50000,
  "padrao": 3200
 },
 {
  "id": "valorRevendaAtual",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Valor de revenda estimado hoje (R$)",
  "tipo": "numero",
  "min": 0,
  "padrao": 180000
 },
 {
  "id": "diariaPraticada",
  "grupo": "Operação Mensal (Mês de Referência)",
  "pergunta": "Diária praticada (R$/8h)",
  "tipo": "numero",
  "min": 0,
  "padrao": 450
 },
 {
  "id": "diasAlugados",
  "grupo": "Operação Mensal (Mês de Referência)",
  "pergunta": "Dias alugados no mês",
  "tipo": "numero",
  "min": 0,
  "max": 30,
  "padrao": 18
 },
 {
  "id": "horasMes",
  "grupo": "Operação Mensal (Mês de Referência)",
  "pergunta": "Horas trabalhadas no mês",
  "tipo": "numero",
  "min": 0,
  "max": 720,
  "padrao": 144
 },
 {
  "id": "combustivelLitros",
  "grupo": "Operação Mensal (Mês de Referência)",
  "pergunta": "Consumo de combustível (L/mês)",
  "tipo": "numero",
  "min": 0,
  "padrao": 600
 },
 {
  "id": "precoDiesel",
  "grupo": "Operação Mensal (Mês de Referência)",
  "pergunta": "Preço do diesel (R$/L)",
  "tipo": "numero",
  "min": 0,
  "padrao": 6.15
 },
 {
  "id": "custoOperador",
  "grupo": "Operação Mensal (Mês de Referência)",
  "pergunta": "Operador dedicado? (custo/mês)",
  "tipo": "numero",
  "min": 0,
  "padrao": 4500
 },
 {
  "id": "manutPrevMensal",
  "grupo": "Custos Fixos Mensais",
  "pergunta": "Manutenção preventiva programada (R$/mês)",
  "tipo": "numero",
  "min": 0,
  "padrao": 800
 },
 {
  "id": "manutCorrMensal",
  "grupo": "Custos Fixos Mensais",
  "pergunta": "Manutenção corretiva média (R$/mês)",
  "tipo": "numero",
  "min": 0,
  "padrao": 1200
 },
 {
  "id": "seguroMensal",
  "grupo": "Custos Fixos Mensais",
  "pergunta": "Seguro (R$/mês)",
  "tipo": "numero",
  "min": 0,
  "padrao": 650
 },
 {
  "id": "rastreamentoMensal",
  "grupo": "Custos Fixos Mensais",
  "pergunta": "Rastreamento / Telemetria (R$/mês)",
  "tipo": "numero",
  "min": 0,
  "padrao": 150
 },
 {
  "id": "outrosFixosMensal",
  "grupo": "Custos Fixos Mensais",
  "pergunta": "Outros custos fixos (R$/mês)",
  "tipo": "numero",
  "min": 0,
  "padrao": 0
 },
 {
  "id": "custoCapital",
  "grupo": "Custo de Capital",
  "pergunta": "Custo de capital / WACC (% a.a.)",
  "tipo": "numero",
  "min": 0,
  "max": 50,
  "padrao": 14.5
 }
],
  calcular: function (v) {
    var f = OravelTool.fmt, brl2 = function (x) { return x.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
    if (v.horasAtuais > v.vidaUtilHoras) { return { titulo: 'Dados inconsistentes', resumo: 'As horas atuais não podem exceder a vida útil total.', indicadores: [] }; }
    var receitaMensal = v.diariaPraticada * v.diasAlugados;
    var custoCombustivel = v.combustivelLitros * v.precoDiesel;
    var custoOperador = v.custoOperador;
    var custosVariaveis = custoCombustivel + custoOperador;
    var margemBruta = receitaMensal - custosVariaveis;
    var custosFixos = v.manutPrevMensal + v.manutCorrMensal + v.seguroMensal + v.rastreamentoMensal + v.outrosFixosMensal;
    var horasRestantes = v.vidaUtilHoras - v.horasAtuais;
    var valorDepreciavel = v.custoAquisicao - v.valorRevendaAtual;
    var depreciacaoPorHora = horasRestantes > 0 ? valorDepreciavel / horasRestantes : 0;
    var depreciacaoMensal = depreciacaoPorHora * v.horasMes;
    var valorMedioAtivo = (v.custoAquisicao + v.valorRevendaAtual) / 2;
    var custoCapitalMensal = valorMedioAtivo * (v.custoCapital / 100) / 12;
    var margemLiquida = margemBruta - custosFixos - depreciacaoMensal - custoCapitalMensal;
    var margemLiquidaPct = receitaMensal > 0 ? (margemLiquida / receitaMensal) * 100 : 0;
    var caixaMensal = margemBruta - custosFixos;
    var paybackMeses = caixaMensal > 0 ? v.custoAquisicao / caixaMensal : Infinity;
    var roiAnual = v.custoAquisicao > 0 ? (margemLiquida * 12 / v.custoAquisicao) * 100 : 0;
    var vidaRestantePct = v.vidaUtilHoras > 0 ? (horasRestantes / v.vidaUtilHoras) * 100 : 0;

    var baseDias = v.diasAlugados, baseDiaria = v.diariaPraticada, baseManutCorr = v.manutCorrMensal;
    var cenarios = [
      { nome: 'Atual', dias: baseDias, diaria: baseDiaria, manutCorr: baseManutCorr },
      { nome: 'Otimista (+3 dias, +5% diária)', dias: Math.min(26, baseDias + 3), diaria: baseDiaria * 1.05, manutCorr: baseManutCorr * 0.9 },
      { nome: 'Conservador (-3 dias, -5% diária)', dias: Math.max(5, baseDias - 3), diaria: baseDiaria * 0.95, manutCorr: baseManutCorr * 1.15 },
      { nome: 'Alta utilização (22 dias)', dias: 22, diaria: baseDiaria, manutCorr: baseManutCorr * 1.1 },
      { nome: 'Diária de mercado (+10%)', dias: baseDias, diaria: baseDiaria * 1.10, manutCorr: baseManutCorr },
      { nome: 'Manut. preventiva rigorosa (-30% corr.)', dias: baseDias, diaria: baseDiaria, manutCorr: baseManutCorr * 0.7 }
    ];
    var linhas = cenarios.map(function (c) {
      var rec = c.diaria * c.dias;
      var comb = v.combustivelLitros * v.precoDiesel * (baseDias > 0 ? c.dias / baseDias : 1);
      var mb = rec - comb - v.custoOperador;
      var fix = v.manutPrevMensal + c.manutCorr + v.seguroMensal + v.rastreamentoMensal + v.outrosFixosMensal;
      var ml = mb - fix - depreciacaoMensal - custoCapitalMensal;
      var delta = margemLiquida !== 0 ? ((ml - margemLiquida) / Math.abs(margemLiquida)) * 100 : 0;
      return [c.nome, String(c.dias), f.brl(c.diaria), f.brl(c.manutCorr), f.brl(ml), (delta >= 0 ? '+' : '') + f.num(delta) + '%'];
    });

    var rec;
    if (margemLiquida > 0 && roiAnual > v.custoCapital) {
      rec = 'Manter. Equipamento rentável: retorno de ' + f.pct(roiAnual) + ' ao ano, acima do custo de capital de ' + f.pct(v.custoCapital) + '. Margem líquida de ' + f.brl(margemLiquida) + ' por mês. Payback em ' + f.num(paybackMeses) + ' meses.';
    } else if (margemLiquida > 0) {
      rec = 'Manter com atenção. Margem positiva (' + f.brl(margemLiquida) + ' por mês), mas retorno de ' + f.pct(roiAnual) + ' abaixo do custo de capital (' + f.pct(v.custoCapital) + '). Considere aumentar a diária, reduzir a manutenção corretiva ou vender se houver oportunidade.';
    } else if (vidaRestantePct > 30) {
      rec = 'Revisar com urgência. Margem negativa (' + f.brl(margemLiquida) + ' por mês) com ' + f.int(vidaRestantePct) + '% de vida restante. Renegocie a diária, audite a manutenção corretiva, avalie terceirizar o operador ou venda.';
    } else {
      rec = 'Vender ou substituir. Margem negativa (' + f.brl(margemLiquida) + ' por mês) e pouca vida restante (' + f.int(vidaRestantePct) + '%). Vale alocar o capital em equipamento mais rentável.';
    }
    var nome = v.codEquipamento ? v.codEquipamento + ': ' : '';
    return {
      titulo: nome + 'margem líquida de ' + brl2(margemLiquida) + ' por mês',
      resumo: rec,
      indicadores: [
        { rotulo: 'Margem bruta', valor: margemBruta, formato: 'brl' },
        { rotulo: 'Margem líquida', valor: margemLiquida, formato: 'brl' },
        { rotulo: '% margem líquida', valor: margemLiquidaPct, formato: 'pct' },
        { rotulo: 'Payback (meses)', valor: isFinite(paybackMeses) ? paybackMeses : null, formato: 'num', nota: isFinite(paybackMeses) ? '' : 'Fluxo de caixa não positivo' },
        { rotulo: 'Retorno anual (ROI)', valor: roiAnual, formato: 'pct' },
        { rotulo: 'Vida restante', valor: vidaRestantePct, formato: 'pct' }
      ],
      grafico: { titulo: 'Composição mensal', formato: 'brl', itens: [
        { rotulo: 'Receita', valor: receitaMensal }, { rotulo: 'Combustível', valor: custoCombustivel }, { rotulo: 'Operador', valor: custoOperador },
        { rotulo: 'Manutenção', valor: v.manutPrevMensal + v.manutCorrMensal }, { rotulo: 'Seguro e rastreamento', valor: v.seguroMensal + v.rastreamentoMensal },
        { rotulo: 'Outros fixos', valor: v.outrosFixosMensal }, { rotulo: 'Depreciação', valor: Math.max(depreciacaoMensal, 0) }, { rotulo: 'Custo de capital', valor: custoCapitalMensal }
      ] },
      tabela: { titulo: 'Cenários da margem líquida', colunas: ['Cenário', 'Dias', 'Diária', 'Manut. corretiva', 'Margem líquida', 'Variação'], linhas: linhas },
      conclusoes: [rec],
      sugestoes: ['Compare a margem deste equipamento com a de outros da mesma categoria antes de decidir vender ou manter.'],
      premissas: ['Depreciação linear por horas de uso; custo de capital sobre o valor médio do ativo.', 'Payback usa o fluxo de caixa (margem bruta menos custos fixos).']
    };
  }

});
