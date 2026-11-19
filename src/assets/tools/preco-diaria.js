OravelTool.register({
  slug: 'preco-diaria',
  modo: 'formulario',
  // Fórmulas portadas sem alteração da versão 1.0. Padrões baseados no Painel do Mercado (P50).
  passos: [
    { id: 'custoAquisicao', grupo: 'Equipamento', pergunta: 'Custo de aquisição', prefixo: 'R$', tipo: 'numero', min: 0.01, max: 1e10, padrao: 250000 },
    { id: 'vidaUtilMeses', grupo: 'Equipamento', pergunta: 'Vida útil na locadora', sufixo: 'meses', tipo: 'numero', min: 1, max: 360, padrao: 60 },
    { id: 'valorRevenda', grupo: 'Equipamento', pergunta: 'Valor de revenda ao final', prefixo: 'R$', tipo: 'numero', min: 0, max: 1e10, padrao: 30000 },
    { id: 'utilizacaoPct', grupo: 'Operação', pergunta: 'Utilização esperada', sufixo: '%', tipo: 'numero', min: 1, max: 100, padrao: 65 },
    { id: 'horasPorDia', grupo: 'Operação', pergunta: 'Horas por dia', sufixo: 'h', tipo: 'numero', min: 1, max: 24, padrao: 8 },
    { id: 'diasUteisMes', grupo: 'Operação', pergunta: 'Dias úteis por mês', sufixo: 'dias', tipo: 'numero', min: 15, max: 25, padrao: 22 },
    { id: 'mesesPorAno', grupo: 'Operação', pergunta: 'Meses de locação por ano', sufixo: 'meses', tipo: 'numero', min: 6, max: 12, padrao: 12 },
    { id: 'custoManutencaoMes', grupo: 'Custos mensais', pergunta: 'Manutenção', prefixo: 'R$/mês', tipo: 'numero', min: 0, max: 1e9, padrao: 3500 },
    { id: 'custoSeguroMes', grupo: 'Custos mensais', pergunta: 'Seguro', prefixo: 'R$/mês', tipo: 'numero', min: 0, max: 1e9, padrao: 800 },
    { id: 'custoRastreamentoMes', grupo: 'Custos mensais', pergunta: 'Rastreamento e telemetria', prefixo: 'R$/mês', tipo: 'numero', min: 0, max: 1e9, padrao: 180 },
    { id: 'outrosCustosMes', grupo: 'Custos mensais', pergunta: 'Outros custos fixos', prefixo: 'R$/mês', tipo: 'numero', min: 0, max: 1e9, padrao: 0 },
    { id: 'custoCapitalAnual', grupo: 'Capital e margem', pergunta: 'Custo de capital', sufixo: '% a.a.', tipo: 'numero', min: 0, max: 50, padrao: 14.5 },
    { id: 'margemDesejada', grupo: 'Capital e margem', pergunta: 'Margem desejada', sufixo: '%', tipo: 'numero', min: 0, max: 200, padrao: 30 }
  ],
  calcular: function (vals) {
    var f = OravelTool.fmt;
    var brl2 = function (v) { return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); };
    var baseDepreciavel = vals.custoAquisicao - vals.valorRevenda;
    var depreciacaoMensal = baseDepreciavel / vals.vidaUtilMeses;
    var valorMedioAtivo = (vals.custoAquisicao + vals.valorRevenda) / 2;
    var custoCapitalMensal = valorMedioAtivo * (vals.custoCapitalAnual / 100) / 12;
    var custosOperacionaisMes = vals.custoManutencaoMes + vals.custoSeguroMes + vals.custoRastreamentoMes + vals.outrosCustosMes;
    var tcoMensal = depreciacaoMensal + custoCapitalMensal + custosOperacionaisMes;
    var horasDisponiveisMes = vals.horasPorDia * vals.diasUteisMes;
    var horasAlugadasMes = horasDisponiveisMes * (vals.utilizacaoPct / 100);
    var custoHoraDisponivel = tcoMensal / horasDisponiveisMes;
    var precoMinimoHora = tcoMensal / horasAlugadasMes;
    var precoMinimoDiaria = precoMinimoHora * 8;
    var precoSugeridoDiaria = precoMinimoDiaria * (1 + vals.margemDesejada / 100);
    var pontoEquilibrioDias = tcoMensal / precoSugeridoDiaria;
    var receitaMensalEsperada = precoSugeridoDiaria * (horasAlugadasMes / 8);
    var fluxoLiquidoMensal = receitaMensalEsperada - custosOperacionaisMes;
    var paybackMeses = fluxoLiquidoMensal > 0 ? vals.custoAquisicao / fluxoLiquidoMensal : Infinity;

    var cenarios = [
      { nome: 'Base (atual)', util: vals.utilizacaoPct, cap: vals.custoCapitalAnual },
      { nome: 'Utilização -10pp', util: Math.max(10, vals.utilizacaoPct - 10), cap: vals.custoCapitalAnual },
      { nome: 'Utilização +10pp', util: Math.min(100, vals.utilizacaoPct + 10), cap: vals.custoCapitalAnual },
      { nome: 'Custo capital -2pp', util: vals.utilizacaoPct, cap: Math.max(0, vals.custoCapitalAnual - 2) },
      { nome: 'Custo capital +2pp', util: vals.utilizacaoPct, cap: vals.custoCapitalAnual + 2 },
      { nome: 'Pessimista (-10pp util, +2pp cap)', util: Math.max(10, vals.utilizacaoPct - 10), cap: vals.custoCapitalAnual + 2 },
      { nome: 'Otimista (+10pp util, -2pp cap)', util: Math.min(100, vals.utilizacaoPct + 10), cap: Math.max(0, vals.custoCapitalAnual - 2) }
    ];
    var linhas = cenarios.map(function (c) {
      var capMes = valorMedioAtivo * (c.cap / 100) / 12;
      var tco = depreciacaoMensal + capMes + custosOperacionaisMes;
      var hAlug = horasDisponiveisMes * (c.util / 100);
      var precoSug = tco / hAlug * 8 * (1 + vals.margemDesejada / 100);
      var delta = precoSugeridoDiaria > 0 ? (precoSug - precoSugeridoDiaria) / precoSugeridoDiaria * 100 : 0;
      return [c.nome, f.pct(c.util), f.pct(c.cap), brl2(precoSug), (delta >= 0 ? '+' : '') + f.num(delta) + '%'];
    });
    return {
      titulo: 'Preço sugerido da diária: ' + brl2(precoSugeridoDiaria),
      resumo: 'Preço mínimo de ' + brl2(precoMinimoDiaria) + ' por diária de 8 horas; o sugerido inclui margem de ' + f.pct(vals.margemDesejada) + '.',
      indicadores: [
        { rotulo: 'Preço mínimo da diária (8h)', valor: precoMinimoDiaria, formato: 'brl' },
        { rotulo: 'Preço sugerido da diária (8h)', valor: precoSugeridoDiaria, formato: 'brl' },
        { rotulo: 'Custo por hora disponível', valor: custoHoraDisponivel, formato: 'brl' },
        { rotulo: 'Dias por mês para equilíbrio', valor: pontoEquilibrioDias, formato: 'num' },
        { rotulo: 'Payback (meses)', valor: isFinite(paybackMeses) ? paybackMeses : 0, formato: 'num', nota: isFinite(paybackMeses) ? '' : 'Fluxo líquido não positivo' }
      ],
      grafico: { titulo: 'Composição do custo total mensal', formato: 'brl', itens: [
        { rotulo: 'Depreciação', valor: depreciacaoMensal }, { rotulo: 'Custo de capital', valor: custoCapitalMensal },
        { rotulo: 'Manutenção', valor: vals.custoManutencaoMes }, { rotulo: 'Seguro', valor: vals.custoSeguroMes },
        { rotulo: 'Rastreamento', valor: vals.custoRastreamentoMes }, { rotulo: 'Outros', valor: vals.outrosCustosMes }
      ] },
      tabela: { titulo: 'Sensibilidade do preço sugerido', colunas: ['Cenário', 'Utilização', 'Custo capital', 'Preço sugerido', 'Variação'], linhas: linhas },
      conclusoes: ['Custo total mensal (TCO): ' + brl2(tcoMensal) + '.'],
      sugestoes: ['Compare o preço sugerido com o praticado pelo mercado na sua região antes de adotá-lo.', 'Veja na tabela quanto cada ponto de utilização muda o preço: é onde a gestão mais atua.'],
      premissas: ['Depreciação linear; custo de capital sobre o valor médio do ativo.', 'Payback considera receita esperada menos custos desembolsáveis.']
    };
  }
});
