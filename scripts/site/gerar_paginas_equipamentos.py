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

BASE = 'https://oravel.com.br'
WA = 'https://wa.me/5519999332615'
WA_FONE = '(19) 99933-2615'
EMAIL = 'contato@oravel.com.br'
SLOGAN = 'Desenvolvimento e tecnologia'

NAV_EQUIP = (
    '<div class="hd-dd"><a href="/guindastes-e-plataformas/" aria-haspopup="true">Equipamentos</a>'
    '<div class="hd-menu">'
    '<a href="/software-manutencao-guindastes/">Guindastes</a>'
    '<a href="/software-manutencao-plataformas-elevatorias/">Plataformas elevatórias</a>'
    '<a href="/nr-13-inspecao-equipamentos/">Inspeções NR-13</a>'
    '<a href="/equipamento-devolvido-com-defeito/">Devolução com defeito</a>'
    '</div></div>'
)


# ---------------------------------------------------------------- mockups (SVG)
def t(x, y, s, size=11, fill='#0f172a', w=500, anchor='start'):
    return (f'<text x="{x}" y="{y}" font-family="Inter,Segoe UI,sans-serif" font-size="{size}" '
            f'font-weight="{w}" fill="{fill}" text-anchor="{anchor}">{html.escape(s)}</text>')


def r(x, y, w, h, fill='#f1f5f9', rx=6, stroke=None):
    st = f' stroke="{stroke}"' if stroke else ''
    return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" fill="{fill}"{st}/>'


def pill(x, y, label, bg, fg):
    w = 14 + len(label) * 6
    return r(x, y, w, 18, bg, 9) + t(x + w / 2, y + 12.5, label, 10, fg, 700, 'middle')


OK = ('#dcfce7', '#166534')
WARN = ('#fef3c7', '#92400e')
BAD = ('#fee2e2', '#991b1b')
ORG = ('#fff1e4', '#c2410c')


def mock(titulo, corpo, legenda):
    svg = (
        f'<svg viewBox="0 0 480 300" role="img" aria-label="{html.escape(legenda)}"><title>{html.escape(legenda)}</title>'
        '<rect width="480" height="300" rx="10" fill="#ffffff"/>'
        '<rect width="480" height="30" rx="10" fill="#0f172a"/><rect y="15" width="480" height="15" fill="#0f172a"/>'
        '<circle cx="18" cy="15" r="4" fill="#f97316"/>'
        + t(30, 19, titulo, 12, '#ffffff', 700)
        + corpo + '</svg>'
    )
    return f'<figure class="mock">{svg}<figcaption>{html.escape(legenda)}</figcaption></figure>'


def linhas(itens, y0=48, passo=44):
    """itens: (titulo, subtitulo, (rotulo, cores))"""
    out = []
    for i, (a, b, (lab, (bg, fg))) in enumerate(itens):
        y = y0 + i * passo
        out.append(r(16, y, 448, 36, '#f8fafc', 8, '#e4e7ec'))
        out.append(t(30, y + 15, a, 12, '#0f172a', 700))
        out.append(t(30, y + 29, b, 10.5, '#5b6472', 500))
        out.append(pill(448 - (14 + len(lab) * 6), y + 9, lab, bg, fg))
    return ''.join(out)


def foto(x, y, danger=False):
    c = '#fecaca' if danger else '#e2e8f0'
    return (r(x, y, 46, 36, c, 6) + f'<path d="M{x+8} {y+28} l10 -12 l8 8 l6 -7 l8 11z" fill="{"#f87171" if danger else "#94a3b8"}"/>'
            f'<circle cx="{x+34}" cy="{y+11}" r="4" fill="{"#f87171" if danger else "#94a3b8"}"/>')


def m_agenda():
    return mock('Inspeções', linhas([
        ('Equipamento 01', 'Próxima inspeção em 18/11', WARN and ('Em breve', WARN)),
        ('Equipamento 02', 'Próxima inspeção em 09/03', ('Em dia', OK)),
        ('Equipamento 03', 'Próxima inspeção em 02/09', ('Em dia', OK)),
        ('Equipamento 04', 'Próxima inspeção em 21/10', ('Vencida', BAD)),
    ]) + t(16, 244, 'Cada equipamento com a sua data à vista.', 11, '#5b6472', 500),
        'Recorte ilustrativo: lista de equipamentos com a data da próxima inspeção')


def m_laudo():
    c = r(16, 44, 448, 190, '#f8fafc', 10, '#e4e7ec')
    c += t(32, 68, 'Registro da inspeção', 13, '#0f172a', 800)
    c += pill(360, 54, 'Aprovado', *OK)
    for i, (k, v) in enumerate([('Equipamento', 'Equipamento 01'), ('Responsável técnico', 'Nome do profissional'), ('Documento', 'laudo-inspecao.pdf')]):
        y = 92 + i * 40
        c += t(32, y, k, 10, '#8891a0', 600) + r(32, y + 6, 416, 22, '#ffffff', 6, '#e4e7ec') + t(42, y + 21, v, 11, '#0f172a', 500)
    return mock('Inspeções', c, 'Recorte ilustrativo: registro da inspeção com o laudo anexado')


def m_proxima():
    c = r(16, 50, 205, 120, '#f8fafc', 12, '#e4e7ec') + t(32, 80, 'Inspeção feita', 11, '#5b6472', 600) + t(32, 112, '14/03', 28, '#0f172a', 800) + pill(32, 130, 'Aprovado', *OK)
    c += '<path d="M232 110 h16 m-6 -6 l6 6 l-6 6" stroke="#f97316" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
    c += r(259, 50, 205, 120, '#fff1e4', 12, '#fdba74') + t(275, 80, 'Próxima inspeção', 11, '#c2410c', 700) + t(275, 112, 'Já definida', 22, '#0f172a', 800) + t(275, 138, 'Acompanhada pelo sistema', 10.5, '#5b6472', 500)
    c += t(16, 214, 'Ao registrar uma inspeção, a seguinte já nasce agendada.', 11, '#5b6472', 500)
    return mock('Inspeções', c, 'Recorte ilustrativo: inspeção realizada e próxima inspeção definida')


def m_painel():
    c = ''
    for i, (n, lab, col, bg) in enumerate([('18', 'Em dia', '#166534', '#dcfce7'), ('3', 'Vencendo', '#92400e', '#fef3c7'), ('1', 'Vencidas', '#991b1b', '#fee2e2')]):
        x = 16 + i * 153
        c += r(x, 50, 142, 100, bg, 12) + t(x + 71, 100, n, 36, col, 800, 'middle') + t(x + 71, 128, lab, 12, col, 700, 'middle')
    c += r(16, 166, 448, 36, '#f8fafc', 8, '#e4e7ec') + t(30, 189, 'Aviso: uma inspeção está perto de vencer', 11.5, '#0f172a', 600) + pill(392, 175, 'Ver', *ORG)
    return mock('Conformidade', c, 'Recorte ilustrativo: painel de conformidade com avisos de vencimento')


def m_laudo_recebimento():
    c = ''
    for i, (a, dano) in enumerate([('Estrutura', False), ('Parte elétrica', False), ('Sistema hidráulico', True)]):
        y = 46 + i * 56
        c += r(16, y, 448, 48, '#f8fafc', 8, '#fca5a5' if dano else '#e4e7ec') + t(30, y + 20, a, 12, '#0f172a', 700)
        c += t(30, y + 36, 'Avaria encontrada' if dano else 'Sem observações', 10.5, '#b91c1c' if dano else '#5b6472', 600)
        c += foto(404, y + 6, dano)
    c += t(16, 232, 'Conferência na volta, com fotos.', 11, '#5b6472', 500)
    return mock('Recebimento', c, 'Recorte ilustrativo: conferência do equipamento na devolução, com fotos')


def m_bloqueado():
    c = r(16, 50, 448, 120, '#fef2f2', 12, '#fecaca') + t(34, 82, 'Equipamento 07', 16, '#0f172a', 800)
    c += pill(34, 94, 'Bloqueado para locação', *BAD) + t(34, 138, 'Fora da vitrine comercial até a liberação.', 11.5, '#7f1d1d', 600)
    c += r(16, 186, 448, 36, '#f8fafc', 8, '#e4e7ec') + t(30, 209, 'Ninguém aluga por engano.', 11.5, '#5b6472', 600)
    return mock('Equipamentos', c, 'Recorte ilustrativo: equipamento bloqueado para nova locação')


def m_os_custo():
    c = r(16, 46, 448, 40, '#f8fafc', 8, '#e4e7ec') + t(30, 62, 'Ordem de serviço', 12, '#0f172a', 800) + t(30, 77, 'Equipamento 07', 10.5, '#5b6472', 500) + pill(380, 57, 'Em execução', *ORG)
    for i, (k, v) in enumerate([('Peças', 'R$ 0.000,00'), ('Mão de obra', 'R$ 0.000,00'), ('Custo total', 'R$ 0.000,00')]):
        y = 100 + i * 38
        last = i == 2
        c += r(16, y, 448, 30, '#fff1e4' if last else '#ffffff', 8, '#fdba74' if last else '#e4e7ec') + t(30, y + 20, k, 11.5, '#0f172a', 800 if last else 600) + t(448, y + 20, v, 11.5, '#0f172a', 800, 'end')
    return mock('Ordens de serviço', c, 'Recorte ilustrativo: ordem de serviço com o custo do reparo')


def m_liberado():
    c = r(16, 50, 448, 120, '#f0fdf4', 12, '#bbf7d0') + t(34, 82, 'Equipamento 07', 16, '#0f172a', 800)
    c += pill(34, 94, 'Disponível', *OK) + t(34, 138, 'Volta a aparecer para o comercial.', 11.5, '#14532d', 600)
    c += '<circle cx="420" cy="110" r="22" fill="#16a34a"/><path d="M409 110 l8 8 l15 -16" stroke="#fff" stroke-width="4" fill="none" stroke-linecap="round" stroke-linejoin="round"/>'
    return mock('Equipamentos', c, 'Recorte ilustrativo: equipamento liberado e disponível para locação')


def m_horimetro(nome):
    c = r(16, 46, 448, 112, '#f8fafc', 12, '#e4e7ec') + t(32, 72, nome, 13, '#0f172a', 800) + t(32, 92, 'Manutenção preventiva', 10.5, '#5b6472', 600)
    c += r(32, 108, 416, 12, '#e2e8f0', 6) + r(32, 108, 300, 12, '#f97316', 6)
    c += t(32, 142, 'Horímetro', 10.5, '#5b6472', 600) + t(448, 142, 'Próxima revisão se aproxima', 10.5, '#c2410c', 700, 'end')
    c += t(16, 190, 'A revisão chega antes da quebra.', 11, '#5b6472', 500)
    return mock('Preventiva', c, 'Recorte ilustrativo: acompanhamento da preventiva por horímetro')


def m_os_equip(nome):
    return mock('Ordens de serviço', linhas([
        (nome, 'Ordem de serviço aberta', ('Em execução', ORG)),
        (nome, 'Ordem de serviço anterior', ('Concluída', OK)),
        ('Outro equipamento', 'Ordem de serviço', ('Aberta', WARN)),
    ], passo=46), 'Recorte ilustrativo: ordens de serviço separadas por equipamento')


def m_historico(nome):
    c = t(32, 62, nome, 13, '#0f172a', 800)
    for i, (a, b) in enumerate([('Revisão concluída', 'Ordem de serviço'), ('Avaria registrada', 'Com fotos'), ('Checklist de entrega', 'Assinado'), ('Inspeção', 'Registrada')]):
        y = 84 + i * 42
        c += f'<circle cx="38" cy="{y+8}" r="6" fill="#f97316"/>' + (f'<line x1="38" y1="{y+14}" x2="38" y2="{y+38}" stroke="#e4e7ec" stroke-width="2"/>' if i < 3 else '')
        c += t(56, y + 8, a, 11.5, '#0f172a', 700) + t(56, y + 22, b, 10, '#5b6472', 500)
    return mock('Histórico', c, 'Recorte ilustrativo: histórico individual de um equipamento')


def m_avarias():
    c = r(16, 46, 448, 100, '#fffbeb', 12, '#fde68a') + t(32, 72, 'Avaria registrada', 13, '#0f172a', 800) + pill(350, 58, 'Em análise', *WARN)
    c += foto(32, 88) + foto(86, 88, True) + t(150, 106, 'Fotos anexadas ao registro', 10.5, '#5b6472', 600)
    c += t(150, 124, 'Cada avaria com data e responsável', 10.5, '#5b6472', 600)
    c += t(16, 182, 'Nada se perde na conversa de corredor.', 11, '#5b6472', 500)
    return mock('Avarias', c, 'Recorte ilustrativo: avaria registrada com fotos')


def m_checklist_assinatura():
    c = ''
    for i, a in enumerate(['Parte estrutural', 'Parte elétrica', 'Itens de segurança']):
        y = 44 + i * 36
        c += r(16, y, 300, 30, '#f8fafc', 8, '#e4e7ec') + '<circle cx="34" cy="' + str(y + 15) + '" r="7" fill="#16a34a"/>' + t(48, y + 19, a, 11.5, '#0f172a', 600) + foto(320, y - 3)
    c += r(16, 158, 448, 74, '#ffffff', 10, '#cbd5e1')
    c += t(30, 178, 'Assinatura do cliente', 10, '#8891a0', 600)
    c += '<path d="M40 218 c18 -34 30 6 46 -14 s24 18 40 -4 s26 -16 38 6" stroke="#0f172a" stroke-width="2.2" fill="none" stroke-linecap="round"/>'
    c += pill(372, 170, 'Entrega', *OK)
    return mock('Checklist', c, 'Recorte ilustrativo: checklist com fotos e assinatura digital')


# ---------------------------------------------------------------- blocos de página
def solve(eyebrow, h3, texto, resultado, mockhtml, rev=False):
    return (f'<div class="solve{" rev" if rev else ""}"><div><div class="eyebrow">{eyebrow}</div><h3>{h3}</h3>'
            f'<p>{texto}</p><p class="result">{resultado}</p></div>{mockhtml}</div>')


def secao(h2_id, eyebrow, h2, intro, corpo, surface=False):
    intro_html = f'<p>{intro}</p>' if intro else ''
    return (f'<section class="section{" section-surface" if surface else ""}" aria-labelledby="{h2_id}"><div class="wrap">'
            f'<div class="section-head"><div class="eyebrow">{eyebrow}</div><h2 id="{h2_id}">{h2}</h2>{intro_html}</div>{corpo}</div></section>')


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
    'devolucao': ('/equipamento-devolvido-com-defeito/', 'Equipamento devolvido com defeito'),
    'locadoras': ('/erp-cmms/', 'Oravel para locadoras'),
    'manutencao': ('/software-manutencao-industrial/', 'Manutenção'),
}

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
              'Resultado: você enxerga o mês antes de ele chegar.', m_agenda())
        + solve('Relatório de inspeção', 'O laudo mora junto do equipamento',
                'Quando o cliente, o auditor ou a seguradora pedir, o documento está a um clique, com responsável técnico e resultado.',
                'Resultado: menos correria, mais segurança na hora de provar.', m_laudo(), True)
        + solve('Próxima data', 'Feita a inspeção, a seguinte já está marcada',
                'A cada registro, a próxima data fica definida e acompanhada. Nada depende de memória.',
                'Resultado: inspeção vencida deixa de ser surpresa.', m_proxima())
        + solve('Alertas de vencimento', 'Um painel que mostra o que merece atenção hoje',
                'Os alertas de vencimento avisam antes de vencer. Você abre o painel e vê, sem perguntar para ninguém.',
                'Resultado: tranquilidade para locar equipamentos com a documentação em dia.', m_painel(), True)
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
    return dict(slug='/nr-13-inspecao-equipamentos/', origem='nr13', eyebrow='Inspeções NR-13',
                title='Controle de inspeções NR-13 para locadoras | Oravel',
                desc='Inspeção NR-13 vencida para a sua locadora? Organize datas, laudos e alertas de vencimento com a Oravel, o sistema de gestão de manutenção que complementa o seu.',
                h1='Inspeções NR-13 sempre em dia na sua locadora.',
                lead='Inspeção NR-13 vencida é equipamento parado e risco para o seu negócio. Com a Oravel, cada equipamento tem a sua data, o seu laudo e o seu aviso de vencimento, tudo num só lugar. Ela trabalha ao lado do sistema que a sua locadora já usa.',
                corpo=corpo, faq=faqs, vt=['guindaste', 'plataforma', 'devolucao', 'locadoras'],
                app_desc='Sistema de gestão de manutenção (CMMS) para locadoras, com controle de inspeções NR-13, laudos e alertas de vencimento.',
                cta_h='Deixe a NR-13 sob controle na sua locadora')


def pagina_equip(tipo):
    g = tipo == 'guindaste'
    nome = 'Guindaste 01' if g else 'Plataforma 01'
    plural = 'guindastes' if g else 'plataformas elevatórias'
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
    if not g:
        faqs.append(('Serve para plataformas tesoura, articuladas e telescópicas?',
                     'Sim. A Oravel atende os principais tipos de plataforma elevatória usados em locação.'))
    flow = (
        solve('Manutenção preventiva', f'Revisão por horímetro ou por tempo, sem depender de memória',
              f'A manutenção preventiva por horímetro ou por tempo mantém cada {"guindaste" if g else "plataforma"} saudável e a sua locadora longe de parada inesperada.',
              'Resultado: menos quebra no meio da locação.', m_horimetro(nome))
        + solve('Ordem de serviço por equipamento', 'Cada equipamento com as suas ordens de serviço',
                'Quem fez, quando e quanto custou, separado por equipamento e fácil de achar.',
                'Resultado: clareza sobre o que cada máquina consome.', m_os_equip(nome), True)
        + solve('Histórico individual', 'A vida inteira do equipamento em uma tela',
                'O histórico individual por equipamento conta tudo o que já aconteceu com ele. É o argumento na hora de negociar, vender ou renovar.',
                'Resultado: decisão com base em fato, não em lembrança.', m_historico(nome))
        + solve('Controle de avarias', 'Avaria registrada, com prova e com responsável',
                'As avarias deixam de virar discussão: ficam registradas com fotos, data e acompanhamento, com trilha de auditoria de quem mexeu em quê.',
                'Resultado: menos prejuízo na devolução.', m_avarias(), True)
        + solve('Checklist com fotos e assinatura digital', 'Entrega e devolução comprovadas',
                'O checklist com fotos e assinatura digital documenta o estado do equipamento na saída e na volta.',
                'Resultado: sem dúvida sobre o estado em que ele saiu e voltou.', m_checklist_assinatura())
    )
    if g:
        pagina_nome, slug = 'Guindastes', '/software-manutencao-guindastes/'
        title = 'Software de manutenção para guindastes de locação | Oravel'
        desc = 'Manutenção preventiva por horímetro, ordem de serviço por equipamento e histórico individual para a sua frota de guindastes. Conheça a Oravel.'
        h1 = 'Manutenção de guindastes sob controle na sua locadora.'
        lead = 'Guindaste parado é contrato em risco. A Oravel cuida da manutenção preventiva por horímetro, das ordens de serviço e do histórico de cada guindaste, para ele estar pronto quando o cliente precisar. Ela complementa o sistema que a sua locadora já usa.'
        quem = [('Locadoras de guindastes', 'Frotas em que cada máquina precisa estar pronta e documentada.'),
                ('Locadoras de equipamentos de elevação de carga', 'Quem opera guindastes e outros equipamentos de içamento.'),
                ('Gestores de manutenção', 'Quem responde pela disponibilidade da frota.'),
                ('Donos de locadora', 'Quem quer menos parada e mais previsibilidade.')]
        porte = 'locadoras de pequeno e médio porte, com frota a partir de poucos guindastes. <!-- VALIDAR porte ideal -->'
        h2a = 'Como manter guindastes de locação sempre prontos para o cliente?'
        eq = 'guindaste'
        vt = ['plataforma', 'nr13', 'devolucao', 'locadoras']
        cta_h = 'Veja a Oravel cuidando da sua frota de guindastes'
        app_desc = 'Sistema de gestão de manutenção (CMMS) para locadoras de guindastes: manutenção preventiva por horímetro, ordem de serviço por equipamento e histórico individual.'
    else:
        pagina_nome, slug = 'Plataformas elevatórias', '/software-manutencao-plataformas-elevatorias/'
        title = 'Software de manutenção para plataformas elevatórias | Oravel'
        desc = 'Manutenção preventiva por horímetro, checklist com fotos e assinatura digital e histórico individual para a sua frota de plataformas elevatórias. Conheça a Oravel.'
        h1 = 'Plataformas elevatórias sempre prontas e documentadas.'
        lead = 'Plataforma elevatória parada ou devolvida com dano sem prova é prejuízo certo. A Oravel organiza a manutenção preventiva, o checklist com fotos e assinatura digital e o histórico de cada plataforma. Ela complementa o sistema que a sua locadora já usa.'
        quem = [('Locadoras de plataformas elevatórias', 'Frotas de tesoura, articuladas e telescópicas.'),
                ('Locadoras de acesso em altura', 'Quem entrega e recebe equipamentos todos os dias.'),
                ('Gestores de manutenção', 'Quem precisa de previsibilidade na frota.'),
                ('Donos de locadora', 'Quem quer provar o estado em que cada plataforma saiu e voltou.')]
        porte = 'locadoras de pequeno e médio porte, com frota a partir de dezenas de plataformas. <!-- VALIDAR porte ideal -->'
        h2a = 'Como manter plataformas elevatórias prontas e comprovar a entrega e a devolução?'
        eq = 'plataforma'
        vt = ['guindaste', 'nr13', 'devolucao', 'locadoras']
        cta_h = 'Veja a Oravel cuidando da sua frota de plataformas'
        app_desc = 'Sistema de gestão de manutenção (CMMS) para locadoras de plataformas elevatórias: manutenção preventiva por horímetro, checklist com fotos e assinatura digital e histórico individual.'
    corpo = (
        secao('como-t', 'Como a Oravel ajuda', h2a, 'Do plano de revisão ao checklist assinado, tudo por equipamento.', flow, True)
        + secao('porque-t', 'Por que importa', 'Por que isso muda o resultado da locadora?', None,
                '<div class="texto"><p>Equipamento disponível e documentado aluga mais e dá menos dor de cabeça. '
                'Com avarias registradas e o equipamento em manutenção bloqueado para locação, a sua operação ganha ritmo e a sua margem agradece.</p></div>')
        + secao('quem-t', 'Para quem é', 'Para quem é e qual o porte ideal?', None, quem_e(quem, 'Porte ideal:', porte), True)
        + secao('faq-t', 'Perguntas frequentes', f'Perguntas frequentes sobre {plural} e a Oravel', None, faq(faqs))
    )
    return dict(slug=slug, origem=eq, eyebrow=pagina_nome, title=title, desc=desc, h1=h1, lead=lead, corpo=corpo,
                faq=faqs, vt=vt, app_desc=app_desc, cta_h=cta_h)


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
              'Resultado: o defeito tem dia, prova e responsável.', m_laudo_recebimento())
        + solve('2. É bloqueado', 'Equipamento com problema não vai para a rua',
                'Enquanto não for resolvido, o equipamento fica bloqueado para nova locação. Ninguém aluga por engano.',
                'Resultado: menos reclamação e menos troca de equipamento no cliente.', m_bloqueado(), True)
        + solve('3. Abre a ordem de serviço', 'O reparo tem dono, peças e custo',
                'A ordem de serviço acompanha o reparo, com peças, mão de obra e o custo total.',
                'Resultado: você sabe quanto cada defeito custou.', m_os_custo())
        + solve('4. É liberado', 'Reparado, ele volta a ser vendido',
                'Depois da liberação, o equipamento volta a aparecer como disponível para o comercial.',
                'Resultado: a máquina volta a gerar receita sem ruído entre manutenção e vendas.', m_liberado(), True)
    )
    corpo = (
        secao('como-t', 'O caminho do equipamento', 'O que a locadora precisa para um equipamento com defeito não ser alugado de novo?',
              'Quatro passos entre a devolução e a próxima locação.', flow, True)
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
    return dict(slug='/equipamento-devolvido-com-defeito/', origem='devolucao', eyebrow='Devolução com defeito',
                title='Equipamento devolvido com defeito: da avaria à liberação | Oravel',
                desc='O equipamento voltou com defeito? Registre a avaria, bloqueie para locação, controle a ordem de serviço e libere de volta ao comercial com a Oravel.',
                h1='Equipamento voltou com defeito? Do bloqueio à liberação, sem ruído.',
                lead='Quando um equipamento volta com defeito, a locadora precisa que ele não seja alugado de novo antes do reparo. A Oravel registra a avaria, bloqueia o equipamento, acompanha a ordem de serviço e o devolve ao comercial só quando está pronto. Ela complementa o sistema que a sua locadora já usa.',
                corpo=corpo, faq=faqs, vt=['plataforma', 'guindaste', 'nr13', 'locadoras'],
                app_desc='Sistema de gestão de manutenção (CMMS) para locadoras: registro de avarias, bloqueio de equipamento em manutenção para locação, ordem de serviço e liberação.',
                cta_h='Leve esse caminho para a sua locadora')


def hub():
    cards = [
        ('/software-manutencao-guindastes/', 'Guindastes', 'Prontos quando o cliente precisar.'),
        ('/software-manutencao-plataformas-elevatorias/', 'Plataformas elevatórias', 'Prontas, documentadas e comprovadas.'),
        ('/nr-13-inspecao-equipamentos/', 'Inspeções NR-13', 'Datas e laudos sob controle.'),
        ('/equipamento-devolvido-com-defeito/', 'Devolução com defeito', 'Do bloqueio à liberação.'),
    ]
    grid = '<div class="who-grid">' + ''.join(
        f'<a class="who" href="{u}" style="text-decoration:none;color:inherit"><h3>{n}</h3><p>{d}</p></a>' for u, n, d in cards) + '</div>'
    corpo = secao('hub-t', 'Equipamentos', 'Qual é o desafio da sua locadora?', 'Escolha o assunto e veja como a Oravel ajuda.', grid, True)
    return dict(slug='/guindastes-e-plataformas/', origem='home', eyebrow='Equipamentos',
                title='Guindastes, plataformas, NR-13 e devolução | Oravel',
                desc='Manutenção de guindastes e plataformas elevatórias, inspeções NR-13 e equipamento devolvido com defeito: veja como a Oravel ajuda a sua locadora.',
                h1='Equipamentos de locação sempre prontos.',
                lead='Guindastes, plataformas elevatórias, inspeções e devoluções com defeito: escolha o desafio da sua locadora e veja como a Oravel ajuda. Ela complementa o sistema que você já usa.',
                corpo=corpo, faq=[],
                vt=['locadoras', 'manutencao'], app_desc='Sistema de gestão de manutenção (CMMS) para locadoras de equipamentos.',
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
    head += ld(p) + '\n'

    # nav: sem item "atual" herdado; novo dropdown "Equipamentos"
    nav = nav.replace(' aria-current="page"', '')
    nav = nav.replace('</div></div><a href="/planos/">', '</div></div>' + NAV_EQUIP + '<a href="/planos/">', 1)
    if p['slug'] != '/guindastes-e-plataformas/':
        nav = nav.replace(f'<a href="{p["slug"]}">', f'<a href="{p["slug"]}" aria-current="page">')

    rodape = rodape.replace(
        '<a href="/departamento-pessoal/">Departamento Pessoal</a></div>',
        '<a href="/departamento-pessoal/">Departamento Pessoal</a>'
        '<a href="/software-manutencao-guindastes/">Guindastes</a>'
        '<a href="/software-manutencao-plataformas-elevatorias/">Plataformas elevatórias</a>'
        '<a href="/nr-13-inspecao-equipamentos/">Inspeções NR-13</a>'
        '<a href="/equipamento-devolvido-com-defeito/">Devolução com defeito</a></div>', 1)
    rodape = rodape.replace('<div class="ft-brand">O<i>r</i>avel</div>',
                            f'<div class="ft-brand">O<i>r</i>avel</div><p class="slogan">{SLOGAN}</p>', 1)

    vt = veja_tambem([LINKS[k] for k in p['vt']])
    hero = (f'<header class="hero"><div class="wrap"><div class="eyebrow">{p["eyebrow"]}</div><h1>{p["h1"]}</h1>'
            f'<p class="sub lead-p">{p["lead"]}</p>'
            '<div class="hero-actions"><a href="#contato" class="btn btn-primary">Teste por 30 dias</a>'
            f'<a href="#{"como-t" if "id=\"como-t\"" in p["corpo"] else "hub-t"}" class="btn btn-ghost">Ver como funciona</a></div></div></header>')
    posic = f'<section class="section"><div class="wrap"><div class="texto"><p>{POSICIONAMENTO}</p>{vt}</div></div></section>'
    main = f'<main id="conteudo">{hero}{p["corpo"]}{posic}{cta_form(p)}</main>\n'
    return head + css + EXTRA_CSS + '\n</head>\n' + topo + nav + '\n\n' + main + rodape + cauda


def main():
    molde = Path(sys.argv[1]).read_text(encoding='utf-8')
    saida = Path(sys.argv[2])
    for p in (pagina_nr13(), pagina_equip('guindaste'), pagina_equip('plataforma'), pagina_devolucao(), hub()):
        destino = saida / p['slug'].strip('/') / 'index.html'
        destino.parent.mkdir(parents=True, exist_ok=True)
        destino.write_text(montar(molde, p), encoding='utf-8')
        print('ok', destino)


if __name__ == '__main__':
    main()
