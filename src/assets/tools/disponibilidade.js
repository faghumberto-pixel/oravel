OravelTool.register({
  slug: 'disponibilidade',
  modo: 'formulario',
  // Formulas portadas sem alteracao da versao anterior.
  passos: [
 {
  "id": "codEquipDisp",
  "grupo": "Identificação",
  "pergunta": "Código / Tag",
  "tipo": "texto",
  "padrao": ""
 },
 {
  "id": "categoriaDisp",
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
  "id": "qtdEquipamentos",
  "grupo": "Identificação",
  "pergunta": "Quantidade de equipamentos (se frota)",
  "tipo": "numero",
  "min": 1,
  "padrao": 1
 },
 {
  "id": "horasPeriodo",
  "grupo": "Período de Análise",
  "pergunta": "Horas no período (h)",
  "tipo": "numero",
  "min": 1,
  "max": 744,
  "padrao": 720
 },
 {
  "id": "numFalhas",
  "grupo": "Falhas e Reparos (MTBF / MTTR)",
  "pergunta": "Número de falhas no período",
  "tipo": "numero",
  "min": 0,
  "padrao": 3
 },
 {
  "id": "tempoTotalReparo",
  "grupo": "Falhas e Reparos (MTBF / MTTR)",
  "pergunta": "Tempo total de reparo (h) — soma de todos MTTR",
  "tipo": "numero",
  "min": 0,
  "padrao": 18
 },
 {
  "id": "mttrInput",
  "grupo": "Falhas e Reparos (MTBF / MTTR)",
  "pergunta": "Tempo médio para reparo — MTTR (h) (opcional, calculado se vazio)",
  "tipo": "numero",
  "min": 0,
  "opcional": true
 },
 {
  "id": "mtbfInput",
  "grupo": "Falhas e Reparos (MTBF / MTTR)",
  "pergunta": "Tempo médio entre falhas — MTBF (h) (opcional, calculado se vazio)",
  "tipo": "numero",
  "min": 0,
  "opcional": true
 },
 {
  "id": "tempPrev",
  "grupo": "Tempos Parados Não-Falha (h no período)",
  "pergunta": "Manutenção preventiva programada",
  "tipo": "numero",
  "min": 0,
  "padrao": 8
 },
 {
  "id": "tempInsp",
  "grupo": "Tempos Parados Não-Falha (h no período)",
  "pergunta": "Inspeções / Vistorias obrigatórias",
  "tipo": "numero",
  "min": 0,
  "padrao": 4
 },
 {
  "id": "tempLog",
  "grupo": "Tempos Parados Não-Falha (h no período)",
  "pergunta": "Logística / Transporte (ida/volta oficina)",
  "tipo": "numero",
  "min": 0,
  "padrao": 6
 },
 {
  "id": "tempPeca",
  "grupo": "Tempos Parados Não-Falha (h no período)",
  "pergunta": "Aguardando peça / Fornecedor",
  "tipo": "numero",
  "min": 0,
  "padrao": 12
 },
 {
  "id": "tempOp",
  "grupo": "Tempos Parados Não-Falha (h no período)",
  "pergunta": "Aguardando operador / Técnico",
  "tipo": "numero",
  "min": 0,
  "padrao": 3
 },
 {
  "id": "tempOutros",
  "grupo": "Tempos Parados Não-Falha (h no período)",
  "pergunta": "Outros paradas administrativas",
  "tipo": "numero",
  "min": 0,
  "padrao": 2
 },
 {
  "id": "diariaDisp",
  "grupo": "Impacto Financeiro",
  "pergunta": "Diária praticada (R$/8h)",
  "tipo": "numero",
  "min": 0,
  "padrao": 450
 },
 {
  "id": "taxaOcupacao",
  "grupo": "Impacto Financeiro",
  "pergunta": "Taxa de ocupação média (%)",
  "tipo": "numero",
  "min": 0,
  "max": 100,
  "padrao": 75
 },
 {
  "id": "custoHoraParado",
  "grupo": "Impacto Financeiro",
  "pergunta": "Custo hora parado (R$/h) — opcional",
  "tipo": "numero",
  "min": 0,
  "opcional": true
 }
],
  calcular: function (v) {
    var f = OravelTool.fmt, h = function (x) { return f.num(x) + ' h'; };
    var tem = function (x) { return x != null && !isNaN(x); };
    var mttr = tem(v.mttrInput) ? v.mttrInput : (v.numFalhas > 0 ? v.tempoTotalReparo / v.numFalhas : 0);
    var mtbf = tem(v.mtbfInput) ? v.mtbfInput : (v.numFalhas > 0 ? (v.horasPeriodo - v.tempoTotalReparo) / v.numFalhas : v.horasPeriodo);
    var tempoFalha = v.numFalhas * mttr;
    var tempoPrev = v.tempPrev || 0, tempoInsp = v.tempInsp || 0, tempoLog = v.tempLog || 0, tempoPeca = v.tempPeca || 0, tempoOp = v.tempOp || 0, tempoOutros = v.tempOutros || 0;
    var totalParado = tempoFalha + tempoPrev + tempoInsp + tempoLog + tempoPeca + tempoOp + tempoOutros;
    var horasDisponiveis = Math.max(0, v.horasPeriodo - totalParado);
    var dispFisica = v.horasPeriodo > 0 ? (horasDisponiveis / v.horasPeriodo) * 100 : 0;
    var dispOper = dispFisica * (v.taxaOcupacao / 100);
    var confiabilidade = mtbf > 0 ? Math.exp(-100 / mtbf) * 100 : 100;
    var custoHora = tem(v.custoHoraParado) ? v.custoHoraParado : (v.diariaDisp / 8);
    var qtd = v.qtdEquipamentos || 1;
    var receitaPerdida = totalParado * custoHora * (v.taxaOcupacao / 100) * qtd;

    var baseFalhas = v.numFalhas, baseMttr = mttr, basePrev = tempoPrev + tempoInsp, baseLogPecaOp = tempoLog + tempoPeca + tempoOp, baseOutros = tempoOutros;
    var cenarios = [
      { nome: 'Atual', falhas: baseFalhas, mttr: baseMttr, prev: basePrev, log: baseLogPecaOp, outros: baseOutros },
      { nome: 'Melhoria preventiva (-30% falhas)', falhas: Math.max(0, baseFalhas * 0.7), mttr: baseMttr, prev: basePrev * 1.1, log: baseLogPecaOp, outros: baseOutros },
      { nome: 'Oficina própria (-40% MTTR)', falhas: baseFalhas, mttr: baseMttr * 0.6, prev: basePrev, log: baseLogPecaOp * 0.5, outros: baseOutros },
      { nome: 'Gestão de peças (-50% espera peça)', falhas: baseFalhas, mttr: baseMttr, prev: basePrev, log: baseLogPecaOp - tempoPeca * 0.5, outros: baseOutros },
      { nome: 'Excelência (meta ambiciosa)', falhas: baseFalhas * 0.5, mttr: baseMttr * 0.5, prev: basePrev * 0.8, log: baseLogPecaOp * 0.3, outros: baseOutros * 0.5 }
    ];
    var linhas = cenarios.map(function (c) {
      var tp = c.falhas * c.mttr + c.prev + c.log + c.outros;
      var hd = Math.max(0, v.horasPeriodo - tp);
      var df = v.horasPeriodo > 0 ? (hd / v.horasPeriodo) * 100 : 0;
      var rp = tp * custoHora * (v.taxaOcupacao / 100) * qtd;
      return [c.nome, f.num(c.falhas), f.num(c.mttr), f.num(c.prev), f.num(c.log + c.outros), f.pct(df), f.brl(rp)];
    });
    var pareto = [
      { rotulo: 'Aguardando peça', valor: tempoPeca }, { rotulo: 'Falhas (reparo)', valor: tempoFalha }, { rotulo: 'Logística/transporte', valor: tempoLog },
      { rotulo: 'Preventiva e inspeção', valor: tempoPrev + tempoInsp }, { rotulo: 'Aguardando operador', valor: tempoOp }, { rotulo: 'Outros', valor: tempoOutros }
    ].filter(function (d) { return d.valor > 0; }).sort(function (a, b) { return b.valor - a.valor; });
    var maior = pareto[0];
    return {
      titulo: (v.codEquipDisp ? v.codEquipDisp + ': ' : '') + 'disponibilidade física de ' + f.pct(dispFisica),
      resumo: 'Foram ' + h(totalParado) + ' paradas em ' + h(v.horasPeriodo) + '. ' + (maior ? 'A maior parcela é "' + maior.rotulo.toLowerCase() + '" (' + h(maior.valor) + ').' : ''),
      indicadores: [
        { rotulo: 'Disponibilidade física', valor: dispFisica, formato: 'pct' },
        { rotulo: 'Disponibilidade operacional', valor: dispOper, formato: 'pct', nota: 'Física × taxa de ocupação' },
        { rotulo: 'Receita perdida no período', valor: receitaPerdida, formato: 'brl' },
        { rotulo: 'MTBF (h)', valor: mtbf, formato: 'num' },
        { rotulo: 'MTTR (h)', valor: mttr, formato: 'num' },
        { rotulo: 'Horas paradas', valor: totalParado, formato: 'num' },
        { rotulo: 'Confiabilidade em 100 h', valor: confiabilidade, formato: 'pct' }
      ],
      grafico: { titulo: 'Horas paradas por causa (maior para menor)', formato: 'num', itens: pareto },
      tabela: { titulo: 'Cenários de melhoria', colunas: ['Cenário', 'Falhas', 'MTTR (h)', 'Prev. + insp. (h)', 'Logística + outros (h)', 'Disp. física', 'Receita perdida'], linhas: linhas },
      conclusoes: ['Horas disponíveis para locar no período: ' + h(horasDisponiveis) + '.'],
      sugestoes: [maior ? 'Comece por "' + maior.rotulo.toLowerCase() + '", a maior causa de parada.' : 'Registre as causas de parada para priorizar.', 'Registre as horas paradas por causa todo mês, com o mesmo critério, para acompanhar a evolução.'],
      premissas: ['Receita perdida = horas paradas × custo por hora × taxa de ocupação × quantidade de equipamentos.', 'Confiabilidade = exp(-100 / MTBF). Cenários são hipóteses de melhoria, não projeções.', 'A versão anterior mostrava percentis de mercado por categoria; eram valores simulados e foram removidos.']
    };
  }

});
