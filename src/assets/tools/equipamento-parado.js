OravelTool.register({
  slug: 'equipamento-parado',
  introTitulo: 'Quanto a frota parada deixa de faturar?',
  intro: 'Responda cinco perguntas rápidas. O cálculo é feito no seu navegador e você pode voltar e corrigir qualquer resposta.',
  passos: [
    { id: 'frota', rotulo: 'Equipamentos na frota', pergunta: 'Quantos equipamentos a locadora tem?', ajuda: 'Considere só os que existem para locar.', tipo: 'numero', min: 1, max: 100000, padrao: 50, sufixo: 'un.' },
    { id: 'diaria', rotulo: 'Diária média', pergunta: 'Qual a diária média de locação?', ajuda: 'Média ponderada da frota. Se variar muito, use a diária dos equipamentos que mais ficam parados.', tipo: 'numero', min: 1, max: 100000, prefixo: 'R$', padrao: 450 },
    { id: 'parada', rotulo: 'Frota parada em média', pergunta: 'Que percentual da frota está parada em média por falta de condição de locar?', ajuda: 'Manutenção, espera de peça, vistoria, documentação ou logística. Não conte equipamento disponível sem cliente.', tipo: 'numero', min: 0, max: 100, sufixo: '%', padrao: 12 },
    { id: 'dias', rotulo: 'Dias locáveis por mês', pergunta: 'Quantos dias por mês um equipamento disponível seria locado?', ajuda: 'Pense em dias com demanda. 26 é um ponto de partida comum.', tipo: 'numero', min: 1, max: 31, sufixo: 'dias', padrao: 26 },
    { id: 'uf', rotulo: 'Estado', pergunta: 'Em que estado fica a operação? (opcional)', ajuda: 'Usado só em estatísticas agregadas e anônimas do mercado.', tipo: 'opcoes', obrigatorio: false, opcoes: ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] }
  ],
  calcular: function (v) {
    var f = OravelTool.fmt;
    var parados = v.frota * v.parada / 100;
    var mes = parados * v.diaria * v.dias;
    var ano = mes * 12;
    var potencial = v.frota * v.diaria * v.dias;
    var cenario = function (corte) { return mes * corte; };
    var r10 = cenario(0.10), r25 = cenario(0.25), r50 = cenario(0.50);
    var gravidade = v.parada >= 15 ? 'alta' : (v.parada >= 8 ? 'moderada' : 'baixa');
    return {
      titulo: 'A frota parada custa ' + f.brl(mes) + ' por mês em receita não realizada',
      resumo: 'Com ' + f.num(v.frota) + ' equipamentos e ' + f.pct(v.parada) + ' parados, são cerca de ' + f.num(parados) + ' unidades fora de condição de locar. A relevância é ' + gravidade + ' para a operação.',
      indicadores: [
        { rotulo: 'Equipamentos parados', valor: parados, formato: 'num' },
        { rotulo: 'Receita não realizada por mês', valor: mes, formato: 'brl' },
        { rotulo: 'Receita não realizada por ano', valor: ano, formato: 'brl' },
        { rotulo: 'Peso no faturamento potencial', valor: potencial ? mes / potencial * 100 : 0, formato: 'pct', nota: 'Faturamento potencial: ' + f.brl(potencial) + ' por mês' }
      ],
      grafico: { titulo: 'Receita mensal recuperável se a parada diminuir', formato: 'brl', itens: [
        { rotulo: 'Reduzir 10%', valor: r10 }, { rotulo: 'Reduzir 25%', valor: r25 }, { rotulo: 'Reduzir 50%', valor: r50 }
      ] },
      conclusoes: [
        'Cada ponto percentual de frota parada equivale a ' + f.brl(mes / (v.parada || 1)) + ' por mês.',
        'Reduzir a parada pela metade devolveria ' + f.brl(r50 * 12) + ' por ano ao faturamento, se houver demanda para o equipamento recuperado.'
      ],
      sugestoes: [
        'Separe as paradas por causa (manutenção, peça, vistoria, documentação) por 30 dias. A maior fatia indica por onde começar.',
        'Meça o tempo médio de reparo por categoria de máquina e compare com o prazo prometido ao cliente.',
        'Mantenha estoque mínimo das peças que mais atrasam a liberação do equipamento.'
      ],
      premissas: [
        'Receita não realizada = equipamentos parados × diária média × dias locáveis por mês.',
        'Estimativa simplificada: não considera descontos, impostos nem demanda disponível para o equipamento recuperado.'
      ]
    };
  }
});
