OravelTool.register({
  slug: 'viabilidade-frota',
  modo: 'formulario',
  // Formulas portadas sem alteracao da versao anterior.
  passos: [
 {
  "id": "catFrota",
  "grupo": "Equipamento e Quantidade",
  "pergunta": "Categoria",
  "tipo": "opcoes",
  "opcoes": [
   {
    "valor": "escavadeira",
    "rotulo": "Escavadeira 20-25t"
   },
   {
    "valor": "pacarreira",
    "rotulo": "Pá Carregadeira 3-4m³"
   },
   {
    "valor": "motoniveladora",
    "rotulo": "Motoniveladora 140-160hp"
   },
   {
    "valor": "rolo",
    "rotulo": "Rolo Compactador 10-12t"
   },
   {
    "valor": "plataforma",
    "rotulo": "Plataforma Tesoura 10-12m"
   },
   {
    "valor": "guindaste",
    "rotulo": "Guindaste 30-50t"
   },
   {
    "valor": "gerador",
    "rotulo": "Gerador 100-200kVA"
   },
   {
    "valor": "compressor",
    "rotulo": "Compressor 150-250pcm"
   },
   {
    "valor": "outro",
    "rotulo": "Outro"
   }
  ],
  "padrao": "escavadeira"
 },
 {
  "id": "qtdUnidades",
  "grupo": "Equipamento e Quantidade",
  "pergunta": "Quantidade de unidades",
  "tipo": "numero",
  "min": 1,
  "padrao": 3
 },
 {
  "id": "modalidadeFrota",
  "grupo": "Equipamento e Quantidade",
  "pergunta": "Modalidade",
  "tipo": "opcoes",
  "opcoes": [
   {
    "valor": "compra",
    "rotulo": "Compra à vista"
   },
   {
    "valor": "financiamento",
    "rotulo": "Financiamento (CDC/Finame)"
   },
   {
    "valor": "consorcio",
    "rotulo": "Consórcio"
   },
   {
    "valor": "aluguel",
    "rotulo": "Aluguel (Rental) longo prazo"
   },
   {
    "valor": "sale-leaseback",
    "rotulo": "Sale & Leaseback"
   }
  ],
  "padrao": "compra"
 },
 {
  "id": "precoUnit",
  "grupo": "Valores Unitários",
  "pergunta": "Preço unitário (R$)",
  "tipo": "numero",
  "min": 0,
  "padrao": 350000
 },
 {
  "id": "vrUnit",
  "grupo": "Valores Unitários",
  "pergunta": "Valor residual unitário estimado (R$)",
  "tipo": "numero",
  "min": 0,
  "padrao": 60000
 },
 {
  "id": "diariaMercado",
  "grupo": "Valores Unitários",
  "pergunta": "Diária de aluguel mercado (R$/8h)",
  "tipo": "numero",
  "min": 0,
  "padrao": 500
 },
 {
  "id": "custoOpUnit",
  "grupo": "Valores Unitários",
  "pergunta": "Custo operacional unitário/ano (R$)",
  "tipo": "numero",
  "min": 0,
  "padrao": 85000
 },
 {
  "id": "hrsAnoUnit",
  "grupo": "Demanda e Precificação",
  "pergunta": "Horas trabalhadas/unidade/ano",
  "tipo": "numero",
  "min": 500,
  "max": 4000,
  "padrao": 1800
 },
 {
  "id": "taxOcupExp",
  "grupo": "Demanda e Precificação",
  "pergunta": "Taxa de ocupação esperada (%)",
  "tipo": "numero",
  "min": 0,
  "max": 100,
  "padrao": 75
 },
 {
  "id": "diariaPropria",
  "grupo": "Demanda e Precificação",
  "pergunta": "Diária praticada própria (R$/8h)",
  "tipo": "numero",
  "min": 0,
  "padrao": 550
 },
 {
  "id": "anosProjeto",
  "grupo": "Demanda e Precificação",
  "pergunta": "Anos de projeto",
  "tipo": "numero",
  "min": 1,
  "max": 10,
  "padrao": 5
 },
 {
  "id": "entradaPct",
  "grupo": "Estrutura de Financiamento (se aplicável)",
  "pergunta": "Entrada (%)",
  "tipo": "numero",
  "min": 0,
  "max": 100,
  "padrao": 30
 },
 {
  "id": "taxaFinFrota",
  "grupo": "Estrutura de Financiamento (se aplicável)",
  "pergunta": "Taxa (% a.a.)",
  "tipo": "numero",
  "min": 0,
  "max": 30,
  "padrao": 12.5
 },
 {
  "id": "prazoFinFrota",
  "grupo": "Estrutura de Financiamento (se aplicável)",
  "pergunta": "Prazo (meses)",
  "tipo": "numero",
  "min": 1,
  "max": 120,
  "padrao": 60
 },
 {
  "id": "carenciaFrota",
  "grupo": "Estrutura de Financiamento (se aplicável)",
  "pergunta": "Carência (meses)",
  "tipo": "numero",
  "min": 0,
  "max": 24,
  "padrao": 0
 },
 {
  "id": "waccFrota",
  "grupo": "Parâmetros Financeiros",
  "pergunta": "WACC / Custo de capital (% a.a.)",
  "tipo": "numero",
  "min": 0,
  "max": 50,
  "padrao": 14.5
 },
 {
  "id": "irEfetivo",
  "grupo": "Parâmetros Financeiros",
  "pergunta": "Imposto de renda/CSLL efetivo (%)",
  "tipo": "numero",
  "min": 0,
  "max": 34,
  "padrao": 15
 },
 {
  "id": "deprFiscal",
  "grupo": "Parâmetros Financeiros",
  "pergunta": "Depreciação fiscal (anos)",
  "tipo": "numero",
  "min": 1,
  "max": 20,
  "padrao": 10
 },
 {
  "id": "dscrMin",
  "grupo": "Parâmetros Financeiros",
  "pergunta": "DSCR mínimo aceitável",
  "tipo": "numero",
  "min": 1,
  "max": 3,
  "padrao": 1.3
 },
 {
  "id": "taxaAdmConsorcio",
  "grupo": "Parâmetros Financeiros",
  "pergunta": "Taxa de administração do consórcio (% total)",
  "tipo": "numero",
  "min": 0,
  "max": 50,
  "padrao": 18
 },
 {
  "id": "custoOpAluguelPct",
  "grupo": "Parâmetros Financeiros",
  "pergunta": "Custo operacional que permanece no aluguel (%)",
  "tipo": "numero",
  "min": 0,
  "max": 100,
  "padrao": 60
 },
 {
  "id": "reajusteLeaseback",
  "grupo": "Parâmetros Financeiros",
  "pergunta": "Acréscimo do aluguel no sale-leaseback (%)",
  "tipo": "numero",
  "min": 0,
  "max": 100,
  "padrao": 15
 }
],
  calcular: function (v) {
    var f = OravelTool.fmt;
    var pmt = function (rate, nper, pv) { if (rate === 0) return pv / nper; var r = rate / 100 / 12; return pv * r * Math.pow(1 + r, nper) / (Math.pow(1 + r, nper) - 1); };
    var dscrTxt = function (d) { return d >= 99 ? 'sem dívida' : f.num(d); };

    function calcularModalidade(v, modalidade) {
      var qtd = v.qtdUnidades, preco = v.precoUnit, vr = v.vrUnit, custoOp = v.custoOpUnit, hrsAno = v.hrsAnoUnit;
      var ocup = v.taxOcupExp / 100, diaria = v.diariaPropria, anos = v.anosProjeto, wacc = v.waccFrota / 100, ir = v.irEfetivo / 100, deprAnos = v.deprFiscal;
      var investInicial = 0, fluxoFinanc = [];
      if (modalidade === 'compra') {
        investInicial = preco * qtd;
      } else if (modalidade === 'financiamento') {
        var financiado = preco * qtd * (1 - v.entradaPct / 100);
        investInicial = preco * qtd * (v.entradaPct / 100);
        var parcelaMensal = pmt(v.taxaFinFrota, v.prazoFinFrota, financiado), parcelaAnual = parcelaMensal * 12, saldo = financiado;
        for (var a = 1; a <= anos; a++) {
          var princ = 0, juros = 0;
          if (a > v.carenciaFrota / 12 && saldo > 0) { juros = saldo * (v.taxaFinFrota / 100); princ = Math.min(parcelaAnual - juros, saldo); saldo -= princ; }
          fluxoFinanc[a] = { princ: princ, juros: juros, total: princ + juros };
        }
      } else if (modalidade === 'consorcio') {
        var totalConsorcio = preco * qtd * (1 + v.taxaAdmConsorcio / 100);
        var parcelaAnualC = totalConsorcio / (v.prazoFinFrota || 80) * 12;
        investInicial = 0;
        for (var a1 = 1; a1 <= anos; a1++) fluxoFinanc[a1] = { princ: parcelaAnualC, juros: 0, total: parcelaAnualC };
      } else if (modalidade === 'aluguel') {
        investInicial = 0;
        var custoAluguelAnual = (v.diariaMercado / 8) * hrsAno * qtd;
        for (var a2 = 1; a2 <= anos; a2++) fluxoFinanc[a2] = { princ: 0, juros: 0, total: custoAluguelAnual };
        custoOp = v.custoOpUnit * (v.custoOpAluguelPct / 100);
      } else if (modalidade === 'sale-leaseback') {
        investInicial = -vr * qtd;
        var custoAluguelSL = (v.diariaMercado / 8) * hrsAno * qtd * (1 + v.reajusteLeaseback / 100);
        for (var a3 = 1; a3 <= anos; a3++) fluxoFinanc[a3] = { princ: 0, juros: 0, total: custoAluguelSL };
        custoOp = v.custoOpUnit * (v.custoOpAluguelPct / 100);
      }
      var receitaAnual = (diaria / 8) * hrsAno * ocup * qtd;
      var custoOpAnual = custoOp * qtd;
      var temAtivo = modalidade === 'compra' || modalidade === 'financiamento' || modalidade === 'consorcio';
      var depreciacaoAnual = temAtivo ? (preco * qtd - vr * qtd) / deprAnos : 0;
      var vpl = -investInicial, tirFluxo = [investInicial], fluxoAnual = [], vplAcum = -investInicial, dscrSum = 0, dscrCount = 0;
      for (var b = 1; b <= anos; b++) {
        var fin = fluxoFinanc[b] || { total: 0, juros: 0, princ: 0 };
        var ebitda = receitaAnual - custoOpAnual;
        var dedutivel = depreciacaoAnual + (modalidade === 'financiamento' ? fin.juros : 0) + (modalidade === 'aluguel' || modalidade === 'sale-leaseback' ? fin.total : 0);
        var ebit = ebitda - dedutivel;
        var irValor = Math.max(0, ebit) * ir;
        var fcl = ebitda - fin.total - irValor;
        var vpFcl = fcl / Math.pow(1 + wacc, b);
        vpl += vpFcl; vplAcum += vpFcl;
        tirFluxo.push(-fcl);
        var dscr = fin.total > 0 ? ebitda / fin.total : 99;
        if (fin.total > 0) { dscrSum += dscr; dscrCount++; }
        fluxoAnual.push({ ano: b, receita: receitaAnual, custoOp: custoOpAnual, finTotal: fin.total, depreciacao: depreciacaoAnual, ebitda: ebitda, ir: irValor, fcl: fcl, vpFcl: vpFcl, vplAcum: vplAcum });
      }
      var vpVr = temAtivo ? (vr * qtd) / Math.pow(1 + wacc, anos) : 0;
      vpl += vpVr;
      if (temAtivo) tirFluxo[tirFluxo.length - 1] -= vr * qtd;
      var low = -0.5, high = 3.0;
      for (var i = 0; i < 60; i++) {
        var mid = (low + high) / 2, npv = 0;
        for (var t = 0; t < tirFluxo.length; t++) npv += tirFluxo[t] / Math.pow(1 + mid, t);
        if (npv > 0) high = mid; else low = mid;
      }
      var tir = (low + high) / 2;
      var payback = anos;
      for (var p = 0; p < fluxoAnual.length; p++) {
        if (fluxoAnual[p].vplAcum >= 0) { payback = p + Math.max(0, -(fluxoAnual[p].vplAcum - fluxoAnual[p].vpFcl) / fluxoAnual[p].vpFcl); break; }
      }
      var dscrMedio = dscrCount > 0 ? dscrSum / dscrCount : 99;
      var fclMedio = fluxoAnual.reduce(function (s, x) { return s + x.fcl; }, 0) / anos;
      var pontoEquil = custoOpAnual > 0 ? (custoOpAnual + ((fluxoFinanc[1] && fluxoFinanc[1].total) || 0)) / ((diaria / 8) * hrsAno * qtd) * 100 : 0;
      var tirOk = investInicial > 0 ? tir > wacc : true;
      var status;
      if (vpl > 0 && tirOk && dscrMedio >= v.dscrMin) status = 'Viável. VPL positivo, TIR acima do WACC e DSCR adequado: o projeto cria valor.';
      else if (vpl > 0 && tirOk) status = 'Viável com ressalva. VPL e TIR estão ok, mas o DSCR (' + dscrTxt(dscrMedio) + ') está abaixo do mínimo (' + f.num(v.dscrMin) + '). Requer financiamento mais longo ou entrada maior.';
      else if (vpl > 0) status = 'Marginal. VPL positivo, mas TIR (' + f.pct(tir * 100) + ') abaixo do WACC (' + f.pct(wacc * 100) + '): destrói valor econômico. Considere apenas se for estratégico.';
      else status = 'Não viável. VPL negativo (' + f.brl(vpl) + '): o projeto destrói valor nas condições atuais.';
      return { vpl: vpl, tir: tir, tirOk: tirOk, semTir: investInicial <= 0, payback: payback, dscrMedio: dscrMedio, fclMedio: fclMedio, pontoEquil: pontoEquil, investInicial: investInicial, fluxoAnual: fluxoAnual, status: status, receitaAnual: receitaAnual, custoOpAnual: custoOpAnual };
    }

    var res = calcularModalidade(v, v.modalidadeFrota);
    var cenarios = [
      { nome: 'Base', ocup: v.taxOcupExp, diaria: v.diariaPropria, custoOp: 1, wacc: v.waccFrota },
      { nome: 'Otimista (+10pp ocup, +5% diária)', ocup: Math.min(95, v.taxOcupExp + 10), diaria: v.diariaPropria * 1.05, custoOp: 0.95, wacc: v.waccFrota },
      { nome: 'Conservador (-10pp ocup, -5% diária)', ocup: Math.max(40, v.taxOcupExp - 10), diaria: v.diariaPropria * 0.95, custoOp: 1.1, wacc: v.waccFrota },
      { nome: 'Baixa demanda (-20pp ocup)', ocup: Math.max(30, v.taxOcupExp - 20), diaria: v.diariaPropria, custoOp: 1, wacc: v.waccFrota },
      { nome: 'Custo operacional +20%', ocup: v.taxOcupExp, diaria: v.diariaPropria, custoOp: 1.2, wacc: v.waccFrota },
      { nome: 'WACC +3pp', ocup: v.taxOcupExp, diaria: v.diariaPropria, custoOp: 1, wacc: v.waccFrota + 3 },
      { nome: 'Crise combinada (-15pp ocup, +15% custo, WACC +2pp)', ocup: Math.max(35, v.taxOcupExp - 15), diaria: v.diariaPropria * 0.98, custoOp: 1.15, wacc: v.waccFrota + 2 }
    ];
    var stress = cenarios.map(function (c) {
      var vMod = Object.assign({}, v, { taxOcupExp: c.ocup, diariaPropria: c.diaria, custoOpUnit: v.custoOpUnit * c.custoOp, waccFrota: c.wacc });
      var r = calcularModalidade(vMod, v.modalidadeFrota);
      var viavel = r.vpl > 0 && (r.semTir || r.tir > (c.wacc / 100)) && r.dscrMedio >= v.dscrMin;
      return [c.nome, f.int(c.ocup) + '%', f.brl(c.diaria), f.int(c.custoOp * 100) + '%', f.pct(c.wacc), f.brl(r.vpl), r.semTir ? 'n/a' : f.pct(r.tir * 100), dscrTxt(r.dscrMedio), viavel ? 'Sim' : 'Não'];
    });
    var labels = { compra: 'Compra à vista', financiamento: 'Financiamento (CDC/Finame)', consorcio: 'Consórcio', aluguel: 'Aluguel longo prazo', 'sale-leaseback': 'Sale & Leaseback' };
    var mods = ['compra', 'financiamento', 'consorcio', 'aluguel', 'sale-leaseback'].map(function (m) {
      var r = calcularModalidade(v, m);
      var viavel = r.vpl > 0 && (r.semTir || r.tir > (v.waccFrota / 100)) && r.dscrMedio >= v.dscrMin;
      return [labels[m] + (m === v.modalidadeFrota ? ' (selecionada)' : ''), f.brl(r.investInicial), f.brl(r.vpl), r.semTir ? 'n/a' : f.pct(r.tir * 100), isFinite(r.payback) ? f.num(r.payback) + ' a' : '—', dscrTxt(r.dscrMedio), viavel ? 'Recomendado' : (r.vpl > 0 ? 'Viável c/ ressalva' : 'Não recomendado')];
    });
    var fluxo = res.fluxoAnual.map(function (x) { return [String(x.ano), f.brl(x.receita), f.brl(x.custoOp), f.brl(x.finTotal), f.brl(x.depreciacao), f.brl(x.ebitda), f.brl(x.ir), f.brl(x.fcl), f.brl(x.vpFcl), f.brl(x.vplAcum)]; });
    return {
      titulo: labels[v.modalidadeFrota] + ': VPL de ' + f.brl(res.vpl),
      resumo: res.status,
      indicadores: [
        { rotulo: 'VPL', valor: res.vpl, formato: 'brl' },
        { rotulo: 'TIR', valor: res.semTir ? null : res.tir * 100, formato: 'pct', nota: res.semTir ? 'Não se aplica sem investimento inicial' : '' },
        { rotulo: 'Payback descontado (anos)', valor: res.payback, formato: 'num' },
        { rotulo: 'DSCR médio', valor: res.dscrMedio >= 99 ? null : res.dscrMedio, formato: 'num', nota: res.dscrMedio >= 99 ? 'Sem dívida' : '' },
        { rotulo: 'Ocupação de equilíbrio', valor: res.pontoEquil, formato: 'pct' },
        { rotulo: 'Investimento inicial', valor: res.investInicial, formato: 'brl' },
        { rotulo: 'Fluxo de caixa livre médio por ano', valor: res.fclMedio, formato: 'brl' }
      ],
      grafico: { titulo: 'VPL por modalidade', formato: 'brl', itens: ['compra', 'financiamento', 'consorcio', 'aluguel', 'sale-leaseback'].map(function (m) { return { rotulo: labels[m], valor: Math.max(calcularModalidade(v, m).vpl, 0) }; }) },
      tabela: { titulo: 'Comparativo das modalidades', colunas: ['Modalidade', 'Investimento', 'VPL', 'TIR', 'Payback', 'DSCR', 'Parecer'], linhas: mods },
      tabela2: { titulo: 'Fluxo anual da modalidade selecionada', colunas: ['Ano', 'Receita', 'Custo operacional', 'Financiamento', 'Depreciação', 'EBITDA', 'IR', 'FCL', 'VP do FCL', 'VPL acumulado'], linhas: fluxo },
      tabela3: { titulo: 'Teste de estresse', colunas: ['Cenário', 'Ocupação', 'Diária', 'Custo op.', 'WACC', 'VPL', 'TIR', 'DSCR', 'Viável'], linhas: stress },
      conclusoes: [res.status],
      sugestoes: ['Confira o teste de estresse: se o projeto só é viável no cenário base, ele depende de premissas otimistas.', 'Valide ocupação e diária com a demanda real da sua região antes de decidir.'],
      premissas: ['Sem investimento inicial (aluguel e consórcio), a TIR não se aplica e o critério passa a ser o VPL.', 'Tratamento fiscal simplificado: depreciação e juros dedutíveis; alíquota efetiva informada por você. Confirme com seu contador.', 'DSCR igual ao EBITDA dividido pelo serviço da dívida; "sem dívida" quando não há parcelas.']
    };
  }

});
