"""
Cenas e topos das páginas de equipamentos com fotos reais (public_html/img/equip).

As fotos vêm de scripts/site/gerar_fotos.py. Sobre cada foto entram recortes ilustrativos de
interface (cartões, medidor, calendário, checklist, assinatura), com dados fictícios, reaproveitando
os componentes de equip_art. Sem foto (ex.: plataformas), a cena usa o fundo de papel técnico.
"""
from pathlib import Path

import equip_art as ea
from equip_art import (CFG, N, O, S2, S4, S5, W, calendar, card, check, chip, circ, doc, esc, gauge, line,
                       padlock, photo, rect, signature, tick_row, txt, uid, wrench)

IMG = '/img/equip/'
COM_FOTO = {'guindaste', 'munk', 'empilhadeira', 'gerador', 'plataforma', 'compressor', 'outros'}


def base(arquivo, overlay, legenda, bg='a'):
    """Foto de fundo (recortada 480x300) com o overlay por cima; sem arquivo, papel técnico."""
    if not arquivo:
        return ea.scene(overlay, legenda, bg)
    cid = uid('cl')
    return (f'<figure class="mock"><svg viewBox="0 0 480 300" role="img" aria-label="{esc(legenda)}"><title>{esc(legenda)}</title>'
            f'<defs><clipPath id="{cid}"><rect width="480" height="300" rx="16"/></clipPath></defs>'
            f'<g clip-path="url(#{cid})"><image href="{IMG}{arquivo}" width="480" height="300" preserveAspectRatio="xMidYMid slice"/>'
            '<rect width="480" height="300" fill="#0f172a" opacity=".12"/>'
            f'{overlay}</g><rect width="480" height="300" rx="16" fill="none" stroke="#0f172a" stroke-opacity=".12"/>'
            f'</svg><figcaption>{esc(legenda)}</figcaption></figure>')


def arq(tipo, k):
    return f'{tipo}-{k}.jpg' if tipo in COM_FOTO else None


def fino(x, y, w, h, href, rx=8):
    cid = uid('ct')
    return (f'<clipPath id="{cid}"><rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}"/></clipPath>'
            f'<image href="{IMG}{href}" x="{x}" y="{y}" width="{w}" height="{h}" preserveAspectRatio="xMidYMid slice" clip-path="url(#{cid})"/>'
            f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" fill="none" stroke="#fff" stroke-width="2"/>')


# ------------------------------------------------------------------ equipamentos
def sc_preventiva(tipo):
    c = CFG[tipo]
    g = card(262, 24, 196, 176) + txt(278, 46, 'Horímetro', 11, S2, 700)
    g += gauge(360, 116, 40, c['horas'][2], c['horas'][0], 'Atual')
    g += rect(278, 172, 164, 6, S5, 3) + rect(278, 172, 164 * c['horas'][2], 6, O, 3)
    g += chip(24, 252, c['horas'][1], 'org', True)[0] + chip(24, 24, 'Preventiva por horímetro ou tempo', 'navy')[0]
    return base(arq(tipo, 1), g, f'Recorte ilustrativo: acompanhamento da preventiva por horímetro do {c["nome"]}')


def sc_os(tipo):
    c = CFG[tipo]
    g = card(18, 22, 238, 176) + txt(34, 44, 'Ordens de serviço', 12, N, 800) + chip(168, 28, c['nome'], 'navy')[0]
    for i, (nome, st) in enumerate(zip(c['os'], ['org', 'ok', 'warn'])):
        y = 58 + i * 42
        g += rect(30, y, 214, 34, '#f8fafc', 9, '#e4e7ec', 1) + wrench(38, y + 3, .8, O if i == 0 else S4)
        g += txt(66, y + 14, nome, 11, N, 700) + rect(66, y + 21, 70, 4, S5, 2)
        lab = ['Em curso', 'Feita', 'Aberta'][i]
        g += chip(238 - (20 + len(lab) * 6.3), y + 5, lab, st)[0]
    g += chip(24, 250, 'Uma OS por equipamento', 'org', True)[0]
    return base(arq(tipo, 2), g, f'Recorte ilustrativo: ordens de serviço separadas para o {c["nome"]}', 'b')


def sc_historico(tipo):
    c = CFG[tipo]
    g = card(268, 18, 194, 232) + txt(284, 40, 'Histórico individual', 12, N, 800) + txt(284, 55, c['nome'], 10.5, S2, 600)
    for i, h in enumerate(c['hist']):
        y = 76 + i * 42
        g += circ(292, y + 6, 7, O if i != 1 else '#ef4444') + (line(292, y + 14, 292, y + 42, S4, 2) if i < 3 else '')
        g += txt(308, y + 8, h, 11, N, 700) + rect(308, y + 14, 80 + (i % 2) * 30, 4, S5, 2)
    g += chip(20, 254, 'Tudo do equipamento, em um lugar', 'navy')[0]
    return base(arq(tipo, 3), g, f'Recorte ilustrativo: histórico individual do {c["nome"]}', 'c')


def sc_avarias(tipo):
    c = CFG[tipo]
    g = card(262, 22, 198, 150) + fino(274, 34, 82, 62, 'esteira.jpg') + fino(364, 34, 82, 62, 'painel.jpg')
    g += txt(274, 118, 'Avaria registrada', 11.5, N, 800) + rect(274, 126, 110, 4, S5, 2) + rect(274, 136, 80, 4, S5, 2)
    g += chip(380, 142, 'Em análise', 'warn')[0] + chip(24, 24, 'Com fotos e data', 'bad', True)[0]
    g += card(24, 232, 214, 44) + circ(44, 254, 8, '#dc2626') + txt(44, 258, '!', 11, W, 800, 'middle') + txt(60, 258, 'Cada avaria com responsável', 11, N, 700)
    return base(arq(tipo, 4), g, f'Recorte ilustrativo: avaria registrada do {c["nome"]}, com fotos', 'd')


def sc_checklist(tipo):
    c = CFG[tipo]
    g = card(18, 18, 252, 176) + txt(34, 40, 'Checklist de entrega', 12, N, 800) + chip(188, 24, 'Assinado', 'ok')[0]
    for i, it in enumerate(c['check']):
        g += tick_row(30, 54 + i * 36, 228, it, True, i == 1)
    g += signature(286, 214, 176, 54) + chip(24, 250, 'Fotos e assinatura digital', 'org', True)[0]
    return base(arq(tipo, 5), g, f'Recorte ilustrativo: checklist com fotos e assinatura digital do {c["nome"]}', 'e')


EQUIP_SCENES = (sc_preventiva, sc_os, sc_historico, sc_avarias, sc_checklist)


# ------------------------------------------------------------------ NR-13
def sc_nr13_agenda():
    g = card(262, 18, 200, 214) + txt(278, 40, 'Inspeções', 12, N, 800)
    for i, (nome, k) in enumerate([('Equipamento 01', 'warn'), ('Equipamento 02', 'ok'), ('Equipamento 03', 'ok'), ('Equipamento 04', 'bad')]):
        y = 52 + i * 44
        g += rect(274, y, 176, 36, '#f8fafc', 9, '#e4e7ec', 1) + circ(290, y + 18, 6, {'warn': '#f59e0b', 'ok': '#16a34a', 'bad': '#ef4444'}[k])
        g += txt(304, y + 16, nome, 11, N, 700) + rect(304, y + 22, 64, 4, S5, 2)
        g += txt(440, y + 22, {'warn': '!', 'ok': '✓', 'bad': '×'}[k], 14, {'warn': '#b45309', 'ok': '#16a34a', 'bad': '#dc2626'}[k], 800, 'end')
    g += calendar(24, 24, '18', 'NOV')
    return base('nr13-1.jpg', g, 'Recorte ilustrativo: agenda de inspeções de equipamentos sujeitos à NR-13', 'c')


def sc_nr13_laudo():
    g = doc(28, 28, 130, 190, O) + txt(40, 52, 'Laudo de inspeção', 11, W, 800) + txt(40, 98, 'Responsável técnico', 9.5, S2, 600) + rect(40, 104, 96, 5, S5, 2)
    g += rect(40, 124, 100, 5, S5, 2) + rect(40, 134, 76, 5, S5, 2) + rect(40, 144, 96, 5, S5, 2)
    g += ('<g transform="rotate(-14 124 182)">' + rect(72, 160, 92, 34, 'none', 6, '#16a34a', 3) + txt(118, 183, 'APROVADO', 14, '#16a34a', 800, 'middle') + '</g>')
    g += card(300, 22, 164, 60) + txt(314, 44, 'Laudo anexado', 11, N, 800) + rect(314, 52, 96, 5, S5, 2) + check(440, 44, 9)
    g += chip(190, 252, 'Laudo junto do equipamento', 'org', True)[0]
    return base('nr13-2.jpg', g, 'Recorte ilustrativo: laudo de inspeção com responsável técnico e resultado', 'a')


def sc_nr13_proxima():
    g = card(20, 24, 290, 150) + calendar(44, 48, '14', 'MAR', '#16a34a') + txt(70, 128, 'Inspeção feita', 11, S2, 700, 'middle')
    g += ea.path('M118 76 h60 m-12 -12 l12 12 l-12 12', 'none', O, 5) + calendar(196, 48, '14', 'PRÓX', O) + txt(222, 128, 'Próxima já definida', 11, ea.OD, 800, 'middle')
    g += chip(24, 196, 'A seguinte nasce agendada', 'org', True)[0] + card(24, 238, 210, 40) + circ(44, 258, 7, '#f59e0b') + txt(58, 262, 'Aviso antes de vencer', 11, N, 700)
    return base('nr13-3.jpg', g, 'Recorte ilustrativo: inspeção realizada e próxima inspeção já definida', 'b')


def sc_nr13_painel():
    g = ''
    for i, (lab, bg, fg) in enumerate([('Em dia', ea.OK_BG, ea.OK_FG), ('Vencendo', ea.WARN_BG, ea.WARN_FG), ('Vencidas', ea.BAD_BG, ea.BAD_FG)]):
        x = 20 + i * 148
        g += rect(x, 24, 138, 84, bg, 14) + circ(x + 30, 54, 12, fg) + rect(x + 54, 46, 62, 8, fg, 4, op=.35) + rect(x + 54, 62, 40, 6, fg, 3, op=.25)
        g += txt(x + 20, 96, lab, 13, fg, 800)
    g += card(20, 226, 440, 50) + circ(44, 251, 8, '#f59e0b') + txt(62, 255, 'Aviso: uma inspeção está perto de vencer', 12, N, 700) + chip(366, 238, 'Ver', 'org')[0]
    return base('nr13-4.jpg', g, 'Recorte ilustrativo: painel de conformidade com equipamentos em dia, vencendo e vencidos', 'e')


NR13_SCENES = (sc_nr13_agenda, sc_nr13_laudo, sc_nr13_proxima, sc_nr13_painel)


# ------------------------------------------------------------------ devolução com defeito
def sc_dev_volta():
    g = card(262, 22, 198, 112) + fino(274, 34, 82, 62, 'esteira.jpg') + fino(364, 34, 82, 62, 'painel.jpg') + txt(274, 120, 'Conferência com fotos', 10.5, N, 800)
    g += chip(24, 24, 'Volta da locação', 'navy')[0] + chip(24, 252, 'Defeito registrado na hora', 'bad', True)[0]
    return base('devolucao-1.jpg', g, 'Recorte ilustrativo: equipamento conferido na devolução, com a avaria registrada e fotografada', 'd')


def sc_dev_bloqueado():
    g = '<rect width="480" height="300" fill="#7f1d1d" opacity=".28"/>' + circ(350, 98, 56, W, '#fecaca', 3) + padlock(333, 70, 1.6)
    g += card(276, 176, 184, 96) + txt(292, 200, 'Vitrine comercial', 11, N, 800) + rect(292, 210, 100, 5, S5, 2)
    g += line(288, 232, 448, 232, '#ef4444', 2.5, dash='6 5') + txt(292, 256, 'Equipamento fora da lista', 10.5, ea.BAD_FG, 700)
    g += chip(24, 24, 'Bloqueado para locação', 'bad', True)[0]
    return base('devolucao-2.jpg', g, 'Recorte ilustrativo: equipamento bloqueado, fora da vitrine comercial', 'd')


def sc_dev_os():
    g = card(262, 88, 200, 180) + txt(278, 112, 'Ordem de serviço', 12, N, 800)
    for i, lab in enumerate(['Peças', 'Mão de obra', 'Custo total']):
        y = 126 + i * 44
        last = i == 2
        g += rect(274, y, 176, 36, '#fff1e4' if last else '#f8fafc', 9, ea.OL if last else '#e4e7ec', 1.2) + txt(288, y + 22, lab, 11.5, N, 800 if last else 600)
        g += rect(380, y + 14, 56, 7, ea.OD if last else S4, 3, op=.55)
    g += wrench(26, 24, 1.6, O) + chip(24, 252, 'Reparo com dono e custo', 'org', True)[0]
    return base('devolucao-3.jpg', g, 'Recorte ilustrativo: ordem de serviço com peças, mão de obra e custo total do reparo', 'a')


def sc_dev_liberado():
    g = check(404, 70, 36) + card(262, 150, 200, 122) + txt(278, 174, 'Vitrine comercial', 11, N, 800)
    g += rect(274, 186, 176, 34, ea.OK_BG, 9) + circ(292, 203, 6, '#16a34a') + txt(306, 207, 'Disponível para locar', 11, ea.OK_FG, 700) + rect(274, 232, 120, 5, S5, 2)
    g += chip(24, 24, 'Liberado', 'ok', True)[0]
    return base('devolucao-4.jpg', g, 'Recorte ilustrativo: equipamento reparado, liberado e disponível para o comercial', 'e')


DEV_SCENES = (sc_dev_volta, sc_dev_bloqueado, sc_dev_os, sc_dev_liberado)


# ------------------------------------------------------------------ topos (520x380)
def topo(arquivo, chips, rotulo):
    cid = uid('hc')
    g = ''.join(chip(x, y, t, k, True)[0] for x, y, t, k in chips)
    return (f'<svg viewBox="0 0 520 380" role="img" aria-label="{esc(rotulo)}"><title>{esc(rotulo)}</title>'
            f'<defs><clipPath id="{cid}"><rect width="520" height="380" rx="22"/></clipPath>'
            f'<linearGradient id="{cid}g" x1="0" y1="0" x2="0" y2="1"><stop offset=".55" stop-color="#0f172a" stop-opacity="0"/><stop offset="1" stop-color="#0f172a" stop-opacity=".55"/></linearGradient></defs>'
            f'<g clip-path="url(#{cid})"><image href="{IMG}{arquivo}" width="520" height="380" preserveAspectRatio="xMidYMid slice"/>'
            f'<rect width="520" height="380" fill="url(#{cid}g)"/>{g}</g>'
            '<rect width="520" height="380" rx="22" fill="none" stroke="#fff" stroke-opacity=".25"/></svg>')


def topo_ui(chips_texto):
    """Sem foto: painel escuro com cartões de interface."""
    g = ('<defs><linearGradient id="tu" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0b1630"/><stop offset="1" stop-color="#14305e"/></linearGradient></defs>'
         '<rect width="520" height="380" rx="22" fill="url(#tu)"/>'
         + card(30, 36, 220, 160) + txt(46, 60, 'Horímetro', 11, S2, 700) + gauge(140, 128, 38, .72, '860 h', 'Atual')
         + card(272, 36, 218, 120) + txt(288, 60, 'Checklist de entrega', 12, N, 800) + tick_row(284, 72, 194, 'Cesto e guarda-corpo', True, True) + tick_row(284, 108, 194, 'Sistema de elevação', True)
         + signature(272, 176, 218, 64) + chip(30, 330, chips_texto[0], 'ok', True)[0] + chip(260, 330, chips_texto[1], 'org', True)[0])
    return f'<svg viewBox="0 0 520 380" role="img" aria-label="Recortes de interface: horímetro, checklist e assinatura"><title>Recortes de interface: horímetro, checklist e assinatura</title>{g}</svg>'


def topo_colagem():
    cid = uid('hk')
    cels = [('guindaste-capa.jpg', 0, 0), ('munk-capa.jpg', 262, 0), ('empilhadeira-capa.jpg', 0, 192), ('gerador-capa.jpg', 262, 192)]
    g = ''.join(f'<image href="{IMG}{f}" x="{x}" y="{y}" width="258" height="188" preserveAspectRatio="xMidYMid slice"/>' for f, x, y in cels)
    return (f'<svg viewBox="0 0 520 380" role="img" aria-label="Fotos de guindaste, munk, empilhadeira e gerador"><title>Fotos de guindaste, munk, empilhadeira e gerador</title>'
            f'<defs><clipPath id="{cid}"><rect width="520" height="380" rx="22"/></clipPath></defs><g clip-path="url(#{cid})"><rect width="520" height="380" fill="#0f172a"/>{g}'
            + chip(24, 328, 'Frota inteira sob controle', 'org', True)[0] + '</g></svg>')


HERO = {
    'guindaste': lambda: topo('guindaste-capa.jpg', [(24, 24, 'Revisão em dia', 'ok'), (24, 332, 'Histórico individual', 'org')], 'Foto de um guindaste içando uma carga em obra'),
    'munk': lambda: topo('munk-capa.jpg', [(24, 24, 'Checklist assinado', 'ok'), (24, 332, 'Preventiva programada', 'org')], 'Foto de um caminhão munk descarregando tubos em obra'),
    'plataforma': lambda: topo('plataforma-capa.jpg', [(24, 24, 'Entrega comprovada', 'ok'), (24, 332, 'Fotos e assinatura', 'org')], 'Foto de plataformas elevatórias articulada e tesoura em uma obra'),
    'empilhadeira': lambda: topo('empilhadeira-capa.jpg', [(24, 24, 'Disponível', 'ok'), (24, 332, 'Revisão por horímetro', 'org')], 'Foto de uma empilhadeira em armazém'),
    'gerador': lambda: topo('gerador-capa.jpg', [(24, 24, 'Pronto para locar', 'ok'), (24, 332, 'Troca de óleo em dia', 'org')], 'Foto de um grupo gerador em obra'),
    'compressor': lambda: topo('compressor-capa.jpg', [(24, 24, 'Revisão em dia', 'ok'), (24, 332, 'Inspeção NR-13 agendada', 'org')], 'Foto de técnicos junto a um compressor e equipamentos industriais'),
    'outros': lambda: topo('outros-capa.jpg', [(24, 24, 'Frota organizada', 'ok'), (24, 332, 'Histórico individual', 'org')], 'Foto de um pátio de equipamentos com escavadeira e empilhadeira'),
    'nr13': lambda: topo('nr13-capa.jpg', [(24, 24, 'Laudo anexado', 'ok'), (24, 332, 'Aviso antes de vencer', 'warn')], 'Foto de técnicos inspecionando equipamentos industriais'),
    'devolucao': lambda: topo('devolucao-capa.jpg', [(24, 24, 'Avaria registrada', 'bad'), (24, 332, 'Bloqueado → liberado', 'org')], 'Foto de uma escavadeira em manutenção ao lado de uma carreta'),
    'hub': lambda: '<video autoplay muted loop playsinline preload="metadata" poster="/img/equip/frota-poster.jpg" aria-label="Equipe da Oravel em campo com tablets e celular"><source src="/img/equip/frota.mp4" type="video/mp4"></video>',
}
