OravelTool.register({
  slug: 'roi-manutencao',
  modo: 'formulario',
  // Formulas portadas sem alteracao da versao anterior.
  passos: [
 {
  "id": "numEquip",
  "grupo": "Frota e Operação",
  "pergunta": "Nº de equipamentos na frota",
  "tipo": "numero",
  "min": 1,
  "padrao": 15
 },
 {
  "id": "horasMesEquip",
  "grupo": "Frota e Operação",
  "pergunta": "Horas trabalhadas/equip/mês",
  "tipo": "numero",
  "min": 0,
  "max": 720,
  "padrao": 180
 },
 {
  "id": "diariaMedia",
  "grupo": "Frota e Operação",
  "pergunta": "Diária média praticada (R$/8h)",
  "tipo": "numero",
  "min": 0,
  "padrao": 500
 },
 {
  "id": "taxaOcupMedia",
  "grupo": "Frota e Operação",
  "pergunta": "Taxa de ocupação média (%)",
  "tipo": "numero",
  "min": 0,
  "max": 100,
  "padrao": 70
 },
 {
  "id": "corrAtual",
  "grupo": "Custos Atuais de Manutenção (Anual)",
  "pergunta": "Manutenção corretiva total (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 450000
 },
 {
  "id": "prevAtual",
  "grupo": "Custos Atuais de Manutenção (Anual)",
  "pergunta": "Manutenção preventiva atual (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 120000
 },
 {
  "id": "pecasEmergAtual",
  "grupo": "Custos Atuais de Manutenção (Anual)",
  "pergunta": "Peças de reposição emergenciais (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 180000
 },
 {
  "id": "maoObraEmergAtual",
  "grupo": "Custos Atuais de Manutenção (Anual)",
  "pergunta": "Mão de obra terceirizada emergencial (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 90000
 },
 {
  "id": "falhasAnoAtual",
  "grupo": "Custos Atuais de Manutenção (Anual)",
  "pergunta": "Nº de falhas/ano (frota total)",
  "tipo": "numero",
  "min": 0,
  "padrao": 60
 },
 {
  "id": "mttrAtual",
  "grupo": "Custos Atuais de Manutenção (Anual)",
  "pergunta": "MTTR médio atual (h/falha)",
  "tipo": "numero",
  "min": 0,
  "padrao": 8
 },
 {
  "id": "capexInicial",
  "grupo": "Investimento no Programa",
  "pergunta": "CAPEX inicial (R$) — ferramentas, sensores, software, treinamento",
  "tipo": "numero",
  "min": 0,
  "padrao": 150000
 },
 {
  "id": "opexAnual",
  "grupo": "Investimento no Programa",
  "pergunta": "OPEX anual do programa (R$/ano) — contratos, licenças, equipe dedicada",
  "tipo": "numero",
  "min": 0,
  "padrao": 80000
 },
 {
  "id": "vidaPrograma",
  "grupo": "Investimento no Programa",
  "pergunta": "Vida útil do programa (anos)",
  "tipo": "numero",
  "min": 1,
  "max": 10,
  "padrao": 5
 },
 {
  "id": "redFalhas",
  "grupo": "Ganhos Esperados (Estimativas Conservadoras)",
  "pergunta": "Redução de falhas corretivas (%)",
  "tipo": "numero",
  "min": 0,
  "max": 90,
  "padrao": 45
 },
 {
  "id": "redMttr",
  "grupo": "Ganhos Esperados (Estimativas Conservadoras)",
  "pergunta": "Redução MTTR (%)",
  "tipo": "numero",
  "min": 0,
  "max": 70,
  "padrao": 30
 },
 {
  "id": "redPecas",
  "grupo": "Ganhos Esperados (Estimativas Conservadoras)",
  "pergunta": "Redução peças emergenciais (%)",
  "tipo": "numero",
  "min": 0,
  "max": 80,
  "padrao": 40
 },
 {
  "id": "redMaoObra",
  "grupo": "Ganhos Esperados (Estimativas Conservadoras)",
  "pergunta": "Redução mão de obra emergencial (%)",
  "tipo": "numero",
  "min": 0,
  "max": 80,
  "padrao": 35
 },
 {
  "id": "aumVidaUtil",
  "grupo": "Ganhos Esperados (Estimativas Conservadoras)",
  "pergunta": "Aumento vida útil equipamentos (%)",
  "tipo": "numero",
  "min": 0,
  "max": 50,
  "padrao": 15
 },
 {
  "id": "valorMedioEquip",
  "grupo": "Ganhos Esperados (Estimativas Conservadoras)",
  "pergunta": "Valor médio por equipamento (R$)",
  "tipo": "numero",
  "min": 0,
  "padrao": 300000
 },
 {
  "id": "waccRoi",
  "grupo": "Ganhos Esperados (Estimativas Conservadoras)",
  "pergunta": "Custo de capital / WACC (% a.a.)",
  "tipo": "numero",
  "min": 0,
  "max": 50,
  "padrao": 14.5
 }
],
  calcular: function (v) {
    var f = OravelTool.fmt;
    var economiaCorretiva = v.corrAtual * (v.redFalhas / 100);
    var economiaPecas = v.pecasEmergAtual * (v.redPecas / 100);
    var economiaMaoObra = v.maoObraEmergAtual * (v.redMaoObra / 100);
    var economiaTotalBruta = economiaCorretiva + economiaPecas + economiaMaoObra;
    var economiaLiquidaAnual = economiaTotalBruta - v.opexAnual;
    var horasParadasAtualAno = v.falhasAnoAtual * v.mttrAtual;
    var falhasNovo = v.falhasAnoAtual * (1 - v.redFalhas / 100);
    var mttrNovo = v.mttrAtual * (1 - v.redMttr / 100);
    var horasParadasNovaAno = falhasNovo * mttrNovo;
    var horasRecuperadasAno = horasParadasAtualAno - horasParadasNovaAno;
    var receitaHora = v.diariaMedia / 8;
    var receitaAdicionalAnual = horasRecuperadasAno * receitaHora * (v.taxaOcupMedia / 100) * v.numEquip;
    var valorFrota = v.numEquip * (v.valorMedioEquip || 0);
    var valorVidaUtilAnual = valorFrota * (v.aumVidaUtil / 100) / v.vidaPrograma;
    var beneficioTotalAnual = economiaLiquidaAnual + receitaAdicionalAnual + valorVidaUtilAnual;
    var paybackMeses = beneficioTotalAnual > 0 ? (v.capexInicial / beneficioTotalAnual) * 12 : Infinity;
    var roiAnual = v.capexInicial > 0 ? (beneficioTotalAnual / v.capexInicial) * 100 : 0;
    var npv = -v.capexInicial, fluxoAcumulado = -v.capexInicial, fluxo = [];
    for (var ano = 1; ano <= v.vidaPrograma; ano++) {
      var vp = beneficioTotalAnual / Math.pow(1 + v.waccRoi / 100, ano);
      fluxoAcumulado += beneficioTotalAnual; npv += vp;
      fluxo.push([String(ano), f.brl(ano === 1 ? v.capexInicial : 0), f.brl(v.opexAnual), f.brl(economiaTotalBruta + receitaAdicionalAnual + valorVidaUtilAnual), f.brl(beneficioTotalAnual), f.brl(fluxoAcumulado), f.brl(vp)]);
    }
    var horasTotalAno = v.numEquip * v.horasMesEquip * 12;
    var dispAtual = (horasTotalAno - horasParadasAtualAno) / horasTotalAno * 100;
    var dispNova = (horasTotalAno - horasParadasNovaAno) / horasTotalAno * 100;
    var ganhoDisp = dispNova - dispAtual;
    var falhasEvitadas = v.falhasAnoAtual - falhasNovo;
    var cenarios = [
      { nome: 'Base', rf: v.redFalhas, rm: v.redMttr, capex: v.capexInicial, opex: v.opexAnual },
      { nome: 'Conservador (-10pp falhas, -5pp MTTR)', rf: Math.max(0, v.redFalhas - 10), rm: Math.max(0, v.redMttr - 5), capex: v.capexInicial, opex: v.opexAnual },
      { nome: 'Otimista (+10pp falhas, +5pp MTTR)', rf: Math.min(100, v.redFalhas + 10), rm: Math.min(100, v.redMttr + 5), capex: v.capexInicial, opex: v.opexAnual },
      { nome: 'CAPEX +30%', rf: v.redFalhas, rm: v.redMttr, capex: v.capexInicial * 1.3, opex: v.opexAnual },
      { nome: 'OPEX +50%', rf: v.redFalhas, rm: v.redMttr, capex: v.capexInicial, opex: v.opexAnual * 1.5 },
      { nome: 'WACC +3pp', rf: v.redFalhas, rm: v.redMttr, capex: v.capexInicial, opex: v.opexAnual, wacc: v.waccRoi + 3 }
    ];
    var linhas = cenarios.map(function (c) {
      var wacc = c.wacc || v.waccRoi;
      var ecBruta = v.corrAtual * (c.rf / 100) + v.pecasEmergAtual * (v.redPecas / 100) + v.maoObraEmergAtual * (v.redMaoObra / 100);
      var ecLiq = ecBruta - c.opex;
      var falhasN = v.falhasAnoAtual * (1 - c.rf / 100), mttrN = v.mttrAtual * (1 - c.rm / 100);
      var hrRec = v.falhasAnoAtual * v.mttrAtual - falhasN * mttrN;
      var recAd = hrRec * receitaHora * (v.taxaOcupMedia / 100) * v.numEquip;
      var valVU = valorFrota * (v.aumVidaUtil / 100) / v.vidaPrograma;
      var benTotal = ecLiq + recAd + valVU;
      var payb = benTotal > 0 ? (c.capex / benTotal) * 12 : Infinity;
      var npvC = -c.capex;
      for (var a = 1; a <= v.vidaPrograma; a++) npvC += benTotal / Math.pow(1 + wacc / 100, a);
      return [c.nome, f.int(c.rf) + '%', f.int(c.rm) + '%', f.brl(c.capex), f.brl(c.opex), f.brl(npvC), isFinite(payb) ? f.num(payb) + ' m' : '—'];
    });
    var positivo = npv > 0;
    return {
      titulo: positivo ? 'O programa se paga: VPL de ' + f.brl(npv) : 'O programa não se paga no prazo: VPL de ' + f.brl(npv),
      resumo: 'Benefício total de ' + f.brl(beneficioTotalAnual) + ' por ano para um investimento inicial de ' + f.brl(v.capexInicial) + ', com payback de ' + (isFinite(paybackMeses) ? f.num(paybackMeses) + ' meses' : 'prazo indefinido') + '.',
      indicadores: [
        { rotulo: 'Benefício total anual', valor: beneficioTotalAnual, formato: 'brl' },
        { rotulo: 'Payback (meses)', valor: isFinite(paybackMeses) ? paybackMeses : null, formato: 'num', nota: isFinite(paybackMeses) ? '' : 'Benefício anual não positivo' },
        { rotulo: 'VPL (valor presente líquido)', valor: npv, formato: 'brl' },
        { rotulo: 'ROI anualizado', valor: roiAnual, formato: 'pct' },
        { rotulo: 'Ganho de disponibilidade (pp)', valor: ganhoDisp, formato: 'num' },
        { rotulo: 'Falhas evitadas por ano', valor: falhasEvitadas, formato: 'int' }
      ],
      grafico: { titulo: 'Composição do benefício anual', formato: 'brl', itens: [
        { rotulo: 'Economia corretiva', valor: economiaCorretiva }, { rotulo: 'Peças emergenciais', valor: economiaPecas }, { rotulo: 'Mão de obra emerg.', valor: economiaMaoObra },
        { rotulo: 'Receita adicional', valor: Math.max(receitaAdicionalAnual, 0) }, { rotulo: 'Vida útil estendida', valor: Math.max(valorVidaUtilAnual, 0) }, { rotulo: 'OPEX do programa', valor: v.opexAnual }
      ] },
      tabela: { titulo: 'Fluxo de caixa do programa', colunas: ['Ano', 'CAPEX', 'OPEX', 'Economia bruta', 'Benefício líquido', 'Acumulado', 'Valor presente'], linhas: fluxo },
      conclusoes: ['Economia líquida de manutenção: ' + f.brl(economiaLiquidaAnual) + ' por ano (depois do OPEX).'],
      sugestoes: ['Teste os cenários abaixo: se o VPL fica negativo com resultados menores, o projeto depende de premissas otimistas.', 'Valide as reduções de falhas e de MTTR com um piloto em poucos equipamentos.'],
      premissas: ['O benefício anual é considerado constante em todos os anos do programa.', 'Valor da extensão da vida útil = valor da frota × aumento ÷ vida do programa (simplificação).', 'Estimativas de redução são hipóteses suas, não resultados garantidos.']
      ,tabela2: { titulo: 'Sensibilidade', colunas: ['Cenário', 'Red. falhas', 'Red. MTTR', 'CAPEX', 'OPEX', 'VPL', 'Payback'], linhas: linhas }
    };
  }

});
