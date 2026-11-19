OravelTool.register({
  slug: 'custo-propriedade',
  modo: 'formulario',
  // Formulas portadas sem alteracao da versao anterior.
  passos: [
 {
  "id": "codTco",
  "grupo": "Identificação",
  "pergunta": "Código / Tag",
  "tipo": "texto",
  "padrao": ""
 },
 {
  "id": "catTco",
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
  "id": "modTco",
  "grupo": "Identificação",
  "pergunta": "Marca / Modelo",
  "tipo": "texto",
  "padrao": ""
 },
 {
  "id": "condTco",
  "grupo": "Identificação",
  "pergunta": "Condição",
  "tipo": "opcoes",
  "opcoes": [
   {
    "valor": "novo",
    "rotulo": "Novo (0h)"
   },
   {
    "valor": "usado",
    "rotulo": "Usado (horas existentes)"
   }
  ],
  "padrao": "novo"
 },
 {
  "id": "hrsUsadoTco",
  "grupo": "Identificação",
  "pergunta": "Horas atuais (se usado)",
  "tipo": "numero",
  "min": 0,
  "padrao": 3000
 },
 {
  "id": "precoCompra",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Preço de compra (R$)",
  "tipo": "numero",
  "min": 0,
  "padrao": 350000
 },
 {
  "id": "vidaUtilTco",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Vida útil total (horas)",
  "tipo": "numero",
  "min": 1000,
  "max": 50000,
  "padrao": 12000
 },
 {
  "id": "valResidual",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Valor residual estimado (R$)",
  "tipo": "numero",
  "min": 0,
  "padrao": 50000
 },
 {
  "id": "hrsAno",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Horas/ano planejadas",
  "tipo": "numero",
  "min": 500,
  "max": 4000,
  "padrao": 2000
 },
 {
  "id": "pctFinTco",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Financiado? % do valor",
  "tipo": "numero",
  "min": 0,
  "max": 100,
  "padrao": 60
 },
 {
  "id": "taxaFin",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Taxa financiamento (% a.a.)",
  "tipo": "numero",
  "min": 0,
  "max": 30,
  "padrao": 13.5
 },
 {
  "id": "prazoFin",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "Prazo financiamento (meses)",
  "tipo": "numero",
  "min": 1,
  "max": 120,
  "padrao": 48
 },
 {
  "id": "waccTco",
  "grupo": "Aquisição e Vida Útil",
  "pergunta": "WACC / Custo capital próprio (% a.a.)",
  "tipo": "numero",
  "min": 0,
  "max": 50,
  "padrao": 14.5
 },
 {
  "id": "combAnual",
  "grupo": "Custos Operacionais Anuais",
  "pergunta": "Combustível (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 72000
 },
 {
  "id": "opAnual",
  "grupo": "Custos Operacionais Anuais",
  "pergunta": "Operador dedicado (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 54000
 },
 {
  "id": "prevAnual",
  "grupo": "Custos Operacionais Anuais",
  "pergunta": "Manutenção preventiva (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 15000
 },
 {
  "id": "corrAnual",
  "grupo": "Custos Operacionais Anuais",
  "pergunta": "Manutenção corretiva (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 25000
 },
 {
  "id": "segAnual",
  "grupo": "Custos Operacionais Anuais",
  "pergunta": "Seguro (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 7800
 },
 {
  "id": "rastAnual",
  "grupo": "Custos Operacionais Anuais",
  "pergunta": "Rastreamento/Telemetria (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 1800
 },
 {
  "id": "pneusAnual",
  "grupo": "Custos Operacionais Anuais",
  "pergunta": "Pneus/Esteiras desgaste (R$/ano)",
  "tipo": "numero",
  "min": 0,
  "padrao": 12000
 },
 {
  "id": "outrosAnual",
  "grupo": "Custos Operacionais Anuais",
  "pergunta": "Outros (lubrificantes, filtros, etc.)",
  "tipo": "numero",
  "min": 0,
  "padrao": 6000
 },
 {
  "id": "diariaRental",
  "grupo": "Alternativas de Comparação",
  "pergunta": "Diária aluguel (rental) (R$/8h)",
  "tipo": "numero",
  "min": 0,
  "padrao": 550
 },
 {
  "id": "custTerc",
  "grupo": "Alternativas de Comparação",
  "pergunta": "Custo terceirização completa (R$/h)",
  "tipo": "numero",
  "min": 0,
  "padrao": 180
 }
],
  calcular: function (v) {
    var f = OravelTool.fmt, brl2 = function (x) { return x.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
    var pmt = function (rate, nper, pv) { if (rate === 0) return pv / nper; var r = rate / 100 / 12; return pv * r * Math.pow(1 + r, nper) / (Math.pow(1 + r, nper) - 1); };
    var horasIniciais = v.condTco === 'usado' ? (v.hrsUsadoTco || 0) : 0;
    var horasRestantes = v.vidaUtilTco - horasIniciais;
    var anosVida = Math.max(1, Math.ceil(horasRestantes / v.hrsAno));
    var valorFinanciado = v.precoCompra * (v.pctFinTco / 100);
    var valorProprio = v.precoCompra - valorFinanciado;
    var parcelaMensal = valorFinanciado > 0 ? pmt(v.taxaFin, v.prazoFin, valorFinanciado) : 0;
    var parcelaAnual = parcelaMensal * 12;
    var depreciacaoAnual = (v.precoCompra - v.valResidual) / anosVida;
    var custoOpAnual = v.combAnual + v.opAnual + v.prevAnual + v.corrAnual + v.segAnual + v.rastAnual + v.pneusAnual + v.outrosAnual;

    var fluxoAnual = [], saldoDevedor = valorFinanciado;
    for (var ano = 1; ano <= anosVida; ano++) {
      var capex = ano === 1 ? valorProprio : 0, finPrinc = 0, finJuros = 0;
      if (saldoDevedor > 0 && ano <= v.prazoFin / 12) {
        finJuros = saldoDevedor * (v.taxaFin / 100);
        finPrinc = parcelaAnual - finJuros;
        saldoDevedor = Math.max(0, saldoDevedor - finPrinc);
      }
      var finTotal = finPrinc + finJuros;
      var capProprioMedio = valorProprio * (1 - (ano - 0.5) / anosVida);
      var custoCapital = capProprioMedio * (v.waccTco / 100);
      var totalAno = finTotal + custoOpAnual;
      var vpAno = totalAno / Math.pow(1 + v.waccTco / 100, ano);
      fluxoAnual.push([String(ano), f.brl(capex), f.brl(finTotal), f.brl(custoOpAnual), f.brl(depreciacaoAnual), f.brl(custoCapital), f.brl(totalAno), f.brl(vpAno)]);
    }
    var pvParcelas = function (wacc) {
      var pv = 0;
      for (var a = 1; a <= Math.ceil(v.prazoFin / 12); a++) {
        var meses = Math.min(12, Math.max(0, v.prazoFin - 12 * (a - 1)));
        pv += parcelaMensal * meses / Math.pow(1 + wacc / 100, a);
      }
      return pv;
    };
    var pvOperacao = function (custoOpA, anos, wacc) { var pv = 0; for (var a = 1; a <= anos; a++) pv += custoOpA / Math.pow(1 + wacc / 100, a); return pv; };
    var vpResidual = v.valResidual / Math.pow(1 + v.waccTco / 100, anosVida);
    var tcoTotal = valorProprio + pvParcelas(v.waccTco) + pvOperacao(custoOpAnual, anosVida, v.waccTco) - vpResidual;
    var horasTotais = anosVida * v.hrsAno;
    var custoHora = tcoTotal / horasTotais;
    var custoRentalAnual = (v.diariaRental / 8) * v.hrsAno;
    var vplRental = pvOperacao(custoRentalAnual, anosVida, v.waccTco);
    var custoTercAnual = v.custTerc * v.hrsAno;
    var vplTerc = pvOperacao(custoTercAnual, anosVida, v.waccTco);

    var tir = 0;
    var fluxoCompra = [-v.precoCompra];
    for (var a2 = 1; a2 <= anosVida; a2++) fluxoCompra.push(custoRentalAnual - custoOpAnual + (a2 === anosVida ? v.valResidual : 0));
    var low = -0.9, high = 3.0;
    for (var i = 0; i < 60; i++) {
      var mid = (low + high) / 2, npv = 0;
      for (var t = 0; t < fluxoCompra.length; t++) npv += fluxoCompra[t] / Math.pow(1 + mid, t);
      if (npv > 0) low = mid; else high = mid;
    }
    tir = (low + high) / 2;
    var economiaAnualVsRental = custoRentalAnual - (custoOpAnual + (parcelaAnual * (v.prazoFin / 12 >= 1 ? 1 : 0)));
    var paybackAnos = economiaAnualVsRental > 0 ? v.precoCompra / economiaAnualVsRental : Infinity;

    var baseHrs = v.hrsAno, baseVida = v.vidaUtilTco, baseRes = v.valResidual, baseComb = v.combAnual;
    var cenarios = [
      { nome: 'Base', hrs: baseHrs, vida: baseVida, res: baseRes, comb: baseComb },
      { nome: 'Baixa utilização (-30% hrs)', hrs: baseHrs * 0.7, vida: baseVida, res: baseRes, comb: baseComb * 0.7 },
      { nome: 'Alta utilização (+30% hrs)', hrs: Math.min(4000, baseHrs * 1.3), vida: baseVida, res: baseRes * 0.9, comb: baseComb * 1.3 },
      { nome: 'Vida útil -20%', hrs: baseHrs, vida: baseVida * 0.8, res: baseRes * 0.7, comb: baseComb },
      { nome: 'Vida útil +20%', hrs: baseHrs, vida: baseVida * 1.2, res: baseRes * 1.2, comb: baseComb },
      { nome: 'Valor residual zero', hrs: baseHrs, vida: baseVida, res: 0, comb: baseComb },
      { nome: 'Combustível +25%', hrs: baseHrs, vida: baseVida, res: baseRes, comb: baseComb * 1.25 },
      { nome: 'WACC +3pp', hrs: baseHrs, vida: baseVida, res: baseRes, comb: baseComb, wacc: v.waccTco + 3 }
    ];
    var linhasCen = cenarios.map(function (c) {
      var wacc = c.wacc || v.waccTco;
      var anos = Math.max(1, Math.ceil((c.vida - horasIniciais) / c.hrs));
      var custoOpA = c.comb + v.opAnual * (c.hrs / baseHrs) + v.prevAnual + v.corrAnual * (c.hrs / baseHrs) + v.segAnual + v.rastAnual + v.pneusAnual * (c.hrs / baseHrs) + v.outrosAnual;
      var vplC = valorProprio + pvParcelas(wacc) + pvOperacao(custoOpA, anos, wacc) - c.res / Math.pow(1 + wacc / 100, anos);
      var ch = vplC / (anos * c.hrs);
      var delta = ((ch - custoHora) / custoHora) * 100;
      return [c.nome, f.int(c.hrs), f.int(c.vida), f.brl(c.res), f.brl(c.comb), brl2(ch) + '/h', (delta >= 0 ? '+' : '') + f.num(delta) + '%'];
    });
    var opts = [{ rotulo: 'Comprar', vpl: tcoTotal }, { rotulo: 'Alugar (rental)', vpl: vplRental }, { rotulo: 'Terceirizar', vpl: vplTerc }].sort(function (a, b) { return a.vpl - b.vpl; });
    var h = function (x) { return brl2(x / horasTotais) + '/h'; };
    return {
      titulo: 'Menor custo no período: ' + opts[0].rotulo.toLowerCase() + ' (' + f.brl(opts[0].vpl) + ' em valor presente)',
      resumo: (v.codTco ? v.codTco + ': ' : '') + 'custo total de propriedade de ' + f.brl(tcoTotal) + ' em ' + anosVida + ' ano(s), ou ' + brl2(custoHora) + ' por hora.',
      indicadores: [
        { rotulo: 'TCO total (valor presente)', valor: tcoTotal, formato: 'brl' },
        { rotulo: 'Custo por hora (próprio)', valor: custoHora, formato: 'brl2' },
        { rotulo: 'TIR da compra vs. aluguel', valor: tir * 100, formato: 'pct' },
        { rotulo: 'Payback vs. aluguel (anos)', valor: isFinite(paybackAnos) ? paybackAnos : null, formato: 'num', nota: isFinite(paybackAnos) ? '' : 'Aluguel sai mais barato por ano' },
        { rotulo: 'Vida considerada (anos)', valor: anosVida, formato: 'int' }
      ],
      grafico: { titulo: 'Custo total em valor presente por alternativa', formato: 'brl', itens: [
        { rotulo: 'Comprar', valor: tcoTotal }, { rotulo: 'Alugar', valor: vplRental }, { rotulo: 'Terceirizar', valor: vplTerc }
      ] },
      tabela: { titulo: 'Comparativo das alternativas', colunas: ['Alternativa', 'Custo total (VP)', 'Custo por hora equivalente'], linhas: [
        ['Comprar', f.brl(tcoTotal), brl2(custoHora) + '/h'], ['Alugar (rental)', f.brl(vplRental), h(vplRental)], ['Terceirizar', f.brl(vplTerc), h(vplTerc)]
      ] },
      tabela2: { titulo: 'Fluxo anual da compra', colunas: ['Ano', 'Capital próprio', 'Financiamento', 'Operação', 'Depreciação', 'Custo de capital', 'Total do ano', 'Valor presente'], linhas: fluxoAnual },
      conclusoes: ['Composição do TCO: capital próprio ' + f.brl(valorProprio) + ', parcelas ' + f.brl(pvParcelas(v.waccTco)) + ' (VP), operação ' + f.brl(pvOperacao(custoOpAnual, anosVida, v.waccTco)) + ' (VP), menos valor residual de ' + f.brl(vpResidual) + ' (VP).'],
      sugestoes: ['Veja na tabela de cenários o quanto o custo por hora muda com menos horas de uso: a compra perde força com baixa utilização.'],
      premissas: ['O custo de oportunidade do capital está na taxa de desconto (WACC) e não é somado de novo.', 'Depreciação linear; vida em anos = horas restantes ÷ horas por ano.', 'A tabela de cenários da versão anterior foi mantida em tabela2 abaixo do fluxo.'],
      tabela3: { titulo: 'Sensibilidade do custo por hora', colunas: ['Cenário', 'Horas/ano', 'Vida útil (h)', 'Residual', 'Combustível', 'Custo por hora', 'Variação'], linhas: linhasCen }
    };
  }

});
