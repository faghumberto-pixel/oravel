"""
Tela ilustrativa do módulo fiscal da Oravel, já preparada para IBS e CBS (reforma tributária).
Usa as classes dos mockups da home (mockups_v2.CSS_V2): v2-app, v2-card, v2-kpi, v2-rk, v2-al, v2-ia.
Todos os dados são fictícios e coerentes entre si:
  receita R$ 412 mil · compras com crédito R$ 118 mil · Lucro Presumido (PIS/Cofins 3,65%) · alíquota combinada estimada 9,21%
  hoje: 3,65% x 412 = R$ 15,0 mil | 2027: 9,21% x (412 - 118) = R$ 27,1 mil | crédito: 9,21% x 118 = R$ 10,9 mil
"""


def _kpi(rotulo, valor, nota, classe):
    return (f'<div class="v2-card v2-kpi"><small>{rotulo}</small><b>{valor}</b>'
            f'<span class="d {classe}">{nota}</span></div>')


def _barra(titulo, detalhe, pct, cor, tag=''):
    t = f'<span class="v2-tg {tag[0]}" style="justify-self:start">{tag[1]}</span>' if tag else ''
    return (f'<div class="r"><div class="l"><b>{titulo}</b><span>{detalhe}</span></div>'
            f'<div class="t"><i style="width:{pct}%;background:{cor}"></i></div>{t}</div>')


def hero_fiscal():
    kpis = (
        '<div class="v2-row hd-k">'
        + _kpi('Carga hoje (PIS/Cofins)', 'R$ 15,0 mil', '3,65% sobre a receita', 'up')
        + _kpi('Carga estimada em 2027', 'R$ 27,1 mil', '▲ R$ 12,0 mil a mais', 'dn')
        + _kpi('Crédito de CBS estimado', 'R$ 10,9 mil', 'sobre R$ 118 mil de compras', 'up')
        + _kpi('Receita sem crédito no cliente', 'R$ 123,6 mil', '30% da receita do mês', 'dn')
        + '</div>')

    prontidao = (
        '<div class="v2-card"><h6>Prontidão fiscal <span>para 1º de janeiro</span></h6><div class="v2-rk">'
        + _barra('Clientes com regime tributário', '92% · 46 de 50', 92, '#22c55e')
        + _barra('Notas de compra com crédito identificado', '81% · R$ 96 mil de R$ 118 mil', 81, '#22c55e')
        + _barra('Itens com classificação fiscal completa', '78% · 156 de 200', 78, '#f59e0b', ('tg-a', 'Completar'))
        + _barra('Contratos com cláusula de IBS e CBS', '64% · 18 de 28', 64, '#ef4444', ('tg-r', '10 sem cláusula'))
        + '</div></div>')

    fatura = (
        '<div class="v2-card"><h6>Fatura de locação <span>prévia 2027</span></h6><div class="v2-al">'
        '<div><span class="v2-tg tg-b">Base</span><span><b>Plataforma articulada 15 m · outubro</b><small>R$ 18.000,00 · Construtora Alvorada</small></span></div>'
        '<div><span class="v2-tg tg-a">CBS + IBS</span><span><b>R$ 1.657,80 destacados</b><small>Alíquota estimada de 9,21% · o cliente toma o crédito</small></span></div>'
        '<div><span class="v2-tg tg-r">Atenção</span><span><b>Engenharia Tupã: sem cláusula de IBS e CBS</b><small>Contrato de preço fechado, 8 meses de vigência</small></span></div>'
        '</div></div>')

    ia = (
        '<div class="v2-card v2-ia"><div class="ch">IA da Oravel</div>'
        '<p><b>10 contratos não dizem quem absorve o IBS e a CBS.</b> Eles somam R$ 1,9 mi em carteira. Sugestão: aditivo antes da virada.</p>'
        '<p style="margin-top:9px"><b>R$ 22 mil em compras ainda sem crédito identificado.</b> Falta o destaque do imposto em 14 notas.</p>'
        '<p style="margin-top:10px;color:#94a3b8;font-size:11px">Estimativa com alíquota de referência. O sistema calcula; a IA prioriza.</p></div>')

    return (
        '<div class="v2-app" role="img" aria-label="Módulo fiscal da Oravel preparado para a reforma tributária: carga hoje e em 2027, '
        'crédito de CBS estimado, prontidão fiscal, fatura com IBS e CBS destacados e contratos sem cláusula">'
        '<div class="v2-bar"><i></i><i></i><i></i><b>app.oravel.com.br · Fiscal · Reforma tributária</b><em>Preparado para 2027</em></div>'
        '<div class="v2-body">' + kpis
        + '<div class="v2-row hd-2">' + prontidao + fatura + '</div>'
        + '<div class="v2-row" style="grid-template-columns:minmax(0,1fr)">' + ia + '</div>'
        + '</div></div>')


if __name__ == '__main__':
    print(len(hero_fiscal()))
