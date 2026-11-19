OravelTool.register({
  slug: 'calculadora-rentabilidade',
  introTitulo: 'A frota rende o que deveria?',
  intro: 'Seis perguntas sobre receita, custos e valor da frota. O cálculo é feito no seu navegador.',
  passos: [
    { id: 'valor', rotulo: 'Valor da frota', pergunta: 'Qual o valor de mercado da frota (ou do grupo que quer analisar)?', ajuda: 'Use o valor atual de reposição ou de mercado, não o valor contábil.', tipo: 'numero', min: 1, max: 1e10, prefixo: 'R$', padrao: 5000000 },
    { id: 'receita', rotulo: 'Receita mensal de locação', pergunta: 'Qual a receita média mensal de locação desse grupo?', ajuda: 'Média dos últimos 6 a 12 meses, sem impostos sobre a receita se possível.', tipo: 'numero', min: 0, max: 1e9, prefixo: 'R$', padrao: 250000 },
    { id: 'manut', rotulo: 'Manutenção mensal', pergunta: 'Quanto se gasta por mês em manutenção, peças e reparos?', ajuda: 'Inclua mão de obra da oficina e serviços de terceiros.', tipo: 'numero', min: 0, max: 1e9, prefixo: 'R$', padrao: 40000 },
    { id: 'fixos', rotulo: 'Outros custos mensais', pergunta: 'Quanto custam por mês seguro, rastreamento, pessoal, logística e demais custos da operação?', ajuda: 'Não inclua depreciação nem juros: eles entram nas próximas perguntas.', tipo: 'numero', min: 0, max: 1e9, prefixo: 'R$', padrao: 90000 },
    { id: 'deprec', rotulo: 'Depreciação anual', pergunta: 'Qual a depreciação anual da frota, em % do valor?', ajuda: 'Perda de valor de mercado por ano. Use o histórico de revenda das suas máquinas.', tipo: 'numero', min: 0, max: 100, sufixo: '%', padrao: 10 },
    { id: 'capital', rotulo: 'Custo de capital anual', pergunta: 'Qual o custo de capital anual, em % do valor da frota?', ajuda: 'Juros do financiamento ou o retorno que o dinheiro renderia em outra aplicação.', tipo: 'numero', min: 0, max: 100, sufixo: '%', padrao: 14 }
  ],
  calcular: function (v) {
    var f = OravelTool.fmt;
    var op = v.receita - v.manut - v.fixos;
    var dep = v.valor * v.deprec / 100 / 12;
    var liq = op - dep;
    var cap = v.valor * v.capital / 100 / 12;
    var eco = liq - cap;
    var margemOp = v.receita ? op / v.receita * 100 : 0;
    var roi = liq * 12 / v.valor * 100;
    var yieldB = v.receita * 12 / v.valor * 100;
    var payback = op > 0 ? v.valor / (op * 12) : null;
    var manutPct = v.receita ? v.manut / v.receita * 100 : 0;
    var positivo = eco >= 0;
    return {
      titulo: positivo ? 'A frota cobre o custo de capital: sobram ' + f.brl(eco) + ' por mês' : 'A frota não cobre o custo de capital: faltam ' + f.brl(-eco) + ' por mês',
      resumo: 'Retorno anual de ' + f.pct(roi) + ' sobre o valor da frota, contra um custo de capital de ' + f.pct(v.capital) + '.',
      indicadores: [
        { rotulo: 'Margem operacional', valor: margemOp, formato: 'pct', nota: 'Receita menos manutenção e outros custos' },
        { rotulo: 'Retorno anual sobre a frota', valor: roi, formato: 'pct', nota: 'Depois da depreciação' },
        { rotulo: 'Receita bruta anual / valor da frota', valor: yieldB, formato: 'pct' },
        { rotulo: 'Payback simples (anos)', valor: payback == null ? 0 : payback, formato: 'num', nota: payback == null ? 'Resultado operacional não positivo' : 'Valor da frota ÷ resultado operacional anual' },
        { rotulo: 'Manutenção sobre receita', valor: manutPct, formato: 'pct' },
        { rotulo: 'Resultado depois do capital (mês)', valor: eco, formato: 'brl' }
      ],
      grafico: { titulo: 'Da receita ao resultado, por mês', formato: 'brl', itens: [
        { rotulo: 'Receita', valor: v.receita }, { rotulo: 'Manutenção', valor: v.manut }, { rotulo: 'Outros custos', valor: v.fixos },
        { rotulo: 'Depreciação', valor: dep }, { rotulo: 'Custo de capital', valor: cap }, { rotulo: 'Resultado final', valor: Math.max(eco, 0) }
      ] },
      conclusoes: [
        'Resultado operacional: ' + f.brl(op) + ' por mês. Depois da depreciação: ' + f.brl(liq) + '. Depois do custo de capital: ' + f.brl(eco) + '.',
        positivo ? 'O retorno supera o custo de capital em ' + f.pct(roi - v.capital) + ' ao ano.' : 'Para empatar com o custo de capital seria preciso melhorar o resultado em ' + f.brl(-eco) + ' por mês.'
      ],
      sugestoes: [
        manutPct > 20 ? 'Manutenção acima de 20% da receita: veja quais categorias concentram o gasto e compare preventiva e corretiva.' : 'Acompanhe a manutenção por categoria para perceber desvio cedo.',
        'Teste quanto de diária ou de utilização fecharia o buraco: 1 ponto de utilização vale ' + f.brl(v.receita * 0.01 / 0.7) + ' por mês se a utilização atual for de cerca de 70%.',
        'Repita o cálculo por grupo de máquinas: a média esconde categorias que dão prejuízo.'
      ],
      premissas: ['Depreciação e custo de capital calculados linearmente sobre o valor informado.', 'Não considera impostos sobre o lucro nem o valor de revenda na saída da máquina.']
    };
  }
});
