#!/usr/bin/env python3
"""
Gera as 4 páginas de equipamentos do site oravel.com.br (NR-13, guindastes, plataformas
elevatórias e devolução com defeito) a partir de uma página de segmento já publicada
(usada só como molde de CSS, menu, rodapé e scripts).

Uso:
  python3 gerar_paginas_equipamentos.py <molde.html> <pasta_public_html>

O site NÃO está no git: a fonte de verdade é o HostGator. Baixe antes o molde
(software-manutencao-industrial/index.html) por SFTP. Este script só escreve arquivos
locais; publicar é decisão do dono do site.
"""
import html
import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import equip_art as ea  # noqa: E402
import equip_media as em  # noqa: E402
import processos as pr  # noqa: E402
import tema as tm  # noqa: E402

BASE = 'https://oravel.com.br'
WA = 'https://wa.me/5519999332615'
WA_FONE = '(19) 99933-2615'
EMAIL = 'contato@oravel.com.br'
SLOGAN = 'Desenvolvimento e tecnologia'

NAV_EQUIP = (
    '<div class="hd-dd"><a href="/guindastes-e-plataformas/" aria-haspopup="true">Equipamentos</a>'
    '<div class="hd-menu">'
    '<a href="/software-manutencao-guindastes/">Guindastes</a>'
    '<a href="/software-manutencao-munks/">Munks</a>'
    '<a href="/software-manutencao-plataformas-elevatorias/">Plataformas elevatórias</a>'
    '<a href="/software-manutencao-empilhadeiras/">Empilhadeiras</a>'
    '<a href="/software-manutencao-geradores/">Geradores</a>'
    '<a href="/software-manutencao-compressores/">Compressores</a>'
    '<a href="/software-manutencao-outros-equipamentos/">Outros equipamentos</a>'
    '</div></div>'
)
# Os processos não entram no menu nem no rodapé; ficam acessíveis pelo índice e por links nas páginas.
NAV_PROC = ''
FAVICON_BLOCO = ('<link rel="icon" href="/favicon.ico" sizes="any">\n<link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">\n'
                 '<link rel="icon" type="image/png" sizes="192x192" href="/favicon-192.png">\n<link rel="apple-touch-icon" href="/apple-touch-icon.png">\n')
LINK_NR13 = '<a href="/nr-13-inspecao-equipamentos/">Inspeções NR-13</a>'
LINK_DEV = '<a href="/equipamento-devolvido-com-defeito/">Devolução com defeito</a>'


# ---------------------------------------------------------------- blocos de página
def solve(eyebrow, h3, texto, resultado, mockhtml, rev=False):
    return (f'<div class="solve{" rev" if rev else ""}"><div><div class="eyebrow">{eyebrow}</div><h3>{h3}</h3>'
            f'<p>{texto}</p><p class="result">{resultado}</p></div>{mockhtml}</div>')


def secao(h2_id, eyebrow, h2, intro, corpo, surface=False):
    intro_html = f'<p>{intro}</p>' if intro else ''
    return (f'<section class="section{" section-surface" if surface else ""}" aria-labelledby="{h2_id}"><div class="wrap">'
            f'<div class="section-head"><div class="eyebrow">{eyebrow}</div><h2 id="{h2_id}">{h2}</h2>{intro_html}</div>{corpo}</div></section>')


def foto_larga(src, alt, legenda):
    return (f'<figure class="wide"><img src="/img/equip/{src}" alt="{html.escape(alt)}" loading="lazy" width="1600">'
            f'<figcaption>{html.escape(legenda)}</figcaption></figure>')


def quem_e(itens, porte_titulo, porte_txt):
    cards = ''.join(f'<div class="who"><h3>{a}</h3><p>{b}</p></div>' for a, b in itens)
    return (f'<div class="who-grid">{cards}</div>'
            f'<div class="porte"><strong>{porte_titulo}</strong> {porte_txt}</div>')


def faq(itens):
    return '<div class="faq">' + ''.join(
        f'<details><summary>{q}</summary><p>{a}</p></details>' for q, a in itens) + '</div>'


def veja_tambem(links):
    return ('<nav class="vt" aria-label="Veja também"><span>Veja também:</span>'
            + ''.join(f'<a href="{u}">{n}</a>' for u, n in links) + '</nav>')


LINKS = {
    'nr13': ('/nr-13-inspecao-equipamentos/', 'Inspeções NR-13'),
    'guindaste': ('/software-manutencao-guindastes/', 'Guindastes'),
    'plataforma': ('/software-manutencao-plataformas-elevatorias/', 'Plataformas elevatórias'),
    'munk': ('/software-manutencao-munks/', 'Munks'),
    'empilhadeira': ('/software-manutencao-empilhadeiras/', 'Empilhadeiras'),
    'gerador': ('/software-manutencao-geradores/', 'Geradores'),
    'compressor': ('/software-manutencao-compressores/', 'Compressores'),
    'outros': ('/software-manutencao-outros-equipamentos/', 'Outros equipamentos'),
    'devolucao': ('/equipamento-devolvido-com-defeito/', 'Equipamento devolvido com defeito'),
    'locadoras': ('/erp-cmms/', 'Oravel para locadoras'),
    'p_manut': ('/processo-manutencao/', 'Processo de manutenção'),
    'p_equipes': ('/processo-gestao-de-equipes/', 'Gestão de equipes'),
    'p_estoque': ('/processo-gestao-de-estoques/', 'Gestão de estoques'),
    'p_logistica': ('/processo-logistica/', 'Logística'),
    'p_cobranca': ('/processo-cobranca-automatica/', 'Cobrança automática'),
    'manutencao': ('/software-manutencao-industrial/', 'Manutenção'),
}

FOOTER_NAVY = """<style data-site="footer-navy">
footer.ifoot[data-site="footer"] { background:#0f172a !important; border-top:0 !important; color:#e2e8f0 !important; }
footer.ifoot .ft-grid { grid-template-columns:1.3fr 1.2fr 1.2fr 1fr 1fr; }
footer.ifoot .ft-col h4, footer.ifoot .ft-brand { color:#fff !important; }
footer.ifoot .ft-col a { color:#cbd5e1 !important; }
footer.ifoot .ft-col a:hover { color:#f97316 !important; }
footer.ifoot .ft-col p, footer.ifoot .ft-bottom p, footer.ifoot .ft-bottom { color:#94a3b8 !important; }
footer.ifoot .ft-bottom { border-top-color:rgba(255,255,255,.14); }
footer.ifoot .ft-col .slogan { color:#94a3b8 !important; }
@media (max-width:768px) { footer.ifoot .ft-grid { grid-template-columns:1fr; } }
</style>
"""

EXTRA_CSS = """<style data-site="equip-css">
.faq { display: grid; gap: 10px; max-width: 820px; }
.faq details { background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 0 20px; }
.faq summary { cursor: pointer; font-family: "Manrope", sans-serif; font-weight: 700; font-size: 16px; padding: 18px 0; list-style: none; }
.faq summary::-webkit-details-marker { display: none; }
.faq summary::after { content: "+"; float: right; color: var(--accent); font-size: 22px; line-height: 1; }
.faq details[open] summary::after { content: "–"; }
.faq p { font-size: 15px; color: var(--ink-soft); line-height: 1.65; padding-bottom: 18px; }
.porte { margin-top: 18px; background: var(--accent-soft); border-radius: 12px; padding: 16px 20px; font-size: 15px; line-height: 1.6; color: var(--ink); }
.vt { display: flex; flex-wrap: wrap; gap: 8px 18px; font-size: 14px; margin-top: 30px; color: var(--ink-soft); }
.vt a { color: var(--accent-ink); font-weight: 600; }
.hero-grid { display: grid; grid-template-columns: 1fr; gap: 36px; align-items: center; }
.hero-art svg { width: 100%; height: auto; display: block; filter: drop-shadow(0 30px 50px rgba(0,0,0,.45)); }
@media (min-width: 960px) { .hero-grid { grid-template-columns: 1.02fr .98fr; gap: 48px; } }
.wide { margin: 0; } .wide img { width: 100%; height: auto; display: block; border-radius: 18px; border: 1px solid var(--border); box-shadow: 0 30px 60px -36px rgba(15,23,42,.45); }
.wide figcaption { font-size: 12.5px; color: var(--ink-faint); margin-top: 10px; text-align: center; }
.hero-art img, .hero-art video { width: 100%; height: auto; display: block; border-radius: 22px; box-shadow: 0 30px 50px -20px rgba(0,0,0,.5); }
.lead-p { font-size: 18px; line-height: 1.65; max-width: 62ch; }
.texto { max-width: 760px; font-size: 16px; line-height: 1.7; color: var(--ink-soft); }
.texto p + p { margin-top: 14px; }
.hero .sub { color: rgba(226,232,240,.88) !important; }
.slogan { font-size: 12.5px; color: var(--ink-faint); margin-top: 6px; }
</style>"""


def ld(pagina):
    url = BASE + pagina['slug']
    grafo = {
        '@context': 'https://schema.org',
        '@graph': [
            {'@type': 'WebPage', '@id': url + '#webpage', 'url': url, 'name': pagina['title'],
             'description': pagina['desc'], 'inLanguage': 'pt-BR', 'isPartOf': {'@id': BASE + '/#website'}},
            {'@type': 'SoftwareApplication', 'name': 'Oravel', 'applicationCategory': 'BusinessApplication',
             'operatingSystem': 'Web', 'description': pagina['app_desc'], 'url': url,
             'publisher': {'@type': 'Organization', 'name': 'Oravel Desenvolvimento de Software LTDA', 'url': BASE + '/'}},
        ],
    }
    if pagina['faq']:
        grafo['@graph'].append({'@type': 'FAQPage', 'mainEntity': [
            {'@type': 'Question', 'name': re.sub('<[^>]+>', '', q),
             'acceptedAnswer': {'@type': 'Answer', 'text': re.sub('<[^>]+>', '', a)}}
            for q, a in pagina['faq']]})
    return '<script type="application/ld+json">' + json.dumps(grafo, ensure_ascii=False) + '</script>'


# ---------------------------------------------------------------- conteúdo das páginas
CTA_FIM = ('Teste a Oravel por 30 dias, sem custo, com acompanhamento, treinamento e implementação inclusos. '
           'A Oravel complementa o sistema que a sua locadora já usa; você não precisa trocar nada para começar.')

POSICIONAMENTO = ('A Oravel é um sistema de gestão de manutenção (CMMS) que trabalha ao lado do sistema que a sua '
                  'locadora já usa. Você mantém o que funciona e ganha o controle que faltava.')


def pagina_nr13():
    faqs = [
        ('A Oravel substitui o profissional que faz a inspeção?',
         'Não. A inspeção e o laudo continuam com o profissional habilitado. A Oravel cuida de manter datas, laudos e avisos organizados para a sua locadora.'),
        ('Todo equipamento da minha locadora entra na NR-13?',
         'Não. A NR-13 trata de caldeiras, vasos de pressão, tubulações e tanques metálicos de armazenamento. Quais dos seus equipamentos se enquadram é uma definição do profissional habilitado da sua empresa.'),
        ('Vou receber aviso antes de uma inspeção vencer?',
         'Sim. Os alertas de vencimento chegam antes da data e também quando ela passa, para ninguém ser pego de surpresa.'),
        ('Onde ficam guardados os laudos?',
         'Junto do registro da inspeção de cada equipamento, prontos para consulta quando alguém pedir.'),
        ('Preciso trocar o sistema que já uso?',
         'Não. A Oravel complementa o sistema que a sua locadora já usa.'),
    ]
    corpo_flow = (
        solve('Agendamento', 'Cada equipamento sabe a sua próxima inspeção',
              'Chega de planilha e de lembrete no calendário de alguém. A agenda das inspeções fica num só lugar, à vista de quem decide.',
              'Resultado: você enxerga o mês antes de ele chegar.', em.NR13_SCENES[0]())
        + solve('Relatório de inspeção', 'O laudo mora junto do equipamento',
                'Quando o cliente, o auditor ou a seguradora pedir, o documento está a um clique, com responsável técnico e resultado.',
                'Resultado: menos correria, mais segurança na hora de provar.', em.NR13_SCENES[1](), True)
        + solve('Próxima data', 'Feita a inspeção, a seguinte já está marcada',
                'A cada registro, a próxima data fica definida e acompanhada. Nada depende de memória.',
                'Resultado: inspeção vencida deixa de ser surpresa.', em.NR13_SCENES[2]())
        + solve('Alertas de vencimento', 'Um painel que mostra o que merece atenção hoje',
                'Os alertas de vencimento avisam antes de vencer. Você abre o painel e vê, sem perguntar para ninguém.',
                'Resultado: tranquilidade para locar equipamentos com a documentação em dia.', em.NR13_SCENES[3](), True)
    )
    corpo = (
        secao('o-que-t', 'A norma', 'O que é a NR-13 e quais equipamentos entram?', None,
              '<div class="texto"><p>A NR-13 trata de caldeiras, vasos de pressão, tubulações e tanques metálicos de armazenamento. '
              'Nem todo equipamento de locação se enquadra, e a classificação de cada um é definida pelo profissional habilitado da sua empresa.</p>'
              '<!-- VALIDAR (dono do site): exemplo abaixo. Confirmar com o responsável técnico antes de publicar. -->'
              '<p>Em locadoras, o caso mais comum são equipamentos que têm reservatório de ar comprimido, como alguns compressores.</p></div>')
        + secao('como-t', 'Como a Oravel ajuda', 'Como manter as inspeções NR-13 em dia sem depender de planilha?',
                'Da agenda ao aviso de vencimento, do jeito que a sua equipe realmente trabalha.', corpo_flow, True)
        + secao('porque-t', 'Por que importa', 'Por que isso pesa tanto para quem aluga equipamentos?', None,
                '<div class="texto"><p>Equipamento com inspeção vencida não pode ir para o cliente. Cada dia parado é receita perdida, e cada documento que não aparece na hora é desgaste com o contrato.</p>'
                '<p>Quem organiza isso antes ganha o que mais vale numa locadora: confiança de quem aluga.</p></div>')
        + secao('quem-t', 'Para quem é', 'Para quem é e qual o porte ideal?', None,
                quem_e([('Locadoras com equipamentos de pressão', 'Frota que inclui compressores e outros equipamentos com reservatório.'),
                        ('Gestores de manutenção', 'Quem precisa responder por prazos e documentos de vários equipamentos.'),
                        ('Responsáveis técnicos', 'Quem assina e quer o histórico organizado.'),
                        ('Donos de locadora', 'Quem quer dormir tranquilo com a conformidade em dia.')],
                       'Porte ideal:',
                       'locadoras de pequeno e médio porte, com equipamentos sujeitos à NR-13 na frota. <!-- VALIDAR porte ideal -->'), True)
        + secao('faq-t', 'Perguntas frequentes', 'Perguntas frequentes sobre NR-13 e a Oravel', None, faq(faqs))
    )
    return dict(slug='/nr-13-inspecao-equipamentos/', origem='nr13', hero=em.HERO['nr13'](), eyebrow='Inspeções NR-13',
                title='Controle de inspeções NR-13 para locadoras | Oravel',
                desc='Inspeção NR-13 vencida para a sua locadora? Organize datas, laudos e alertas de vencimento com a Oravel, o sistema de gestão de manutenção que complementa o seu.',
                h1='Inspeções NR-13 sempre em dia na sua locadora.',
                lead='Inspeção NR-13 vencida é equipamento parado e risco para o seu negócio. Com a Oravel, cada equipamento tem a sua data, o seu laudo e o seu aviso de vencimento, tudo num só lugar. Ela trabalha ao lado do sistema que a sua locadora já usa.',
                corpo=corpo, faq=faqs, vt=['guindaste', 'plataforma', 'devolucao', 'locadoras'],
                app_desc='Sistema de gestão de manutenção (CMMS) para locadoras, com controle de inspeções NR-13, laudos e alertas de vencimento.',
                cta_h='Deixe a NR-13 sob controle na sua locadora')


EQUIPS = {
    'guindaste': dict(nome='Guindaste 01', sing='guindaste', plural='guindastes', pagina='Guindastes', slug='/software-manutencao-guindastes/',
        title='Software de manutenção para guindastes de locação | Oravel',
        desc='Manutenção preventiva por horímetro, ordem de serviço por equipamento e histórico individual para a sua frota de guindastes. Conheça a Oravel.',
        h1='Manutenção de guindastes sob controle na sua locadora.',
        lead='Guindaste parado é contrato em risco. A Oravel cuida da manutenção preventiva por horímetro, das ordens de serviço e do histórico de cada guindaste, para ele estar pronto quando o cliente precisar. Ela complementa o sistema que a sua locadora já usa.',
        quem=[('Locadoras de guindastes', 'Frotas em que cada máquina precisa estar pronta e documentada.'), ('Locadoras de equipamentos de elevação de carga', 'Quem opera guindastes e outros equipamentos de içamento.'), ('Gestores de manutenção', 'Quem responde pela disponibilidade da frota.'), ('Donos de locadora', 'Quem quer menos parada e mais previsibilidade.')],
        porte='locadoras de pequeno e médio porte, com frota a partir de poucos guindastes.',
        h2a='Como manter guindastes de locação sempre prontos para o cliente?', cta='Veja a Oravel cuidando da sua frota de guindastes',
        vt=['munk', 'plataforma', 'nr13', 'devolucao'], extra=None),
    'munk': dict(nome='Munk 01', sing='munk', plural='munks', pagina='Munks', slug='/software-manutencao-munks/',
        title='Software de manutenção para caminhões munk de locação | Oravel',
        desc='Manutenção preventiva por horímetro, ordem de serviço por equipamento e histórico individual para a sua frota de munks. Conheça a Oravel.',
        h1='Munks sempre prontos para o próximo serviço.',
        lead='Munk parado é serviço cancelado e cliente insatisfeito. A Oravel organiza a manutenção preventiva, as ordens de serviço e o histórico de cada munk, para ele sair da base pronto e voltar com tudo registrado. Ela complementa o sistema que a sua locadora já usa.',
        quem=[('Locadoras de munks', 'Frotas em que cada caminhão precisa estar pronto e documentado.'), ('Locadoras com frota mista', 'Quem opera munks junto com guindastes e outros equipamentos.'), ('Gestores de manutenção', 'Quem responde pela disponibilidade da frota.'), ('Donos de locadora', 'Quem quer menos parada e mais previsibilidade.')],
        porte='locadoras de pequeno e médio porte, com frota a partir de poucos munks.',
        h2a='Como manter munks de locação sempre prontos e comprovar a entrega e a devolução?', cta='Veja a Oravel cuidando da sua frota de munks',
        vt=['guindaste', 'plataforma', 'devolucao', 'locadoras'], extra=None),
    'plataforma': dict(nome='Plataforma 01', sing='plataforma', plural='plataformas elevatórias', pagina='Plataformas elevatórias', slug='/software-manutencao-plataformas-elevatorias/',
        title='Software de manutenção para plataformas elevatórias | Oravel',
        desc='Manutenção preventiva por horímetro, checklist com fotos e assinatura digital e histórico individual para a sua frota de plataformas elevatórias. Conheça a Oravel.',
        h1='Plataformas elevatórias sempre prontas e documentadas.',
        lead='Plataforma elevatória parada ou devolvida com dano sem prova é prejuízo certo. A Oravel organiza a manutenção preventiva, o checklist com fotos e assinatura digital e o histórico de cada plataforma. Ela complementa o sistema que a sua locadora já usa.',
        quem=[('Locadoras de plataformas elevatórias', 'Frotas de tesoura, articuladas e telescópicas.'), ('Locadoras de acesso em altura', 'Quem entrega e recebe equipamentos todos os dias.'), ('Gestores de manutenção', 'Quem precisa de previsibilidade na frota.'), ('Donos de locadora', 'Quem quer provar o estado em que cada plataforma saiu e voltou.')],
        porte='locadoras de pequeno e médio porte, com frota a partir de dezenas de plataformas.',
        h2a='Como manter plataformas elevatórias prontas e comprovar a entrega e a devolução?', cta='Veja a Oravel cuidando da sua frota de plataformas',
        vt=['empilhadeira', 'guindaste', 'nr13', 'devolucao'],
        extra=('Serve para plataformas tesoura, articuladas e telescópicas?', 'Sim. A Oravel atende os principais tipos de plataforma elevatória usados em locação.')),
    'empilhadeira': dict(nome='Empilhadeira 01', sing='empilhadeira', plural='empilhadeiras', pagina='Empilhadeiras', slug='/software-manutencao-empilhadeiras/',
        title='Software de manutenção para empilhadeiras de locação | Oravel',
        desc='Manutenção preventiva por horímetro, ordem de serviço por equipamento e histórico individual para a sua frota de empilhadeiras. Conheça a Oravel.',
        h1='Empilhadeiras sempre disponíveis, sem parada surpresa.',
        lead='Empilhadeira parada derruba a operação do seu cliente e a sua reputação. A Oravel organiza a manutenção preventiva, as ordens de serviço e o histórico de cada empilhadeira, para ela estar pronta quando for alugada. Ela complementa o sistema que a sua locadora já usa.',
        quem=[('Locadoras de empilhadeiras', 'Frotas elétricas, a combustão e de movimentação em geral.'), ('Locadoras de equipamentos de armazém', 'Quem aluga empilhadeiras e transpaleteiras.'), ('Gestores de manutenção', 'Quem precisa de previsibilidade na frota.'), ('Donos de locadora', 'Quem quer menos parada e mais receita por equipamento.')],
        porte='locadoras de pequeno e médio porte, com frota a partir de dezenas de empilhadeiras.',
        h2a='Como manter empilhadeiras de locação sempre disponíveis para o cliente?', cta='Veja a Oravel cuidando da sua frota de empilhadeiras',
        vt=['gerador', 'plataforma', 'devolucao', 'locadoras'],
        extra=('Serve para empilhadeiras elétricas e a combustão?', 'Sim. A Oravel atende empilhadeiras elétricas, a combustão e outros equipamentos de movimentação.')),
    'gerador': dict(nome='Gerador 01', sing='gerador', plural='geradores', pagina='Geradores', slug='/software-manutencao-geradores/',
        title='Software de manutenção para geradores de locação | Oravel',
        desc='Manutenção preventiva por horímetro, ordem de serviço por equipamento e histórico individual para a sua frota de geradores. Conheça a Oravel.',
        h1='Geradores prontos quando a energia não pode faltar.',
        lead='Gerador que falha no evento ou na obra do cliente custa contrato e confiança. A Oravel organiza a manutenção preventiva por horímetro, as ordens de serviço e o histórico de cada gerador, para ele partir da base pronto para trabalhar. Ela complementa o sistema que a sua locadora já usa.',
        quem=[('Locadoras de geradores', 'Frotas de várias potências, para obra, evento e emergência.'), ('Locadoras com frota mista', 'Quem opera geradores junto com compressores e outros equipamentos.'), ('Gestores de manutenção', 'Quem responde pela disponibilidade da frota.'), ('Donos de locadora', 'Quem quer menos parada e mais previsibilidade.')],
        porte='locadoras de pequeno e médio porte, com frota a partir de poucos geradores.',
        h2a='Como manter geradores de locação sempre prontos para o cliente?', cta='Veja a Oravel cuidando da sua frota de geradores',
        vt=['empilhadeira', 'nr13', 'devolucao', 'locadoras'], extra=None),
    'compressor': dict(nome='Compressor 01', sing='compressor', plural='compressores', pagina='Compressores', slug='/software-manutencao-compressores/',
        title='Software de manutenção para compressores de locação | Oravel',
        desc='Manutenção preventiva por horímetro, ordem de serviço por equipamento e histórico individual para a sua frota de compressores. Conheça a Oravel.',
        h1='Compressores prontos, revisados e com a documentação em dia.',
        lead='Compressor parado ou com a inspeção vencida tira o equipamento do mercado e arranha a confiança do cliente. A Oravel organiza a manutenção preventiva, as ordens de serviço e o histórico de cada compressor, e ajuda a manter as inspeções em dia. Ela complementa o sistema que a sua locadora já usa.',
        quem=[('Locadoras de compressores', 'Frotas de compressores de ar para obra, indústria e serviços.'), ('Locadoras com frota mista', 'Quem opera compressores junto com geradores e outros equipamentos.'), ('Gestores de manutenção', 'Quem responde pela disponibilidade da frota.'), ('Donos de locadora', 'Quem quer menos parada e mais previsibilidade.')],
        porte='locadoras de pequeno e médio porte, com frota a partir de poucos compressores.',
        h2a='Como manter compressores de locação sempre prontos e com as inspeções em dia?', cta='Veja a Oravel cuidando da sua frota de compressores',
        vt=['nr13', 'gerador', 'devolucao', 'locadoras'],
        extra=('Compressor entra na NR-13?', 'Compressores com reservatório de ar comprimido podem se enquadrar na NR-13. Quem define é o profissional habilitado da sua empresa, e a Oravel ajuda a manter datas, laudos e alertas de vencimento organizados. <!-- VALIDAR (dono do site): afirmação sobre compressores e NR-13. -->')),
    'outros': dict(nome='Equipamento 01', sing='equipamento', plural='equipamentos', pagina='Outros equipamentos', slug='/software-manutencao-outros-equipamentos/',
        title='Software de manutenção para qualquer equipamento de locação | Oravel',
        desc='Manutenção preventiva por horímetro, ordem de serviço por equipamento e histórico individual para qualquer equipamento da sua locadora. Conheça a Oravel.',
        h1='Qualquer equipamento da sua frota, sob o mesmo controle.',
        lead='Sua frota não é feita só de um tipo de máquina, e a manutenção não pode ser uma colcha de retalhos. A Oravel reúne a manutenção preventiva, as ordens de serviço e o histórico de cada equipamento, seja ele qual for. Ela complementa o sistema que a sua locadora já usa.',
        quem=[('Locadoras de frota variada', 'Escavadeiras, torres de iluminação, bombas e outros equipamentos.'), ('Locadoras em crescimento', 'Quem está ampliando a frota e precisa de organização.'), ('Gestores de manutenção', 'Quem lida com vários tipos de equipamento ao mesmo tempo.'), ('Donos de locadora', 'Quem quer a frota inteira em um só lugar.')],
        porte='locadoras de pequeno e médio porte, com frota de vários tipos de equipamento.',
        h2a='Como manter toda a frota, de qualquer tipo, sob o mesmo controle?', cta='Veja a Oravel cuidando de toda a sua frota',
        vt=['guindaste', 'empilhadeira', 'devolucao', 'locadoras'],
        extra=('Serve para equipamentos que não estão listados no site?', 'Sim. A Oravel atende qualquer equipamento da frota, de qualquer tipo. Fale com a gente para ver o seu caso.')),
}


def pagina_equip(tipo):
    c = EQUIPS[tipo]
    nome, plural, sing = c['nome'], c['plural'], c['sing']
    faqs = [
        ('A Oravel funciona junto com o sistema de locação que já uso?',
         'Sim. A Oravel complementa o sistema que a sua locadora já usa. Você não precisa trocar nada para começar.'),
        ('Como a manutenção preventiva por horímetro ajuda a locadora?',
         f'Ela faz a revisão chegar antes da quebra, no momento certo para cada equipamento. Menos {plural} parados e menos reparo caro.'),
        ('Consigo ver tudo o que aconteceu com um equipamento?',
         'Sim. Cada equipamento tem o seu histórico individual, com ordens de serviço, avarias e checklists.'),
        ('O checklist de entrega e devolução tem fotos e assinatura?',
         'Sim. O checklist com fotos e assinatura digital deixa registrada a condição do equipamento nos dois momentos.'),
        ('É possível evitar que um equipamento com problema seja locado?',
         'Sim. É possível bloquear o equipamento em manutenção para locação, para ninguém alugar por engano.'),
    ]
    if c['extra']:
        faqs.append(c['extra'])
    flow = (
        solve('Manutenção preventiva', 'Revisão por horímetro ou por tempo, sem depender de memória',
              f'A manutenção preventiva por horímetro ou por tempo mantém cada {sing} saudável e a sua locadora longe de parada inesperada.',
              'Resultado: menos quebra no meio da locação.', em.EQUIP_SCENES[0](tipo))
        + solve('Ordem de serviço por equipamento', 'Cada equipamento com as suas ordens de serviço',
                'Quem fez, quando e quanto custou, separado por equipamento e fácil de achar.',
                'Resultado: clareza sobre o que cada máquina consome.', em.EQUIP_SCENES[1](tipo), True)
        + solve('Histórico individual', 'A vida inteira do equipamento em uma tela',
                'O histórico individual por equipamento conta tudo o que já aconteceu com ele. É o argumento na hora de negociar, vender ou renovar.',
                'Resultado: decisão com base em fato, não em lembrança.', em.EQUIP_SCENES[2](tipo))
        + solve('Controle de avarias', 'Avaria registrada, com prova e com responsável',
                'As avarias deixam de virar discussão: ficam registradas com fotos, data e acompanhamento, com trilha de auditoria de quem mexeu em quê.',
                'Resultado: menos prejuízo na devolução.', em.EQUIP_SCENES[3](tipo), True)
        + solve('Checklist com fotos e assinatura digital', 'Entrega e devolução comprovadas',
                'O checklist com fotos e assinatura digital documenta o estado do equipamento na saída e na volta.',
                'Resultado: sem dúvida sobre o estado em que ele saiu e voltou.', em.EQUIP_SCENES[4](tipo))
    )
    corpo = (
        secao('como-t', 'Como a Oravel ajuda', c['h2a'], 'Do plano de revisão ao checklist assinado, tudo por equipamento.', flow, True)
        + (secao('app-t', 'No celular', 'Como o técnico registra tudo na hora, no pátio ou na obra?', None,
                 foto_larga('celular.jpg', 'Telas do app do técnico: ordem de serviço, leitura de QR e checklist com assinatura digital',
                            'Telas ilustrativas do app do técnico.')) if tipo == 'gerador' else '')
        + secao('porque-t', 'Por que importa', 'Por que isso muda o resultado da locadora?', None,
                '<div class="texto"><p>Equipamento disponível e documentado aluga mais e dá menos dor de cabeça. '
                'Com avarias registradas e o equipamento em manutenção bloqueado para locação, a sua operação ganha ritmo e a sua margem agradece.</p></div>'
                + ('' if tipo == 'gerador' else '<div style="margin-top:28px">' + foto_larga('equipe.jpg', 'Equipe de manutenção registrando o serviço com tablets, ao lado de um caminhão', 'Equipe registrando a manutenção em campo.') + '</div>'))
        + secao('quem-t', 'Para quem é', 'Para quem é e qual o porte ideal?', None,
                quem_e(c['quem'], 'Porte ideal:', c['porte'] + ' <!-- VALIDAR porte ideal -->'), True)
        + secao('faq-t', 'Perguntas frequentes', f'Perguntas frequentes sobre {plural} e a Oravel', None, faq(faqs))
    )
    return dict(slug=c['slug'], origem=tipo, hero=em.HERO[tipo](), eyebrow=c['pagina'], title=c['title'], desc=c['desc'], h1=c['h1'], lead=c['lead'],
                corpo=corpo, faq=faqs, vt=c['vt'],
                app_desc=f'Sistema de gestão de manutenção (CMMS) para locadoras de {plural}: manutenção preventiva por horímetro, ordem de serviço por equipamento e histórico individual.',
                cta_h=c['cta'])


PROC_PAGES = {
    'manutencao': dict(slug='/processo-manutencao/', origem='manutencao', eyebrow='Processo de manutenção', cenas=pr.cenas_manutencao,
        title='Gestão da manutenção de equipamentos de locação | Oravel',
        desc='Manutenção preventiva por horímetro ou tempo, ordem de serviço por equipamento e histórico individual, com checklist com fotos e assinatura digital. Conheça a Oravel.',
        h1='Manutenção no prazo, com prova e com custo à vista.',
        lead='Manutenção que depende de memória e de papel vira equipamento parado e cliente reclamando. A Oravel organiza a manutenção preventiva, as ordens de serviço e o histórico de cada equipamento, para a revisão chegar antes da quebra. Ela complementa o sistema que a sua locadora já usa.',
        passos=[('Plano preventivo', 'A revisão chega antes da quebra', 'A manutenção preventiva por horímetro ou por tempo avisa quando cada equipamento precisa de atenção.', 'Resultado: menos parada no meio da locação.'),
                ('Ordem de serviço por equipamento', 'Todas as ordens num só painel', 'Cada ordem tem dono, status e andamento, separada por equipamento.', 'Resultado: você acompanha a oficina sem ligar para ninguém.'),
                ('Checklist com fotos e assinatura digital', 'O serviço fica comprovado', 'O checklist com fotos e assinatura digital documenta o que foi feito, antes e depois.', 'Resultado: menos discussão com o cliente.'),
                ('Custo e histórico', 'Quanto custou cada equipamento', 'Peças, mão de obra e custo total ficam no histórico individual por equipamento.', 'Resultado: decisão com base em fato, não em lembrança.')],
        h2a='Como organizar a manutenção da frota sem depender de planilha?',
        why='Uma frota bem mantida aluga mais, quebra menos e gasta menos. O que separa uma locadora da outra, quase sempre, é a organização da manutenção.',
        quem=[('Gestores de manutenção', 'Quem precisa de prazo, prova e custo na mão.'), ('Oficinas de locadoras', 'Equipes que atendem várias ordens ao mesmo tempo.'), ('Donos de locadora', 'Quem quer a frota disponível e documentada.'), ('Locadoras em crescimento', 'Quem está ampliando a frota e precisa de ordem.')],
        porte='locadoras de pequeno e médio porte, com frota própria e oficina.',
        faq=[('A Oravel funciona junto com o sistema que já uso?', 'Sim. A Oravel complementa o sistema que a sua locadora já usa. Você não precisa trocar nada para começar.'),
             ('Como funciona a manutenção preventiva?', 'Você define o plano por horímetro ou por tempo, e a Oravel avisa quando a revisão se aproxima.'),
             ('Consigo ver o custo de cada equipamento?', 'Sim. Peças, mão de obra e custo total ficam registrados por equipamento.'),
             ('O técnico registra o serviço pelo celular?', 'Sim. O técnico registra a ordem de serviço, as fotos e a assinatura pelo celular.'),
             ('Dá para bloquear um equipamento em manutenção para locação?', 'Sim. É possível bloquear o equipamento em manutenção para locação, para ninguém alugar por engano.')],
        vt=['p_equipes', 'p_estoque', 'guindaste', 'devolucao'], cta='Leve esse controle para a manutenção da sua locadora'),
    'equipes': dict(slug='/processo-gestao-de-equipes/', origem='equipes', eyebrow='Gestão de equipes', cenas=pr.cenas_equipes,
        title='Gestão de equipes de manutenção e campo | Oravel',
        desc='Agenda, alocação e carga de trabalho dos técnicos, com ordens do dia no celular e indicadores de desempenho. Conheça a Oravel.',
        h1='Cada técnico no lugar certo, com a carga certa.',
        lead='Equipe sobrecarregada atrasa serviço, equipe ociosa custa caro e você só descobre quando o cliente reclama. A Oravel mostra quem está fazendo o quê, quem está cheio e quem está livre, e leva a ordem do dia para o celular do técnico. Ela complementa o sistema que a sua locadora já usa.',
        passos=[('Agenda e alocação', 'Todo mundo sabe onde estar', 'A agenda da equipe mostra a alocação de cada técnico, sem choque entre serviços.', 'Resultado: o serviço certo com a pessoa certa.'),
                ('Carga da equipe', 'Quem está cheio, quem está livre', 'Em uma tela você enxerga quem está sobrecarregado e quem pode receber mais trabalho.', 'Resultado: carga equilibrada, sem depender de ligação.'),
                ('App do técnico', 'A ordem do dia no bolso', 'O técnico vê as ordens do dia no celular e registra o serviço na hora.', 'Resultado: menos papel e menos recado perdido.'),
                ('Desempenho', 'Equipe que melhora com dados', 'Indicadores de desempenho e retrabalho mostram onde a equipe vai bem e onde precisa de apoio.', 'Resultado: gestão com fato, não com achismo.')],
        h2a='Como saber quem está sobrecarregado e quem está livre na equipe?',
        why='Uma equipe bem distribuída entrega mais com o mesmo time. O ganho está em enxergar a carga de cada pessoa antes do atraso.',
        quem=[('Gestores de manutenção', 'Quem distribui o serviço todos os dias.'), ('Coordenadores de campo', 'Quem acompanha técnicos em obras e clientes.'), ('Donos de locadora', 'Quem quer produtividade sem aumentar a equipe.'), ('Líderes de oficina', 'Quem precisa equilibrar a carga entre os técnicos.')],
        porte='locadoras de pequeno e médio porte, com equipe técnica de poucos a dezenas de pessoas.',
        faq=[('A Oravel funciona junto com o sistema que já uso?', 'Sim. A Oravel complementa o sistema que a sua locadora já usa.'),
             ('Consigo ver a carga de cada técnico?', 'Sim. Uma tela compara os técnicos lado a lado, mostrando quem está sobrecarregado e quem está livre.'),
             ('O técnico usa o celular?', 'Sim. As ordens do dia ficam no celular do técnico, onde ele registra o serviço.'),
             ('Dá para acompanhar desempenho?', 'Sim. Há indicadores por técnico e uma visão de retrabalho.'),
             ('A agenda evita choque de serviços?', 'A agenda da equipe mostra a alocação de cada técnico, o que ajuda a distribuir o trabalho sem sobreposição.')],
        vt=['p_manut', 'p_logistica', 'p_estoque', 'locadoras'], cta='Leve esse controle para a sua equipe'),
    'estoque': dict(slug='/processo-gestao-de-estoques/', origem='estoque', eyebrow='Gestão de estoques', cenas=pr.cenas_estoque,
        title='Gestão de estoques e almoxarifado para locadoras | Oravel',
        desc='Níveis de estoque com alerta de mínimo, reposição, recebimento e peças por ordem de serviço. Conheça a Oravel, sistema de gestão de manutenção.',
        h1='A peça certa, na hora certa, sem estoque parado.',
        lead='Peça que falta para o equipamento e peça que sobra na prateleira custam dinheiro do mesmo jeito. A Oravel acompanha o estoque, avisa quando algo está abaixo do mínimo e liga cada peça usada ao equipamento certo. Ela complementa o sistema que a sua locadora já usa.',
        passos=[('Níveis e mínimo', 'A falta aparece antes de acontecer', 'O estoque mínimo de cada item gera alerta antes de o almoxarifado zerar.', 'Resultado: nenhum equipamento parado esperando peça.'),
                ('Reposição', 'Da falta ao pedido', 'A requisição de reposição segue para compra, sem recado solto.', 'Resultado: reposição organizada, sem correria.'),
                ('Recebimento', 'Entrada conferida', 'O que chega é conferido contra o pedido de compra.', 'Resultado: menos erro e menos surpresa na fatura.'),
                ('Peças na ordem de serviço', 'Cada peça no equipamento certo', 'As peças usadas ficam registradas na ordem de serviço, com o custo.', 'Resultado: você sabe quanto cada equipamento consome.')],
        h2a='Como evitar que falte peça e que sobre estoque parado?',
        why='Estoque é capital parado. Controlar o que entra, o que sai e o que está acabando libera caixa e evita equipamento esperando peça.',
        quem=[('Almoxarifes', 'Quem controla entradas e saídas todos os dias.'), ('Gestores de manutenção', 'Quem depende da peça para liberar o equipamento.'), ('Compradores', 'Quem precisa repor no prazo certo.'), ('Donos de locadora', 'Quem quer menos capital parado.')],
        porte='locadoras de pequeno e médio porte, com almoxarifado próprio.',
        faq=[('A Oravel funciona junto com o sistema que já uso?', 'Sim. A Oravel complementa o sistema que a sua locadora já usa.'),
             ('Recebo alerta de estoque mínimo?', 'Sim. Cada item pode ter um estoque mínimo, e o sistema sinaliza quando o saldo fica abaixo.'),
             ('Consigo controlar mais de um almoxarifado?', 'Sim. O estoque pode ser acompanhado por almoxarifado, com transferência entre eles.'),
             ('Dá para saber o custo de peças por equipamento?', 'Sim. As peças usadas ficam registradas na ordem de serviço do equipamento.'),
             ('A compra faz parte do processo?', 'Sim. A requisição de reposição segue para compra, e o recebimento é conferido contra o pedido.')],
        vt=['p_manut', 'p_logistica', 'p_cobranca', 'locadoras'], cta='Leve esse controle para o estoque da sua locadora'),
    'logistica': dict(slug='/processo-logistica/', origem='logistica', eyebrow='Logística', cenas=pr.cenas_logistica,
        title='Logística de equipamentos de locação: retirada, entrega e pátio | Oravel',
        desc='Solicitações de retirada e entrega, checklist de saída com fotos e assinatura digital e conferência na chegada ao pátio. Conheça a Oravel.',
        h1='Do pátio ao cliente e de volta, com tudo registrado.',
        lead='Equipamento que sai sem checklist volta com discussão, e pedido de retirada perdido no WhatsApp vira cliente irritado. A Oravel organiza as solicitações, a saída com fotos e assinatura e a conferência na volta ao pátio. Ela complementa o sistema que a sua locadora já usa.',
        passos=[('Retiradas e entregas', 'Todo pedido com status', 'As solicitações de retirada e entrega ficam registradas e acompanhadas, do pedido à conclusão.', 'Resultado: nenhum pedido se perde.'),
                ('Saída comprovada', 'Checklist com fotos e assinatura', 'O checklist com fotos e assinatura digital registra o estado do equipamento na saída.', 'Resultado: sem dúvida sobre como ele saiu.'),
                ('Transporte', 'Você sabe onde cada equipamento está', 'As etapas do transporte ficam registradas, do pátio ao cliente e de volta.', 'Resultado: o comercial responde ao cliente com segurança.'),
                ('Chegada no pátio', 'A volta conferida', 'Na chegada, o equipamento é conferido; se houver avaria, ele fica bloqueado até a liberação.', 'Resultado: o defeito aparece na volta, não na próxima locação.')],
        h2a='Como acompanhar cada equipamento do pátio ao cliente e de volta?',
        why='A logística é onde a locadora ganha ou perde a confiança do cliente. Quem registra a saída e a volta resolve a discussão antes de ela começar.',
        quem=[('Coordenadores de logística', 'Quem programa retiradas, entregas e transportes.'), ('Gestores de pátio', 'Quem confere a saída e a chegada de equipamentos.'), ('Equipe comercial', 'Quem responde ao cliente sobre prazos e entregas.'), ('Donos de locadora', 'Quem quer a operação organizada de ponta a ponta.')],
        porte='locadoras de pequeno e médio porte, com entregas e retiradas frequentes.',
        faq=[('A Oravel funciona junto com o sistema que já uso?', 'Sim. A Oravel complementa o sistema que a sua locadora já usa.'),
             ('A saída do equipamento fica comprovada?', 'Sim. O checklist com fotos e assinatura digital documenta o estado do equipamento na saída.'),
             ('Consigo acompanhar os pedidos de retirada?', 'Sim. Cada solicitação tem status, do pedido à conclusão.'),
             ('O que acontece se o equipamento volta com avaria?', 'Ele é conferido na chegada ao pátio, e a avaria fica registrada. O equipamento fica bloqueado até a liberação.'),
             ('O custo do frete é registrado?', 'Sim. Os fretes e transportadoras ficam registrados para você acompanhar o custo.')],
        vt=['devolucao', 'p_equipes', 'p_cobranca', 'locadoras'], cta='Leve esse controle para a logística da sua locadora'),
    'cobranca': dict(slug='/processo-cobranca-automatica/', origem='cobranca', eyebrow='Cobrança automática', cenas=pr.cenas_cobranca,
        title='Cobrança automática de locação: medição, boleto e aviso | Oravel',
        desc='Da medição do contrato à cobrança, ao aviso automático e ao pagamento identificado. A Oravel trabalha ao lado do seu sistema fiscal.',
        h1='Cobrança que acontece sozinha, sem planilha no meio.',
        lead='Cobrar locação na mão é medir, calcular, mandar boleto, lembrar o cliente e conferir o pagamento. A Oravel faz esse caminho com você, da medição do contrato ao aviso automático e ao pagamento identificado. A nota fiscal continua no sistema que a sua locadora já usa.',
        passos=[('Contrato', 'Cada contrato, a sua regra', 'Mensal, por hora, diária ou franquia com excedente: a cobrança segue a regra do contrato.', 'Resultado: a medição vira cobrança sem retrabalho.'),
                ('Contas a receber', 'A cobrança nasce da locação', 'Cada fatura aparece em contas a receber, com vencimento e status.', 'Resultado: você enxerga quem pagou e quem atrasou.'),
                ('Da medição ao pagamento', 'O caminho inteiro, sem planilha', 'A cobrança é gerada, enviada ao cliente e acompanhada até o pagamento.', 'Resultado: menos tempo cobrando, mais tempo vendendo.'),
                ('Aviso automático', 'O cliente é lembrado, você não precisa cobrar', 'Avisos automáticos de cobrança lembram o cliente do vencimento, e o pagamento é identificado.', 'Resultado: menos inadimplência e menos constrangimento.')],
        h2a='Como cobrar a locação sem depender de planilha e de lembrete manual?',
        why='Receber em dia é o que sustenta a locadora. Quanto menos a cobrança depende de pessoas lembrando, mais previsível fica o seu caixa.',
        quem=[('Financeiro de locadoras', 'Quem emite e acompanha as cobranças todos os meses.'), ('Gestores de contratos', 'Quem cuida de medições e renovações.'), ('Donos de locadora', 'Quem quer caixa previsível.'), ('Locadoras em crescimento', 'Quem já não aguenta cobrar na planilha.')],
        porte='locadoras de pequeno e médio porte, com vários contratos ativos.',
        faq=[('A Oravel emite nota fiscal?', 'Não. A Oravel cuida da medição e da cobrança e trabalha ao lado do sistema fiscal que a sua locadora já usa, onde a nota fiscal continua sendo emitida.'),
             ('Quais formas de cobrança os contratos aceitam?', 'Mensal fixo, por hora, diária e franquia de horas com excedente.'),
             ('Como o cliente paga?', 'O cliente recebe a cobrança com boleto e link de pagamento.'),
             ('O que acontece quando o cliente paga?', 'A Oravel identifica o pagamento e atualiza o status da cobrança.'),
             ('O cliente é avisado do vencimento?', 'Sim. Os avisos de cobrança são enviados automaticamente.')],
        vt=['p_logistica', 'p_manut', 'p_estoque', 'locadoras'], cta='Leve a cobrança automática para a sua locadora'),
}


def pagina_processo(chave):
    c = PROC_PAGES[chave]
    flow = ''.join(solve(e, h, t, r, c['cenas']()[i], i % 2 == 1) for i, (e, h, t, r) in enumerate(c['passos']))
    corpo = (secao('como-t', 'Como a Oravel ajuda', c['h2a'], 'Quatro passos, do começo ao fim.', flow, True)
             + secao('porque-t', 'Por que importa', 'Por que isso muda o resultado da locadora?', None,
                     f'<div class="texto"><p>{c["why"]}</p></div><div style="margin-top:28px">' + foto_larga('equipe.jpg', 'Equipe de manutenção registrando o serviço com tablets, ao lado de um caminhão', 'Equipe registrando a manutenção em campo.') + '</div>')
             + secao('quem-t', 'Para quem é', 'Para quem é e qual o porte ideal?', None, quem_e(c['quem'], 'Porte ideal:', c['porte'] + ' <!-- VALIDAR porte ideal -->'), True)
             + secao('faq-t', 'Perguntas frequentes', f'Perguntas frequentes sobre {c["eyebrow"].lower()} e a Oravel', None, faq(c['faq'])))
    return dict(slug=c['slug'], origem=c['origem'], eyebrow=c['eyebrow'], title=c['title'], desc=c['desc'], h1=c['h1'], lead=c['lead'], corpo=corpo,
                faq=c['faq'], vt=c['vt'], hero=pr.HERO[chave](),
                app_desc=f'Sistema de gestão de manutenção (CMMS) para locadoras: {c["eyebrow"].lower()}.', cta_h=c['cta'])



def pagina_devolucao():
    faqs = [
        ('O que acontece quando um equipamento volta com defeito?',
         'O defeito é registrado com fotos, o equipamento fica bloqueado para nova locação e o reparo segue por uma ordem de serviço, até a liberação.'),
        ('O comercial consegue alugar um equipamento que está em reparo?',
         'Não. Enquanto o equipamento estiver bloqueado, ele não aparece como disponível. Ao ser liberado, volta à vitrine.'),
        ('Dá para saber quanto custou o reparo?',
         'Sim. A ordem de serviço reúne peças, mão de obra e o custo total, por equipamento.'),
        ('As avarias ficam registradas para consulta futura?',
         'Sim. Cada avaria fica com fotos, data e histórico de alterações, o que ajuda em qualquer conversa com o cliente.'),
        ('Preciso trocar o sistema que já uso?',
         'Não. A Oravel complementa o sistema que a sua locadora já usa.'),
    ]
    flow = (
        solve('1. Volta com defeito', 'O problema aparece na devolução, não na próxima locação',
              'O equipamento é conferido na volta, com fotos, e a avaria é registrada na hora.',
              'Resultado: o defeito tem dia, prova e responsável.', em.DEV_SCENES[0]())
        + solve('2. É bloqueado', 'Equipamento com problema não vai para a rua',
                'Enquanto não for resolvido, o equipamento fica bloqueado para nova locação. Ninguém aluga por engano.',
                'Resultado: menos reclamação e menos troca de equipamento no cliente.', em.DEV_SCENES[1](), True)
        + solve('3. Abre a ordem de serviço', 'O reparo tem dono, peças e custo',
                'A ordem de serviço acompanha o reparo, com peças, mão de obra e o custo total.',
                'Resultado: você sabe quanto cada defeito custou.', em.DEV_SCENES[2]())
        + solve('4. É liberado', 'Reparado, ele volta a ser vendido',
                'Depois da liberação, o equipamento volta a aparecer como disponível para o comercial.',
                'Resultado: a máquina volta a gerar receita sem ruído entre manutenção e vendas.', em.DEV_SCENES[3](), True)
    )
    corpo = (
        secao('como-t', 'O caminho do equipamento', 'O que a locadora precisa para um equipamento com defeito não ser alugado de novo?',
              'Quatro passos entre a devolução e a próxima locação.', flow, True)
        + secao('dossie-t', 'O registro que fica', 'O que fica documentado de cada saída e de cada devolução?', None,
                foto_larga('dossie.jpg', 'Dossiê de uma locação com fotos do painel, da avaria e da estrutura do equipamento, diagnóstico técnico e assinatura do cliente',
                           'Tela ilustrativa do dossiê de uma locação.'))
        + secao('porque-t', 'Por que importa', 'Por que esse caminho faz diferença?', None,
                '<div class="texto"><p>O elo mais frágil de uma locadora costuma ser a conversa entre a manutenção e o comercial. '
                'Quando os dois enxergam a mesma coisa, o equipamento certo vai para o cliente certo, e o prejuízo da avaria deixa de ser surpresa.</p></div>')
        + secao('quem-t', 'Para quem é', 'Para quem é e qual o porte ideal?', None,
                quem_e([('Locadoras de equipamentos pesados', 'Frotas em que cada devolução precisa ser conferida.'),
                        ('Gestores de manutenção', 'Quem recebe o equipamento e precisa de um caminho claro.'),
                        ('Equipe comercial', 'Quem precisa saber o que está de fato disponível.'),
                        ('Donos de locadora', 'Quem quer controle sobre avarias e custos de reparo.')],
                       'Porte ideal:', 'locadoras de pequeno e médio porte, com devoluções frequentes e equipamentos de valor. <!-- VALIDAR porte ideal -->'), True)
        + secao('faq-t', 'Perguntas frequentes', 'Perguntas frequentes sobre equipamento devolvido com defeito', None, faq(faqs))
    )
    return dict(slug='/equipamento-devolvido-com-defeito/', origem='devolucao', hero=em.HERO['devolucao'](), eyebrow='Devolução com defeito',
                title='Equipamento devolvido com defeito: da avaria à liberação | Oravel',
                desc='O equipamento voltou com defeito? Registre a avaria, bloqueie para locação, controle a ordem de serviço e libere de volta ao comercial com a Oravel.',
                h1='Equipamento voltou com defeito? Do bloqueio à liberação, sem ruído.',
                lead='Quando um equipamento volta com defeito, a locadora precisa que ele não seja alugado de novo antes do reparo. A Oravel registra a avaria, bloqueia o equipamento, acompanha a ordem de serviço e o devolve ao comercial só quando está pronto. Ela complementa o sistema que a sua locadora já usa.',
                corpo=corpo, faq=faqs, vt=['plataforma', 'guindaste', 'nr13', 'locadoras'],
                app_desc='Sistema de gestão de manutenção (CMMS) para locadoras: registro de avarias, bloqueio de equipamento em manutenção para locação, ordem de serviço e liberação.',
                cta_h='Leve esse caminho para a sua locadora')


def hub():
    def grade(cards):
        return '<div class="who-grid">' + ''.join(
            f'<a class="who" href="{u}" style="text-decoration:none;color:inherit"><h3>{n}</h3><p>{d}</p></a>' for u, n, d in cards) + '</div>'
    equip = [
        ('/software-manutencao-guindastes/', 'Guindastes', 'Prontos quando o cliente precisar.'),
        ('/software-manutencao-munks/', 'Munks', 'Prontos para o próximo serviço.'),
        ('/software-manutencao-plataformas-elevatorias/', 'Plataformas elevatórias', 'Prontas, documentadas e comprovadas.'),
        ('/software-manutencao-empilhadeiras/', 'Empilhadeiras', 'Disponíveis, sem parada surpresa.'),
        ('/software-manutencao-geradores/', 'Geradores', 'Prontos quando a energia não pode faltar.'),
        ('/software-manutencao-compressores/', 'Compressores', 'Revisados e com as inspeções em dia.'),
        ('/software-manutencao-outros-equipamentos/', 'Outros equipamentos', 'Qualquer máquina da frota, sob o mesmo controle.'),
    ]
    proc = [
        ('/processo-manutencao/', 'Manutenção', 'Preventiva, ordens de serviço e histórico.'),
        ('/processo-gestao-de-equipes/', 'Gestão de equipes', 'Quem faz o quê, sem choque de agenda.'),
        ('/processo-gestao-de-estoques/', 'Gestão de estoques', 'A peça certa, na hora certa.'),
        ('/processo-logistica/', 'Logística', 'Do pátio ao cliente e de volta.'),
        ('/processo-cobranca-automatica/', 'Cobrança automática', 'Da medição ao pagamento, sem planilha.'),
        ('/nr-13-inspecao-equipamentos/', 'Inspeções NR-13', 'Datas e laudos sob controle.'),
        ('/equipamento-devolvido-com-defeito/', 'Devolução com defeito', 'Do bloqueio à liberação.'),
    ]
    corpo = (secao('hub-t', 'Equipamentos', 'Qual é o equipamento da sua locadora?', 'Escolha o tipo de equipamento e veja como a Oravel ajuda.', grade(equip), True)
             + secao('proc-t', 'Processos', 'E o dia a dia da operação?', 'Os processos que mais dão dor de cabeça na locadora.', grade(proc))
             + secao('campo-t', 'Em campo', 'A manutenção acontece onde o equipamento está.', None,
                     foto_larga('equipe.jpg', 'Equipe de manutenção registrando o serviço com tablets, ao lado de um caminhão', 'Equipe registrando a manutenção em campo.'), True))
    return dict(slug='/guindastes-e-plataformas/', origem='home', eyebrow='Equipamentos', hero=em.HERO['hub'](),
                title='Equipamentos de locação: guindastes, munks, empilhadeiras e mais | Oravel',
                desc='Manutenção de guindastes, munks, plataformas, empilhadeiras, geradores e compressores, com inspeções NR-13 e devoluções com defeito. Veja como a Oravel ajuda.',
                h1='Equipamentos de locação sempre prontos.',
                lead='Guindastes, munks, plataformas, empilhadeiras, geradores, compressores e muito mais: escolha o equipamento da sua locadora e veja como a Oravel ajuda. Ela complementa o sistema que você já usa.',
                corpo=corpo, faq=[], vt=['locadoras', 'manutencao'], app_desc='Sistema de gestão de manutenção (CMMS) para locadoras de equipamentos.',
                cta_h='Fale com a Oravel sobre a sua frota')


# ---------------------------------------------------------------- montagem
def cta_form(p):
    wa_txt = f'Ol%C3%A1%21%20Vim%20pela%20p%C3%A1gina%20{p["eyebrow"].replace(" ", "%20")}%20e%20quero%20testar%20a%20Oravel%20por%2030%20dias.'
    return (
        '<section class="section" id="contato" aria-labelledby="cta-t"><div class="wrap"><div class="cta-box"><div>'
        f'<h2 id="cta-t">{p["cta_h"]}</h2>'
        f'<p class="lead">{CTA_FIM}</p>'
        f'<div class="cta-alt"><a class="btn btn-ghost" href="{WA}?text={wa_txt}" target="_blank" rel="noopener">WhatsApp {WA_FONE}</a>'
        f'<a class="btn btn-ghost" href="mailto:{EMAIL}">{EMAIL}</a></div></div>'
        '<form class="form" id="lead-form" method="post" action="/lead-segmento.php" novalidate>'
        '<div id="form-msg" class="form-msg" role="status" aria-live="polite" hidden></div>'
        f'<input type="hidden" name="origem" value="{p["origem"]}">'
        '<div class="hp" aria-hidden="true"><label>Não preencha <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>'
        '<div class="row"><div><label for="f-nome">Nome *</label><input id="f-nome" name="name" type="text" autocomplete="name" required minlength="2" maxlength="120"></div>'
        '<div><label for="f-empresa">Empresa *</label><input id="f-empresa" name="company" type="text" autocomplete="organization" required minlength="2" maxlength="120"></div></div>'
        '<div class="row"><div><label for="f-email">E-mail *</label><input id="f-email" name="email" type="email" autocomplete="email" required maxlength="160"></div>'
        '<div><label for="f-tel">WhatsApp</label><input id="f-tel" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="30" placeholder="(19) 90000-0000"></div></div>'
        '<div class="row"><div><label for="f-seg">Segmento *</label><select id="f-seg" name="segmento" required>'
        '<option value="locacao" selected>Locação de Equipamentos</option><option value="manutencao-industrial">Manutenção Industrial</option>'
        '<option value="outro">Outro segmento</option></select></div>'
        '<div><label for="f-porte">Nº de técnicos *</label><select id="f-porte" name="porte" required><option value="">Selecione</option>'
        '<option>1 a 5</option><option>6 a 15</option><option>16 a 50</option><option>Mais de 50</option></select></div></div>'
        '<div class="cf-turnstile" data-sitekey="0x4AAAAAAEUrIcrw2tIq9KEj"></div>'
        '<button type="submit" class="btn btn-primary">Quero testar por 30 dias</button>'
        '<p class="fine">Ao enviar, você concorda em ser contatado pela Oravel, conforme a '
        '<a href="/politica-de-privacidade/" style="color:inherit;text-decoration:underline;">Política de Privacidade</a>.</p>'
        '</form></div></div></section>'
    )


def montar(molde, p):
    s = molde
    head_fim = s.index('</head>')
    head = s[:s.index('<style>')]
    css = s[s.index('<style>'):head_fim]
    body_ini = s.index('<body>')
    nav_ini = s.index('<nav class="inav"')
    nav_fim = s.index('</nav>', nav_ini) + 6
    topo = s[body_ini:nav_ini]
    nav = s[nav_ini:nav_fim]
    rodape_ini = s.index('<footer class="ifoot"')
    rodape_fim = s.index('</footer>', rodape_ini) + 9
    rodape = s[rodape_ini:rodape_fim]
    cauda = s[rodape_fim:]

    # head: remove metadados do molde, mantém ícones e fontes
    head = re.sub(r'<title>.*?</title>\n?', '', head, flags=re.S)
    head = re.sub(r'<meta name="description"[^>]*>\n?', '', head)
    head = re.sub(r'<link rel="canonical"[^>]*>\n?', '', head)
    head = re.sub(r'<meta property="og:(title|description|url)"[^>]*>\n?', '', head)
    head = re.sub(r'<script type="application/ld\+json">.*?</script>\n?', '', head, flags=re.S)
    url = BASE + p['slug']
    meta = (f'<title>{html.escape(p["title"])}</title>\n<meta name="description" content="{html.escape(p["desc"])}">\n'
            f'<link rel="canonical" href="{url}">\n<meta property="og:title" content="{html.escape(p["title"])}">\n'
            f'<meta property="og:description" content="{html.escape(p["desc"])}">\n<meta property="og:url" content="{url}">\n')
    head = head.replace('<meta name="viewport" content="width=device-width, initial-scale=1.0">\n',
                        '<meta name="viewport" content="width=device-width, initial-scale=1.0">\n' + meta, 1)
    if 'href="/favicon-48.png"' not in head:
        antigos = list(re.finditer(r'<link rel="icon"[^>]*nova/assets/favicon[^>]*>\s*\n?', head))
        if antigos:
            for m in reversed(antigos[1:]):
                head = head[:m.start()] + head[m.end():]
            m = antigos[0]
            head = head[:m.start()] + FAVICON_BLOCO + head[m.end():]
    head += ld(p) + '\n'

    # nav: sem item "atual" herdado; novo dropdown "Equipamentos"
    nav = nav.replace(' aria-current="page"', '')
    nav = nav.replace('</div></div><a href="/planos/">', '</div></div>' + NAV_EQUIP + NAV_PROC + '<a href="/planos/">', 1)
    if p['slug'] != '/guindastes-e-plataformas/':
        nav = nav.replace(f'<a href="{p["slug"]}">', f'<a href="{p["slug"]}" aria-current="page">')

    rodape = rodape.replace(
        '<a href="/departamento-pessoal/">Departamento Pessoal</a></div>',
        '<a href="/departamento-pessoal/">Departamento Pessoal</a></div><div class="ft-col"><h4>Equipamentos</h4>'
        '<a href="/software-manutencao-guindastes/">Guindastes</a>'
        '<a href="/software-manutencao-munks/">Munks</a>'
        '<a href="/software-manutencao-plataformas-elevatorias/">Plataformas elevatórias</a>'
        '<a href="/software-manutencao-empilhadeiras/">Empilhadeiras</a>'
        '<a href="/software-manutencao-geradores/">Geradores</a>'
        '<a href="/software-manutencao-compressores/">Compressores</a>'
        '<a href="/software-manutencao-outros-equipamentos/">Outros equipamentos</a></div>', 1)
    rodape = rodape.replace('<div class="ft-brand">O<i>r</i>avel</div>',
                            f'<div class="ft-brand">O<i>r</i>avel</div><p class="slogan">{SLOGAN}</p>', 1)

    vt = veja_tambem([LINKS[k] for k in p['vt']])
    hero = (f'<header class="hero"><div class="wrap hero-grid"><div><div class="eyebrow">{p["eyebrow"]}</div><h1>{p["h1"]}</h1>'
            f'<p class="sub lead-p">{p["lead"]}</p>'
            '<div class="hero-actions"><a href="#contato" class="btn btn-primary">Teste por 30 dias</a>'
            f'<a href="#{"como-t" if "id=\"como-t\"" in p["corpo"] else "hub-t"}" class="btn btn-ghost">Ver como funciona</a></div></div>'
            f'<div class="hero-art">{p["hero"]}</div></div></header>')
    posic = f'<section class="section"><div class="wrap"><div class="texto"><p>{POSICIONAMENTO}</p>{vt}</div></div></section>'
    main = f'<main id="conteudo">{hero}{p["corpo"]}{posic}{cta_form(p)}</main>\n'
    return head + css + EXTRA_CSS + FOOTER_NAVY + '\n</head>\n' + topo + nav + '\n\n' + main + rodape + cauda


def montar_novo(molde, p):
    """Página no padrão novo do site (tema.py): cabeçalho, topo, corpo gerado, convite final e rodapé."""
    url = BASE + p['slug']
    j = re.search(r'<script type="application/ld\+json">(.*?)</script>', ld(p), re.S)
    ldj = json.loads(j.group(1))
    alvo = '"como-t"' if 'id="como-t"' in p['corpo'] else '"hub-t"'
    hero = ('<section class="hero"><div class="wrap"><div><div class="eyebrow">' + p['eyebrow'] + '</div><h1>' + p['h1'] + '</h1>'
            '<p class="sub">' + p['lead'] + '</p><div class="hero-act"><a class="btn btn-p" href="/contato.php">Agendar uma demonstração</a>'
            '<a class="btn btn-o" href="#' + alvo.strip('"') + '">Ver como funciona</a></div></div>'
            '<div class="hero-art">' + p['hero'] + '</div></div></section>')
    posic = ('<section class="section"><div class="wrap"><div class="texto"><p>' + POSICIONAMENTO + '</p>' + veja_tambem([LINKS[k] for k in p['vt']]) + '</div></div></section>')
    corpo = hero + p['corpo'] + posic + tm.cta_final(p['cta_h'], CTA_FIM)
    return tm.pagina(p['title'], p['desc'], url, corpo, ldj)


def main():
    molde = Path(sys.argv[1]).read_text(encoding='utf-8')
    saida = Path(sys.argv[2])
    for p in (pagina_nr13(), *[pagina_equip(k) for k in EQUIPS], *[pagina_processo(k) for k in PROC_PAGES], pagina_devolucao(), hub()):
        destino = saida / p['slug'].strip('/') / 'index.html'
        destino.parent.mkdir(parents=True, exist_ok=True)
        destino.write_text(montar_novo(molde, p), encoding='utf-8')
        print('ok', destino)


if __name__ == '__main__':
    main()
