OravelTool.register({
  slug: 'checklist-vistoria',
  introTitulo: 'Como está a sua vistoria?',
  intro: 'Dez perguntas objetivas sobre o processo de vistoria. No fim você vê a pontuação e o que corrigir primeiro.',
  passos: [
  {
    "id": "docs",
    "rotulo": "Existe contrato ou termo de vistoria assinado na entrega e na devolução",
    "pergunta": "Existe contrato ou termo de vistoria assinado na entrega e na devolução?",
    "tipo": "opcoes",
    "obrigatorio": true,
    "opcoes": [
      "Sim",
      "Parcialmente",
      "Não"
    ]
  },
  {
    "id": "fotos",
    "rotulo": "As fotos são tiradas na entrega e na devolução, com data e identificação do equipamento",
    "pergunta": "As fotos são tiradas na entrega e na devolução, com data e identificação do equipamento?",
    "tipo": "opcoes",
    "obrigatorio": true,
    "opcoes": [
      "Sim",
      "Parcialmente",
      "Não"
    ]
  },
  {
    "id": "horimetro",
    "rotulo": "Horímetro ou odômetro é registrado nas duas vistorias",
    "pergunta": "Horímetro ou odômetro é registrado nas duas vistorias?",
    "tipo": "opcoes",
    "obrigatorio": true,
    "opcoes": [
      "Sim",
      "Parcialmente",
      "Não"
    ]
  },
  {
    "id": "padrao",
    "rotulo": "Há um checklist padrão por categoria de equipamento",
    "pergunta": "Há um checklist padrão por categoria de equipamento?",
    "tipo": "opcoes",
    "obrigatorio": true,
    "opcoes": [
      "Sim",
      "Parcialmente",
      "Não"
    ]
  },
  {
    "id": "avarias",
    "rotulo": "Avarias e itens faltantes são descritos e assinados pelo cliente na hora",
    "pergunta": "Avarias e itens faltantes são descritos e assinados pelo cliente na hora?",
    "tipo": "opcoes",
    "obrigatorio": true,
    "opcoes": [
      "Sim",
      "Parcialmente",
      "Não"
    ]
  },
  {
    "id": "comparativo",
    "rotulo": "Na devolução, o responsável compara com o registro da entrega",
    "pergunta": "Na devolução, o responsável compara com o registro da entrega?",
    "tipo": "opcoes",
    "obrigatorio": true,
    "opcoes": [
      "Sim",
      "Parcialmente",
      "Não"
    ]
  },
  {
    "id": "tempo",
    "rotulo": "A vistoria de devolução acontece no mesmo dia, antes de liberar o cliente",
    "pergunta": "A vistoria de devolução acontece no mesmo dia, antes de liberar o cliente?",
    "tipo": "opcoes",
    "obrigatorio": true,
    "opcoes": [
      "Sim",
      "Parcialmente",
      "Não"
    ]
  },
  {
    "id": "cobranca",
    "rotulo": "O contrato prevê como as avarias são cobradas e em que prazo",
    "pergunta": "O contrato prevê como as avarias são cobradas e em que prazo?",
    "tipo": "opcoes",
    "obrigatorio": true,
    "opcoes": [
      "Sim",
      "Parcialmente",
      "Não"
    ]
  },
  {
    "id": "treino",
    "rotulo": "Quem faz a vistoria foi treinado e usa o mesmo critério",
    "pergunta": "Quem faz a vistoria foi treinado e usa o mesmo critério?",
    "tipo": "opcoes",
    "obrigatorio": true,
    "opcoes": [
      "Sim",
      "Parcialmente",
      "Não"
    ]
  },
  {
    "id": "arquivo",
    "rotulo": "Os registros ficam guardados e podem ser achados em minutos",
    "pergunta": "Os registros ficam guardados e podem ser achados em minutos?",
    "tipo": "opcoes",
    "obrigatorio": true,
    "opcoes": [
      "Sim",
      "Parcialmente",
      "Não"
    ]
  }
],
  calcular: function (v) {
    var f = OravelTool.fmt;
    var pts = { 'Sim': 1, 'Parcialmente': 0.5, 'Não': 0 };
    var total = 0, falhas = [], parciais = [];
    this.passos.forEach(function (p) {
      var r = v[p.id]; total += pts[r] || 0;
      if (r === 'Não') falhas.push(p.pergunta); else if (r === 'Parcialmente') parciais.push(p.pergunta);
    });
    var n = this.passos.length, nota = total / n * 100;
    var nivel = nota >= 80 ? 'Sólido' : (nota >= 50 ? 'Em construção' : 'Frágil');
    return {
      titulo: 'Processo de vistoria: ' + nivel.toLowerCase() + ' (' + f.int(nota) + ' de 100)',
      resumo: falhas.length ? falhas.length + ' prática(s) ainda não existem e ' + parciais.length + ' existem pela metade.' : (parciais.length ? parciais.length + ' prática(s) existem pela metade.' : 'Todas as práticas avaliadas estão em uso.'),
      indicadores: [
        { rotulo: 'Pontuação', valor: nota, formato: 'int', nota: 'Sim = 1, Parcialmente = 0,5, Não = 0' },
        { rotulo: 'Práticas completas', valor: n - falhas.length - parciais.length, formato: 'int' },
        { rotulo: 'Parciais', valor: parciais.length, formato: 'int' },
        { rotulo: 'Ausentes', valor: falhas.length, formato: 'int' }
      ],
      grafico: { titulo: 'Situação das dez práticas', formato: 'int', itens: [
        { rotulo: 'Completas', valor: n - falhas.length - parciais.length }, { rotulo: 'Parciais', valor: parciais.length }, { rotulo: 'Ausentes', valor: falhas.length }
      ] },
      conclusoes: nota >= 80 ? ['O processo tem boa base. O risco restante está nas práticas parciais.'] : ['Sem registro comparável entre entrega e devolução, a locadora tem pouco para sustentar uma cobrança de avaria.'],
      sugestoes: falhas.concat(parciais).slice(0, 5).map(function (q) { return 'Corrigir: ' + q; }),
      premissas: ['Pontuação sem pesos: todas as práticas valem o mesmo.', 'Autodiagnóstico: não substitui revisão jurídica do contrato.']
    };
  }
});
