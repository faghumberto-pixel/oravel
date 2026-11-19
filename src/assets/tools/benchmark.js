(function () {
  var ind = [
    { id: 'util', nome: 'Utilização física', sufixo: '%', max: 100, melhor: 'maior', meta: 70, ajuda: 'Dias locados ÷ dias disponíveis, na média da frota. Referência de mercado internacional: cerca de 70% da frota em locação (guias do setor, ex.: TARGIT).' },
    { id: 'disp', nome: 'Disponibilidade', sufixo: '%', max: 100, melhor: 'maior', meta: 92, ajuda: 'Percentual do tempo em que o equipamento está em condição de locar. Referência internacional: no máximo 8% a 10% da frota fora de condição de locar. Pesquisa da Sobratema: 39% dos usuários mantêm até 10% da frota parada.' },
    { id: 'manut', nome: 'Manutenção sobre receita', sufixo: '%', max: 100, melhor: 'menor', meta: 15, ajuda: 'Gasto com manutenção ÷ receita de locação.' },
    { id: 'inad', nome: 'Inadimplência', sufixo: '%', max: 100, melhor: 'menor', meta: 3, ajuda: 'Valor vencido há mais de 30 dias ÷ faturamento do período.' },
    { id: 'marg', nome: 'Margem operacional', sufixo: '%', max: 100, melhor: 'maior', meta: 25, ajuda: 'Resultado operacional ÷ receita, antes de depreciação e juros.' }
  ];
  var passos = [];
  ind.forEach(function (i) { passos.push({ id: i.id, rotulo: i.nome + ' atual', pergunta: 'Qual a ' + i.nome.toLowerCase() + ' atual da locadora?', ajuda: i.ajuda, tipo: 'numero', min: 0, max: i.max, sufixo: '%' }); });
  ind.forEach(function (i) { passos.push({ id: i.id + '_meta', rotulo: i.nome + ' (meta)', pergunta: 'Qual a sua meta para ' + i.nome.toLowerCase() + '?', ajuda: 'Valor sugerido apenas como ponto de partida. Ajuste para a realidade da sua operação.', tipo: 'numero', min: 0, max: i.max, sufixo: '%', padrao: i.meta }); });
  passos.push({ id: 'uf', rotulo: 'Estado', pergunta: 'Em que estado fica a operação? (opcional)', ajuda: 'Usado só em estatísticas agregadas e anônimas.', tipo: 'opcoes', obrigatorio: false, opcoes: ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] });

  OravelTool.register({
    slug: 'benchmark',
    introTitulo: 'Onde a locadora está longe da meta?',
    intro: 'Informe cinco indicadores e a meta de cada um. A ferramenta mostra a distância e o que olhar primeiro. A comparação com o mercado depende de dados do Painel do Mercado e ainda não está incluída.',
    passos: passos,
    calcular: function (v) {
      var f = OravelTool.fmt;
      var linhas = ind.map(function (i) {
        var a = v[i.id], m = v[i.id + '_meta'];
        var ating = i.melhor === 'maior' ? (m ? a / m * 100 : 0) : (a ? m / a * 100 : 150);
        ating = Math.min(ating, 150);
        return { i: i, a: a, m: m, ating: ating, ok: i.melhor === 'maior' ? a >= m : a <= m };
      });
      var fora = linhas.filter(function (l) { return !l.ok; }).sort(function (x, y) { return x.ating - y.ating; });
      var dentro = linhas.length - fora.length;
      return {
        titulo: dentro === linhas.length ? 'Os cinco indicadores estão dentro da meta' : dentro + ' de 5 indicadores estão dentro da meta',
        resumo: fora.length ? 'A maior distância está em ' + fora[0].i.nome.toLowerCase() + ': ' + f.pct(fora[0].a) + ' contra meta de ' + f.pct(fora[0].m) + '.' : 'Revise as metas: se todas foram batidas, talvez estejam baixas.',
        indicadores: linhas.map(function (l) { return { rotulo: l.i.nome, valor: l.a, formato: 'pct', nota: 'Meta: ' + f.pct(l.m) + (l.ok ? ' (atingida)' : ' (não atingida)') }; }),
        grafico: { titulo: 'Atingimento da meta (100% = meta; limite de 150%)', formato: 'pct', itens: linhas.map(function (l) { return { rotulo: l.i.nome, valor: l.ating }; }) },
        conclusoes: fora.length ? fora.slice(0, 3).map(function (l) { return l.i.nome + ': ' + (l.i.melhor === 'maior' ? 'faltam ' + f.num(l.m - l.a) : 'excesso de ' + f.num(l.a - l.m)) + ' ponto(s) percentual(is) em relação à meta.'; }) : ['Nenhum indicador abaixo da meta informada.'],
        sugestoes: [
          'Atue primeiro no indicador mais distante da meta, e não em todos ao mesmo tempo.',
          'Registre os cinco indicadores todo mês, com a mesma fórmula, para ver tendência e não só um retrato.',
          'Quando houver base suficiente, o Painel do Mercado permitirá comparar com outras locadoras de forma anônima.'
        ],
        premissas: ['As metas são definidas por você. Os valores sugeridos não são média de mercado.', 'Para indicadores em que menos é melhor, atingimento = meta ÷ valor atual.']
      };
    }
  });
})();
