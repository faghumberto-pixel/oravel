"""
Ilustrações vetoriais (SVG inline) dos equipamentos e cenas das páginas de equipamentos.

Cada equipamento é desenhado numa caixa de 320x200 (chão em y=185, de frente para a direita).
As cenas combinam o desenho com recortes ilustrativos de interface (cartões, medidor, calendário,
marcadores de avaria, assinatura). Dados sempre fictícios.
"""
import html
import math

# paleta
O, OD, OL = '#f97316', '#c2410c', '#fdba74'
N, S1, S2, S3, S4, S5 = '#0f172a', '#334155', '#64748b', '#94a3b8', '#cbd5e1', '#e2e8f0'
W, GL, YL = '#ffffff', '#bae6fd', '#facc15'
OK_BG, OK_FG = '#dcfce7', '#166534'
WARN_BG, WARN_FG = '#fef3c7', '#92400e'
BAD_BG, BAD_FG = '#fee2e2', '#991b1b'

_uid = [0]


def uid(p='g'):
    _uid[0] += 1
    return f'{p}{_uid[0]}'


def esc(s):
    return html.escape(s)


def txt(x, y, s, size=11, fill=N, w=600, anchor='start'):
    return (f'<text x="{x}" y="{y}" font-family="Inter,Segoe UI,sans-serif" font-size="{size}" '
            f'font-weight="{w}" fill="{fill}" text-anchor="{anchor}">{esc(s)}</text>')


def rect(x, y, w, h, fill=S5, rx=0, stroke=None, sw=1, op=None):
    a = f' stroke="{stroke}" stroke-width="{sw}"' if stroke else ''
    o = f' opacity="{op}"' if op is not None else ''
    return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{rx}" fill="{fill}"{a}{o}/>'


def circ(cx, cy, r, fill=S5, stroke=None, sw=1, op=None):
    a = f' stroke="{stroke}" stroke-width="{sw}"' if stroke else ''
    o = f' opacity="{op}"' if op is not None else ''
    return f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{fill}"{a}{o}/>'


def line(x0, y0, x1, y1, stroke=N, sw=2, cap='round', dash=None):
    d = f' stroke-dasharray="{dash}"' if dash else ''
    return f'<line x1="{x0}" y1="{y0}" x2="{x1}" y2="{y1}" stroke="{stroke}" stroke-width="{sw}" stroke-linecap="{cap}"{d}/>'


def path(d, fill='none', stroke=None, sw=1, op=None, cap='round'):
    a = f' stroke="{stroke}" stroke-width="{sw}" stroke-linecap="{cap}" stroke-linejoin="round"' if stroke else ''
    o = f' opacity="{op}"' if op is not None else ''
    return f'<path d="{d}" fill="{fill}"{a}{o}/>'


def seg(x0, y0, x1, y1, th, fill, stroke=None):
    dx, dy = x1 - x0, y1 - y0
    L = math.hypot(dx, dy) or 1
    nx, ny = -dy / L * th / 2, dx / L * th / 2
    pts = f'{x0+nx:.1f},{y0+ny:.1f} {x1+nx:.1f},{y1+ny:.1f} {x1-nx:.1f},{y1-ny:.1f} {x0-nx:.1f},{y0-ny:.1f}'
    a = f' stroke="{stroke}" stroke-width="1.5" stroke-linejoin="round"' if stroke else ''
    return f'<polygon points="{pts}" fill="{fill}"{a}/>'


def wheel(cx, cy, r):
    return (circ(cx, cy, r, '#1e293b') + circ(cx, cy, r * .56, S4) + circ(cx, cy, r * .22, S2)
            + ''.join(line(cx, cy, cx + math.cos(a) * r * .5, cy + math.sin(a) * r * .5, S2, 1.2)
                      for a in [i * math.pi / 3 for i in range(6)]))


def shadow(cx=160, w=150):
    return f'<ellipse cx="{cx}" cy="186" rx="{w}" ry="5" fill="#000" opacity=".13"/>'


def person(x, y, vest=YL, s=1.0):
    """Operador com capacete (x,y = pés)."""
    return (f'<g transform="translate({x} {y}) scale({s})">'
            + rect(-5, -22, 4.5, 22, S1, 2) + rect(0.5, -22, 4.5, 22, S1, 2)
            + rect(-8, -44, 16, 24, vest, 4) + rect(-8, -36, 16, 3, W, 0, op=.7)
            + circ(0, -51, 6.5, '#f1c9a5') + path('M-7.5 -53 A7.5 7.5 0 0 1 7.5 -53 Z', W)
            + rect(-8.5, -53, 17, 2.5, '#e2e8f0', 1) + '</g>')


# ------------------------------------------------------------------ equipamentos (caixa 320x200)
# Desenho técnico: traço fino azul-marinho, preenchimento claro e laranja só nos conjuntos principais.
PAPER = dict(INK='#12315a', FILL='#ffffff', FILL2='#e6eef8', DET='#6b86a8', ACC=O, ACCL='#ffe6d1', TIRE='#d5e2f1', GLASS='#cfe6fb')
DARK = dict(INK='#d3e8ff', FILL='#0e2748', FILL2='#143560', DET='#6f93c0', ACC=O, ACCL='#17406f', TIRE='#08182f', GLASS='#1c4a7a')
T = dict(PAPER)


def use_theme(nome):
    T.clear()
    T.update(PAPER if nome == 'paper' else DARK)


def ol(d, fill=None, sw=1.3):
    return f'<path d="{d}" fill="{fill or T["FILL"]}" stroke="{T["INK"]}" stroke-width="{sw}" stroke-linejoin="round" stroke-linecap="round"/>'


def bx(x, y, w, h, r=0, fill=None, sw=1.3):
    return f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="{r}" fill="{fill or T["FILL"]}" stroke="{T["INK"]}" stroke-width="{sw}"/>'


def ci(cx, cy, r, fill=None, sw=1.3):
    return f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="{fill or T["FILL"]}" stroke="{T["INK"]}" stroke-width="{sw}"/>'


def ln(x0, y0, x1, y1, sw=.8, dash=None, col=None):
    d = f' stroke-dasharray="{dash}"' if dash else ''
    return f'<line x1="{x0}" y1="{y0}" x2="{x1}" y2="{y1}" stroke="{col or T["DET"]}" stroke-width="{sw}" stroke-linecap="round"{d}/>'


def bar(x0, y0, x1, y1, th, fill=None, sw=1.3):
    dx, dy = x1 - x0, y1 - y0
    L = math.hypot(dx, dy) or 1
    nx, ny = -dy / L * th / 2, dx / L * th / 2
    pts = f'{x0+nx:.1f},{y0+ny:.1f} {x1+nx:.1f},{y1+ny:.1f} {x1-nx:.1f},{y1-ny:.1f} {x0-nx:.1f},{y0-ny:.1f}'
    return f'<polygon points="{pts}" fill="{fill or T["FILL"]}" stroke="{T["INK"]}" stroke-width="{sw}" stroke-linejoin="round"/>'


def hatch(x, y, w, h, step=4):
    cid = uid('cp')
    ls = ''.join(f'<line x1="{x+i}" y1="{y+h}" x2="{x+i+h}" y2="{y}" stroke="{T["DET"]}" stroke-width=".6"/>' for i in range(-int(h), int(w), step))
    return f'<clipPath id="{cid}"><rect x="{x}" y="{y}" width="{w}" height="{h}"/></clipPath><g clip-path="url(#{cid})">{ls}</g>'


def tire(cx, cy, r):
    g = ci(cx, cy, r, T['TIRE'], 1.5)
    g += f'<circle cx="{cx}" cy="{cy}" r="{r*.84:.1f}" fill="none" stroke="{T["DET"]}" stroke-width=".8" stroke-dasharray="2 2"/>'
    g += ci(cx, cy, r * .6, T['FILL'], 1.2) + ci(cx, cy, r * .2, T['INK'], 1)
    for i in range(6):
        a = i * math.pi / 3
        g += f'<circle cx="{cx+math.cos(a)*r*.4:.1f}" cy="{cy+math.sin(a)*r*.4:.1f}" r="{max(r*.045, .8):.1f}" fill="{T["INK"]}"/>'
    return g


def center(x0, y, x1):
    return ln(x0, y, x1, y, .7, '10 3 2 3')


def gauge_dial(cx, cy, r):
    g = ci(cx, cy, r, T['FILL'], 1.4) + ci(cx, cy, r * .78, 'none', .7)
    for i in range(9):
        a = math.radians(150 + 30 * i)
        g += ln(cx + math.cos(a) * r * .66, cy + math.sin(a) * r * .66, cx + math.cos(a) * r * .8, cy + math.sin(a) * r * .8, .8, col=T['INK'])
    a = math.radians(300)
    g += f'<line x1="{cx}" y1="{cy}" x2="{cx+math.cos(a)*r*.62:.1f}" y2="{cy+math.sin(a)*r*.62:.1f}" stroke="{O}" stroke-width="1.6" stroke-linecap="round"/>' + ci(cx, cy, 1.8, T['INK'], .8)
    return g


def bolts(xs, y, r=1.1):
    return ''.join(f'<circle cx="{x}" cy="{y}" r="{r}" fill="{T["INK"]}"/>' for x in xs)


def eq_empilhadeira():
    g = ln(8, 185, 312, 185, .9)
    g += ol('M28 172 V126 Q28 108 46 108 H120 L124 140 H205 V172 Z', T['ACCL'])
    g += f'<rect x="28" y="150" width="177" height="6" fill="{O}" stroke="{T["INK"]}" stroke-width="1"/>'
    g += ln(62, 112, 62, 172, .8) + ln(124, 140, 124, 172, .8)
    g += ''.join(ln(140, 146 + i * 4, 188, 146 + i * 4, .7) for i in range(4))
    g += bx(70, 94, 38, 14, 5, T['FILL2']) + bx(66, 66, 10, 42, 4, T['FILL2'])
    g += ln(150, 140, 162, 108, 2.4, col=T['INK']) + f'<ellipse cx="162" cy="106" rx="12" ry="4" fill="none" stroke="{T["INK"]}" stroke-width="1.6"/>'
    g += bx(112, 46, 5, 96, 1.5) + bx(198, 46, 5, 96, 1.5) + bx(104, 40, 108, 8, 3)
    g += ''.join(ln(116 + i * 17, 41, 116 + i * 17, 47, .8, col=T['INK']) for i in range(6))
    g += bx(154, 32, 10, 8, 2, O)
    g += tire(64, 160, 17) + tire(176, 158, 24)
    g += bx(214, 22, 8, 160, 1.5, T['FILL2']) + bx(233, 22, 8, 160, 1.5, T['FILL2']) + bx(222, 62, 11, 120, 1, T['FILL'])
    g += bx(212, 22, 31, 5, 1.5) + bx(212, 172, 31, 6, 1.5)
    g += ln(228, 28, 228, 100, .8, '2 2', T['INK']) + bx(226, 74, 4, 98, 1, T['FILL2'], .9)
    g += bx(243, 92, 8, 86, 2) + ''.join(ln(243, 100 + i * 14, 251, 100 + i * 14, .8) for i in range(5))
    g += ol('M243 172 H312 L317 178 H243 Z', T['FILL2'])
    g += bx(257, 124, 52, 48, 2, T['ACCL']) + ln(257, 148, 309, 148, .8) + ln(283, 124, 283, 172, .8) + ln(257, 124, 309, 172, .7, '3 2')
    g += center(10, 160, 120)
    return g


def eq_gerador():
    g = ln(8, 185, 312, 185, .9)
    g += bx(28, 150, 252, 12, 2, T['FILL2']) + ol('M8 162 L32 150 V162 Z', T['FILL2']) + ci(10, 160, 5, 'none', 1.3)
    g += bx(36, 164, 4, 14, 1, T['FILL2']) + bx(30, 177, 16, 4, 1, T['FILL2'])
    g += bx(40, 60, 232, 90, 6, T['ACCL']) + bx(36, 54, 240, 10, 5, T['FILL2'])
    g += ''.join(bx(54, 76 + i * 9, 92, 4.5, 1.5, T['FILL'], .9) for i in range(7))
    g += bx(158, 76, 104, 68, 5, 'none') + bx(244, 102, 6, 18, 2, T['FILL2'])
    g += bx(170, 86, 52, 30, 3, T['FILL2']) + bx(175, 91, 42, 13, 1.5, O, 1) + ln(179, 97, 205, 97, 1.4, col=T['INK'])
    g += ci(231, 93, 3.5, T['FILL'], 1) + ci(231, 105, 3.5, O, 1)
    g += bolts([176, 216, 176, 216], 111) + bolts([164, 256], 82) + bolts([164, 256], 138)
    g += ol('M200 120 l-11 17 h8 l-4 14 l15 -21 h-9 l5 -10z', O, 1)
    g += bx(54, 132, 52, 10, 1, T['FILL']) + ln(58, 137, 100, 137, .7)
    g += bx(242, 24, 10, 32, 1.5) + bx(238, 20, 18, 6, 2) + hatch(242, 24, 10, 32, 3)
    g += f'<path d="M247 14 q6 -4 0 -9 q-6 -4 0 -9" fill="none" stroke="{T["DET"]}" stroke-width="1" stroke-dasharray="2 2"/>'
    g += bx(264, 112, 14, 34, 3, T['FILL2']) + ci(271, 122, 3, T['FILL'], 1) + ci(271, 132, 3, T['FILL'], 1) + ci(271, 142, 3, T['FILL'], 1)
    g += ol('M170 152 a20 20 0 0 1 40 0 Z', T['FILL2']) + tire(190, 168, 17)
    g += center(30, 105, 290)
    return g


def eq_tesoura():
    g = ln(8, 185, 312, 185, .9)
    g += bx(70, 158, 190, 18, 4, T['ACCL']) + f'<rect x="70" y="168" width="190" height="4" fill="{O}" stroke="{T["INK"]}" stroke-width="1"/>'
    h, y = 30, 158
    for i in range(3):
        y0, y1 = y - h * i, y - h * (i + 1)
        g += bar(112, y0, 218, y1, 7, T['FILL']) + bar(218, y0, 112, y1, 7, T['FILL'])
        g += ci(165, (y0 + y1) / 2, 4.5, T['FILL2'], 1.2) + ln(112, y0, 112, y1, .6, '2 2') + ln(218, y0, 218, y1, .6, '2 2')
    g += bx(100, 64, 16, 8, 2, T['FILL2']) + bx(210, 64, 16, 8, 2, T['FILL2'])
    g += bar(150, 150, 190, 108, 4, T['FILL2'], 1)
    g += tire(98, 173, 12) + tire(232, 173, 12)
    g += bx(78, 60, 176, 10, 2, T['FILL2']) + bx(250, 62, 40, 5, 1.5, T['FILL2']) + bx(80, 54, 172, 6, 1.5)
    g += ''.join(bx(x, 24, 4, 30, 1) for x in (84, 166, 246)) + bx(80, 22, 172, 4, 2, O, 1.1) + bx(80, 38, 172, 3, 1.5)
    g += ''.join(ln(x, 26, x, 38, .5) for x in range(96, 246, 12))
    g += bx(122, 44, 20, 10, 2, T['FILL2']) + ci(128, 49, 2, O, .8)
    g += center(60, 100, 270)
    return g


def eq_articulada():
    g = ln(8, 185, 312, 185, .9)
    g += bx(48, 134, 40, 24, 6, T['FILL2']) + bx(60, 156, 132, 20, 6, T['ACCL'])
    g += tire(92, 172, 13) + tire(166, 172, 13)
    g += bx(96, 126, 64, 30, 6, T['ACCL']) + bx(104, 118, 20, 10, 3, T['FILL2'])
    g += bar(142, 136, 214, 60, 16, T['ACCL']) + bar(150, 130, 206, 68, 5, 'none', .8)
    g += bar(154, 148, 198, 94, 5, T['FILL2'], 1)
    g += ci(214, 60, 9, T['FILL']) + ci(214, 60, 3, T['INK'], 1)
    g += bar(214, 60, 272, 42, 12, T['ACCL']) + bar(224, 74, 258, 56, 4, T['FILL2'], 1)
    g += bx(258, 38, 48, 28, 3) + ln(258, 48, 306, 48, .9, col=T['INK']) + ln(258, 57, 306, 57, .9, col=T['INK'])
    g += ''.join(ln(x, 38, x, 66, .6) for x in (270, 282, 294))
    g += ci(142, 136, 4, T['FILL2'], 1.1)
    g += center(40, 100, 140)
    return g


def eq_guindaste():
    g = ln(8, 185, 312, 185, .9)
    g += bx(24, 130, 240, 28, 5, T['ACCL'])
    g += ol('M222 130 V102 Q222 92 232 92 H258 L278 118 V130 Z', T['ACCL']) + ol('M232 99 H254 L267 118 H232 Z', T['GLASS'], 1.1)
    g += ci(274, 124, 3, O, .9) + ln(30, 150, 262, 150, .8)
    g += bx(60, 104, 118, 28, 5, T['FILL2']) + bx(66, 98, 106, 14, 5, T['FILL'])
    g += bx(134, 82, 42, 28, 4, T['ACCL']) + bx(142, 88, 26, 16, 3, T['GLASS'], 1.1) + bx(52, 100, 18, 30, 4, T['FILL2'])
    for x in (92, 132, 214, 252):
        g += tire(x, 162, 18)
    g += bx(56, 160, 8, 22, 2, T['FILL2']) + bx(48, 180, 24, 5, 1.5) + bx(226, 160, 8, 22, 2, T['FILL2']) + bx(218, 180, 24, 5, 1.5)
    px, py, tx, ty = 150, 96, 50, 14

    def at(f):
        return px + (tx - px) * f, py + (ty - py) * f
    a, b, c, d, e, f_ = at(0), at(.46), at(.42), at(.78), at(.74), at(1)
    g += ln(112, 108, 80, 64, 4, col=T['INK']) + ln(112, 108, 80, 64, 2.4, col=T['FILL'])
    g += bar(*a, *b, 24, T['ACCL']) + bar(*c, *d, 17, T['FILL']) + bar(*e, *f_, 11, T['FILL2'])
    g += ln(*at(.05), *at(.95), .7, '8 3 2 3')
    g += ci(px, py, 8, T['FILL']) + ci(px, py, 2.5, T['INK'], 1)
    g += ln(f_[0], f_[1], f_[0], 92, 1, col=T['INK'])
    g += bx(f_[0] - 6, 88, 12, 14, 3, T['FILL2']) + f'<path d="M{f_[0]} 102 v6 a6 6 0 1 1 -6 -3" fill="none" stroke="{T["INK"]}" stroke-width="2.2"/>'
    g += ln(f_[0], 102, f_[0] - 22, 124, .9, col=T['INK']) + ln(f_[0], 102, f_[0] + 22, 124, .9, col=T['INK'])
    g += bx(f_[0] - 30, 124, 60, 26, 3, T['FILL2']) + hatch(f_[0] - 30, 124, 60, 26, 4)
    return g


def eq_munk():
    g = ln(8, 185, 312, 185, .9)
    g += bx(18, 134, 238, 14, 3, T['FILL2']) + bx(24, 116, 150, 20, 3, T['FILL']) + bx(24, 102, 150, 6, 2, T['FILL2'])
    g += ''.join(ci(40 + i * 22, 110, 9, T['FILL2'], 1.2) + ci(40 + i * 22, 110, 4.5, T['FILL'], 1) for i in range(6))
    g += ol('M184 134 V92 Q184 80 196 80 H232 L260 106 V134 Z', T['ACCL']) + ol('M194 87 H228 L246 106 H194 Z', T['GLASS'], 1.1)
    g += ln(222, 106, 222, 134, .9) + bx(252, 124, 12, 10, 2, T['FILL2']) + ci(254, 116, 3.4, O, .9)
    g += bx(158, 106, 28, 12, 3, T['FILL2']) + bx(164, 60, 16, 48, 3, T['ACCL']) + ci(172, 62, 6, T['FILL'], 1.2)
    g += bar(172, 66, 114, 16, 14, T['ACCL']) + ci(114, 16, 6.5, T['FILL']) + ci(114, 16, 2, T['INK'], 1)
    g += bar(114, 16, 54, 56, 11, T['ACCL']) + bar(54, 56, 34, 70, 7, T['FILL2'])
    g += bar(166, 90, 124, 38, 4, T['FILL2'], 1) + bar(120, 30, 70, 50, 3.5, T['FILL2'], 1)
    g += ln(34, 70, 34, 92, 1.2, col=T['INK']) + bx(28, 90, 12, 8, 2, T['FILL2'])
    g += ln(34, 98, 20, 106, .8, col=T['INK']) + ln(34, 98, 48, 106, .8, col=T['INK']) + bx(12, 106, 44, 9, 3, T['FILL2'])
    g += bx(112, 146, 40, 6, 2, T['FILL2']) + bx(144, 150, 6, 32, 2, T['FILL2']) + bx(138, 180, 18, 5, 1.5)
    g += tire(58, 162, 17) + tire(90, 162, 17) + tire(224, 162, 17)
    return g


def eq_vaso():
    g = ln(8, 185, 312, 185, .9)
    g += ol('M86 140 L74 182 H118 L112 140 Z', T['FILL2']) + ol('M208 140 L204 182 H248 L236 140 Z', T['FILL2'])
    g += hatch(76, 150, 40, 30, 4) + hatch(206, 150, 40, 30, 4)
    g += bx(44, 64, 232, 82, 41, T['FILL2'])
    g += f'<path d="M62 78 H258" stroke="{T["FILL"]}" stroke-width="5" stroke-linecap="round" opacity=".8"/>'
    g += ln(94, 66, 94, 144, 1, col=T['INK']) + ln(226, 66, 226, 144, 1, col=T['INK'])
    g += bx(156, 38, 8, 28, 1.5) + bx(146, 36, 28, 6, 2) + bolts([150, 158, 166, 170], 39, 1)
    g += gauge_dial(160, 22, 17)
    g += bx(222, 42, 8, 24, 1.5) + bx(214, 32, 24, 12, 3, O, 1.2) + bx(222, 26, 8, 6, 2)
    g += bx(150, 146, 10, 14, 1.5) + ci(155, 163, 6, T['FILL'], 1.3)
    g += bx(112, 94, 44, 28, 2) + ln(118, 102, 148, 102, .8) + ln(118, 109, 140, 109, .8) + ln(118, 116, 144, 116, .8)
    g += bx(40, 100, 6, 10, 1.5, T['FILL2']) + bx(274, 100, 6, 10, 1.5, T['FILL2'])
    g += center(20, 105, 300)
    return g


def eq_caldeira():
    g = ln(8, 185, 312, 185, .9)
    g += bx(96, 170, 128, 12, 3, T['FILL2']) + hatch(96, 170, 128, 12, 4)
    g += ol('M104 70 Q160 30 216 70 V156 Q216 172 200 172 H120 Q104 172 104 156 Z', T['FILL2'])
    g += f'<path d="M104 70 H216" stroke="{T["INK"]}" stroke-width="1"/>'
    g += bx(150, 6, 20, 52, 1.5) + hatch(150, 6, 20, 52, 4) + bx(144, 2, 32, 8, 3)
    g += ci(160, 126, 24, T['FILL']) + ci(160, 126, 15, T['FILL2'], 1.2) + ci(160, 126, 4, T['INK'], 1)
    g += ''.join(f'<circle cx="{160+math.cos(i*math.pi/4)*20:.1f}" cy="{126+math.sin(i*math.pi/4)*20:.1f}" r="1.3" fill="{T["INK"]}"/>' for i in range(8))
    g += bx(60, 124, 40, 32, 5, T['ACCL']) + ol('M52 140 q-6 -10 2 -18 q2 10 10 8 z', O, 1) + bx(98, 136, 8, 8, 2, T['FILL2'])
    g += gauge_dial(228, 78, 14) + bx(228, 104, 7, 44, 2, T['GLASS'], 1.1) + bx(229, 126, 5, 22, 1, O, .6)
    g += ln(216, 100, 228, 100, 2, col=T['INK']) + ln(216, 150, 228, 150, 2, col=T['INK'])
    g += ln(160, -6, 160, 190, .7, '10 3 2 3')
    return g


def eq_carreta(carga=None):
    g = ln(8, 185, 312, 185, .9)
    g += bx(16, 148, 220, 10, 1.5, T['FILL2']) + bx(120, 156, 116, 5, 1.5, T['FILL2'])
    g += ol('M236 158 V112 Q236 98 250 98 H292 L316 130 V158 Z', T['ACCL']) + ol('M246 106 H288 L304 130 H246 Z', T['GLASS'], 1.1)
    g += ln(262, 130, 262, 158, .9) + bx(306, 148, 12, 10, 2, T['FILL2']) + ci(312, 138, 3.4, O, .9) + bx(228, 148, 12, 8, 2, T['FILL2'])
    g += tire(264, 164, 18) + tire(60, 164, 16) + tire(96, 164, 16) + tire(132, 164, 16)
    if carga:
        g += f'<g transform="translate(22 26) scale(.66)">{carga}</g>'
    return g


EQ = {
    'empilhadeira': eq_empilhadeira, 'gerador': eq_gerador, 'plataforma': eq_tesoura,
    'articulada': eq_articulada, 'guindaste': eq_guindaste, 'munk': eq_munk,
    'vaso': eq_vaso, 'caldeira': eq_caldeira,
}


def art(tipo, x, y, s):
    return f'<g transform="translate({x} {y}) scale({s})">{EQ[tipo]()}</g>'


# ------------------------------------------------------------------ componentes de interface
def chip(x, y, label, kind='ok', icon=None):
    bg, fg = {'ok': (OK_BG, OK_FG), 'warn': (WARN_BG, WARN_FG), 'bad': (BAD_BG, BAD_FG), 'org': ('#fff1e4', OD), 'navy': (N, W)}[kind]
    w = 20 + len(label) * 6.3 + (14 if icon else 0)
    g = rect(x + 1.5, y + 2.5, w, 24, '#0f172a', 12, op=.08) + rect(x, y, w, 24, bg, 12)
    if icon:
        g += circ(x + 14, y + 12, 4, fg)
    g += txt(x + (26 if icon else 12), y + 16, label, 11, fg, 700)
    return g, w


def card(x, y, w, h, op=1):
    return rect(x + 2, y + 4, w, h, N, 14, op=.08) + rect(x, y, w, h, W, 14, '#e4e7ec', 1.2)


def arc(cx, cy, r, a0, a1):
    p0 = (cx + r * math.cos(math.radians(a0)), cy + r * math.sin(math.radians(a0)))
    p1 = (cx + r * math.cos(math.radians(a1)), cy + r * math.sin(math.radians(a1)))
    large = 1 if (a1 - a0) > 180 else 0
    return f'M{p0[0]:.1f} {p0[1]:.1f} A{r} {r} 0 {large} 1 {p1[0]:.1f} {p1[1]:.1f}'


def gauge(cx, cy, r, pct, big, small, color=O):
    g = path(arc(cx, cy, r, 150, 390), 'none', S5, 10) + path(arc(cx, cy, r, 150, 150 + 240 * pct), 'none', color, 10)
    ang = math.radians(150 + 240 * pct)
    g += line(cx, cy, cx + (r - 14) * math.cos(ang), cy + (r - 14) * math.sin(ang), N, 3) + circ(cx, cy, 5, N)
    g += txt(cx, cy + r * .62, big, 15, N, 800, 'middle') + txt(cx, cy + r * .62 + 14, small, 10, S2, 600, 'middle')
    return g


def calendar(x, y, dia, mes, cor=O):
    return (rect(x + 2, y + 4, 52, 56, N, 8, op=.1) + rect(x, y, 52, 56, W, 8, '#e4e7ec', 1.2)
            + path(f'M{x} {y+16} V{y+8} Q{x} {y} {x+8} {y} H{x+44} Q{x+52} {y} {x+52} {y+8} V{y+16} Z', cor)
            + txt(x + 26, y + 12, mes, 9.5, W, 800, 'middle') + txt(x + 26, y + 43, dia, 24, N, 800, 'middle')
            + rect(x + 12, y - 4, 4, 9, N, 2) + rect(x + 36, y - 4, 4, 9, N, 2))


def pin(x, y, n='!', cor='#ef4444'):
    return (path(f'M{x} {y} c-12 -14 -14 -20 -14 -26 a14 14 0 1 1 28 0 c0 6 -2 12 -14 26z', cor, W, 2)
            + txt(x, y - 20, n, 14, W, 800, 'middle'))


def check(x, y, r=11, cor='#16a34a'):
    return circ(x, y, r, cor) + path(f'M{x-r*.45:.1f} {y+r*.02:.1f} l{r*.32:.1f} {r*.34:.1f} l{r*.62:.1f} -{r*.7:.1f}', 'none', W, 2.6)


def wrench(x, y, s=1, cor=O):
    return (f'<g transform="translate({x} {y}) scale({s})">'
            + path('M20 4 a8 8 0 0 0 -9 11 L2 24 a3 3 0 0 0 4 4 l9 -9 a8 8 0 0 0 11 -9 l-5 5 -4 -1 -1 -4 5 -5 z', cor) + '</g>')


def padlock(x, y, s=1, cor='#ef4444'):
    return (f'<g transform="translate({x} {y}) scale({s})">'
            + path('M8 16 V10 a8 8 0 0 1 16 0 V16', 'none', cor, 4) + rect(3, 16, 26, 20, cor, 5)
            + circ(16, 25, 3.2, W) + rect(14.8, 26, 2.4, 6, W, 1) + '</g>')


def doc(x, y, w=70, h=88, cor=O):
    g = rect(x + 3, y + 4, w, h, N, 6, op=.1) + rect(x, y, w, h, W, 6, '#e4e7ec', 1.2) + rect(x, y, w, 14, cor, 6) + rect(x, y + 8, w, 6, cor)
    for i in range(5):
        g += rect(x + 8, y + 24 + i * 10, w - 16 - (i % 2) * 14, 4, S5, 2)
    return g


def photo(x, y, w=44, h=34, dano=False):
    c1, c2 = ('#fecaca', '#f87171') if dano else ('#e2e8f0', '#94a3b8')
    return (rect(x, y, w, h, c1, 6, W, 2) + path(f'M{x+6} {y+h-6} l{w*.25:.1f} -{h*.4:.1f} l{w*.2:.1f} {h*.25:.1f} l{w*.15:.1f} -{h*.2:.1f} l{w*.25:.1f} {h*.35:.1f}z', c2)
            + circ(x + w * .74, y + h * .26, 4, c2))


def signature(x, y, w=150, h=46):
    return (rect(x, y, w, h, W, 8, S4, 1.4) + txt(x + 10, y + 14, 'Assinatura', 9.5, S2, 600)
            + path(f'M{x+14} {y+h-10} c14 -26 24 8 38 -8 s20 14 34 -4 s22 -12 32 6', 'none', N, 2.2)
            + line(x + 10, y + h - 8, x + w - 10, y + h - 8, S4, 1))


def tick_row(x, y, w, label, ok=True, thumb=None):
    g = rect(x, y, w, 30, '#f8fafc', 9, '#e4e7ec', 1) + (check(x + 16, y + 15, 8) if ok else circ(x + 16, y + 15, 8, WARN_BG, '#f59e0b', 2))
    g += txt(x + 32, y + 19, label, 11.5, N, 600)
    if thumb is not None:
        g += photo(x + w - 46, y + 3, 40, 24, thumb)
    return g


# ------------------------------------------------------------------ cenas
BGS = {
    'a': ('#f4f8fd', '#e9f1fa'), 'b': ('#f1f6fc', '#e6eff9'), 'c': ('#f5f8fb', '#eaf0f6'),
    'd': ('#fbf3f3', '#f6e7e7'), 'e': ('#f1f8f4', '#e4f1e9'),
}


def corners(w, h, col='#12315a'):
    g = ''
    for x, y, sx, sy in ((10, 10, 1, 1), (w - 10, 10, -1, 1), (10, h - 10, 1, -1), (w - 10, h - 10, -1, -1)):
        g += f'<path d="M{x} {y+sy*10} V{y} H{x+sx*10}" fill="none" stroke="{col}" stroke-width="1.2" opacity=".45"/>'
    return g


def scene(corpo, legenda, bg='a', w=480, h=300):
    gid, pid = uid('bg'), uid('gr')
    c0, c1 = BGS[bg]
    return (f'<figure class="mock"><svg viewBox="0 0 {w} {h}" role="img" aria-label="{esc(legenda)}"><title>{esc(legenda)}</title>'
            f'<defs><linearGradient id="{gid}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{c0}"/><stop offset="1" stop-color="{c1}"/></linearGradient>'
            f'<pattern id="{pid}" width="60" height="60" patternUnits="userSpaceOnUse">'
            '<path d="M0 0H60M0 12H60M0 24H60M0 36H60M0 48H60M0 0V60M12 0V60M24 0V60M36 0V60M48 0V60" stroke="#5b8fc7" stroke-width=".5" opacity=".16" fill="none"/>'
            '<path d="M0 0H60M0 0V60" stroke="#5b8fc7" stroke-width=".9" opacity=".26" fill="none"/></pattern></defs>'
            f'<rect width="{w}" height="{h}" rx="16" fill="url(#{gid})"/><rect width="{w}" height="{h}" rx="16" fill="url(#{pid})"/>'
            f'{corners(w, h)}{corpo}</svg><figcaption>{esc(legenda)}</figcaption></figure>')


def callout(px, py, n, lx, ly, cor='#dc2626'):
    return (f'<circle cx="{px}" cy="{py}" r="11" fill="none" stroke="{cor}" stroke-width="1.8" stroke-dasharray="4 3"/>'
            f'<line x1="{px}" y1="{py}" x2="{lx}" y2="{ly}" stroke="{cor}" stroke-width="1.3"/>'
            f'<circle cx="{lx}" cy="{ly}" r="9" fill="{cor}"/>' + txt(lx, ly + 4, str(n), 11, W, 800, 'middle'))


# configuração por equipamento: arte, peças (para marcadores) e textos
CFG = {
    'guindaste': dict(arte='guindaste', nome='Guindaste 01', pecas=[('Lança', 120, 60), ('Patola', 240, 150), ('Cabo de aço', 70, 100)],
                      check=['Lança e cabos', 'Patolas e estabilizadores', 'Itens de segurança'], os=['Revisão da lança', 'Troca de cabo', 'Inspeção geral'],
                      hist=['Revisão concluída', 'Avaria registrada', 'Entrega assinada', 'Troca de cabo'], horas=('1.180 h', 'Revisão em 70 h', .84)),
    'munk': dict(arte='munk', nome='Munk 01', pecas=[('Lança articulada', 100, 40), ('Estabilizador', 150, 150), ('Cabine', 220, 100)],
                 check=['Lança e cilindros', 'Estabilizadores', 'Carroceria e cabine'], os=['Revisão hidráulica', 'Troca de mangueira', 'Inspeção geral'],
                 hist=['Revisão concluída', 'Avaria registrada', 'Entrega assinada', 'Troca de mangueira'], horas=('2.340 h', 'Revisão em 160 h', .62)),
    'plataforma': dict(arte='plataforma', nome='Plataforma 01', pecas=[('Cesto e guarda-corpo', 160, 30), ('Tesoura', 165, 100), ('Pneus', 232, 175)],
                       check=['Cesto e guarda-corpo', 'Sistema de elevação', 'Comandos e segurança'], os=['Revisão da tesoura', 'Troca de bateria', 'Inspeção geral'],
                       hist=['Revisão concluída', 'Avaria registrada', 'Entrega assinada', 'Troca de bateria'], horas=('860 h', 'Revisão em 140 h', .72)),
    'empilhadeira': dict(arte='empilhadeira', nome='Empilhadeira 01', pecas=[('Garfos', 285, 175), ('Mastro', 226, 80), ('Pneus', 176, 158)],
                         check=['Garfos e mastro', 'Freios e direção', 'Itens de segurança'], os=['Revisão do mastro', 'Troca de pneus', 'Inspeção geral'],
                         hist=['Revisão concluída', 'Avaria registrada', 'Entrega assinada', 'Troca de pneus'], horas=('3.050 h', 'Revisão em 50 h', .9)),
    'gerador': dict(arte='gerador', nome='Gerador 01', pecas=[('Painel de comando', 195, 100), ('Escapamento', 247, 40), ('Tomadas', 271, 128)],
                    check=['Painel e comandos', 'Motor e filtros', 'Itens de segurança'], os=['Troca de óleo e filtros', 'Revisão do painel', 'Inspeção geral'],
                    hist=['Revisão concluída', 'Avaria registrada', 'Entrega assinada', 'Troca de filtros'], horas=('1.520 h', 'Revisão em 80 h', .76)),
}


def sc_preventiva(tipo):
    c = CFG[tipo]
    g = art(c['arte'], 8, 70, .92)
    g += card(268, 22, 192, 150)
    g += txt(284, 44, 'Horímetro', 11, S2, 700) + gauge(364, 112, 40, c['horas'][2], c['horas'][0], 'Atual')
    g += rect(284, 160, 160, 6, S5, 3) + rect(284, 160, 160 * c['horas'][2], 6, O, 3)
    ch, w = chip(250, 230, c['horas'][1], 'org', True)
    g += ch
    ch, w = chip(24, 24, 'Preventiva por horímetro ou tempo', 'navy')
    return scene(g + ch, f'Ilustração: {c["nome"]} com acompanhamento da manutenção preventiva por horímetro', 'a')


def sc_os(tipo):
    c = CFG[tipo]
    g = art(c['arte'], 196, 112, .78)
    g += card(18, 22, 238, 172)
    g += txt(34, 44, 'Ordens de serviço', 12, N, 800) + chip(168, 28, c['nome'], 'navy')[0]
    for i, (nome, st) in enumerate(zip(c['os'], ['org', 'ok', 'warn'])):
        y = 58 + i * 42
        g += rect(30, y, 214, 34, '#f8fafc', 9, '#e4e7ec', 1) + wrench(38, y + 3, .8, O if i == 0 else S3)
        g += txt(66, y + 14, nome, 11, N, 700) + rect(66, y + 21, 70, 4, S5, 2)
        lab = ['Em curso', 'Feita', 'Aberta'][i]
        g += chip(238 - (20 + len(lab) * 6.3), y + 5, lab, st)[0]
    g += chip(40, 236, 'Uma OS por equipamento', 'org', True)[0]
    return scene(g, f'Ilustração: ordens de serviço separadas para o {c["nome"]}', 'b')


def sc_historico(tipo):
    c = CFG[tipo]
    g = art(c['arte'], -6, 52, .86)
    g += card(268, 18, 194, 220)
    g += txt(284, 40, 'Histórico individual', 12, N, 800) + txt(284, 55, c['nome'], 10.5, S2, 600)
    for i, h in enumerate(c['hist']):
        y = 76 + i * 40
        g += circ(292, y + 6, 7, O if i != 1 else '#ef4444') + (line(292, y + 14, 292, y + 40, S4, 2) if i < 3 else '')
        g += txt(308, y + 8, h, 11, N, 700) + rect(308, y + 14, 80 + (i % 2) * 30, 4, S5, 2)
    g += chip(20, 250, 'Tudo do equipamento, em um lugar', 'navy')[0]
    return scene(g, f'Ilustração: histórico individual do {c["nome"]}', 'c')


def sc_avarias(tipo):
    c = CFG[tipo]
    g = art(c['arte'], 40, 44, 1.05)
    for i, (peca, px, py) in enumerate(c['pecas'][:2]):
        x, y = 40 + px * 1.05, 44 + py * 1.05
        g += callout(x, y, i + 1, x + (26 if i == 0 else -26), y - 34, '#dc2626' if i == 0 else '#d97706')
    g += card(300, 24, 164, 98) + photo(312, 36, 56, 44, True) + photo(374, 36, 56, 44, False)
    g += txt(312, 98, 'Avaria registrada', 11, N, 800) + rect(312, 106, 100, 4, S5, 2)
    g += chip(26, 24, 'Com fotos e data', 'bad', True)[0]
    g += card(26, 236, 266, 46)
    g += circ(46, 259, 8, '#dc2626') + txt(46, 263, '1', 10, W, 800, 'middle') + txt(60, 263, c['pecas'][0][0], 11, N, 700) + circ(190, 259, 8, '#d97706') + txt(190, 263, '2', 10, W, 800, 'middle') + txt(204, 263, c['pecas'][1][0], 11, N, 700)
    return scene(g, f'Ilustração: avarias do {c["nome"]} marcadas nas peças, com fotos', 'd')


def sc_checklist(tipo):
    c = CFG[tipo]
    g = art(c['arte'], 272, 120, .6)
    g += card(18, 18, 252, 188)
    g += txt(34, 40, 'Checklist de entrega', 12, N, 800) + chip(188, 24, 'Assinado', 'ok')[0]
    for i, it in enumerate(c['check']):
        g += tick_row(30, 54 + i * 36, 228, it, True, i == 1)
    g += signature(20, 224, 200, 56)
    g += chip(262, 238, 'Fotos e assinatura digital', 'org', True)[0]
    return scene(g, f'Ilustração: checklist com fotos e assinatura digital do {c["nome"]}', 'e')


EQUIP_SCENES = (sc_preventiva, sc_os, sc_historico, sc_avarias, sc_checklist)


# ---- NR-13
def sc_nr13_agenda():
    g = art('vaso', 8, 96, .86)
    g += card(262, 20, 200, 214)
    g += txt(278, 42, 'Inspeções', 12, N, 800)
    for i, (nome, quando, k) in enumerate([('Equipamento 01', 'em breve', 'warn'), ('Equipamento 02', 'em dia', 'ok'), ('Equipamento 03', 'em dia', 'ok'), ('Equipamento 04', 'vencida', 'bad')]):
        y = 54 + i * 44
        g += rect(274, y, 176, 36, '#f8fafc', 9, '#e4e7ec', 1) + circ(290, y + 18, 6, {'warn': '#f59e0b', 'ok': '#16a34a', 'bad': '#ef4444'}[k])
        g += txt(304, y + 16, nome, 11, N, 700) + rect(304, y + 22, 64, 4, S5, 2)
        g += txt(440, y + 22, {'warn': '!', 'ok': '✓', 'bad': '×'}[k], 14, {'warn': '#b45309', 'ok': '#16a34a', 'bad': '#dc2626'}[k], 800, 'end')
    g += calendar(30, 30, '18', 'NOV')
    return scene(g, 'Ilustração: agenda de inspeções de equipamentos sujeitos à NR-13', 'c')


def sc_nr13_laudo():
    g = doc(34, 36, 130, 190, O)
    g += txt(46, 60, 'Laudo de inspeção', 11, W, 800) + txt(46, 106, 'Responsável técnico', 9.5, S2, 600) + rect(46, 112, 96, 5, S5, 2)
    g += rect(46, 132, 100, 5, S5, 2) + rect(46, 142, 76, 5, S5, 2) + rect(46, 152, 96, 5, S5, 2)
    g += (f'<g transform="rotate(-14 130 190)">' + rect(78, 168, 92, 34, 'none', 6, '#16a34a', 3) + txt(124, 191, 'APROVADO', 14, '#16a34a', 800, 'middle') + '</g>')
    g += art('caldeira', 186, 74, .8)
    g += card(300, 20, 164, 60) + txt(314, 42, 'Laudo anexado', 11, N, 800) + rect(314, 50, 96, 5, S5, 2) + check(440, 42, 9)
    g += chip(190, 250, 'Laudo junto do equipamento', 'org', True)[0]
    return scene(g, 'Ilustração: laudo de inspeção com responsável técnico e resultado', 'a')


def sc_nr13_proxima():
    g = art('caldeira', 246, 100, .8)
    g += calendar(40, 56, '14', 'MAR', '#16a34a')
    g += txt(66, 140, 'Inspeção feita', 11, S2, 700, 'middle')
    g += path('M110 84 h60 m-12 -12 l12 12 l-12 12', 'none', O, 5)
    g += calendar(190, 56, '14', 'PRÓX', O)
    g += txt(216, 140, 'Próxima já definida', 11, OD, 800, 'middle')
    g += chip(40, 212, 'A seguinte nasce agendada', 'org', True)[0]
    g += card(40, 246, 200, 40) + circ(60, 266, 7, '#f59e0b') + txt(74, 270, 'Aviso antes de vencer', 11, N, 700)
    return scene(g, 'Ilustração: inspeção realizada e próxima inspeção já definida', 'b')


def sc_nr13_painel():
    g = ''
    for i, (n, lab, bg, fg) in enumerate([('•', 'Em dia', OK_BG, OK_FG), ('•', 'Vencendo', WARN_BG, WARN_FG), ('•', 'Vencidas', BAD_BG, BAD_FG)]):
        x = 20 + i * 148
        g += rect(x, 24, 138, 84, bg, 14) + circ(x + 30, 54, 12, fg) + rect(x + 54, 46, 62, 8, fg, 4, op=.35) + rect(x + 54, 62, 40, 6, fg, 3, op=.25)
        g += txt(x + 20, 96, lab, 13, fg, 800)
    for i, t in enumerate(['vaso', 'caldeira', 'vaso']):
        x = 14 + i * 150
        g += art(t, x, 124, .42)
        g += circ(x + 20, 132, 8, ['#16a34a', '#f59e0b', '#ef4444'][i], W, 2.5)
    g += card(20, 232, 440, 48) + circ(44, 256, 8, '#f59e0b') + txt(62, 260, 'Aviso: uma inspeção está perto de vencer', 12, N, 700) + chip(366, 244, 'Ver', 'org')[0]
    return scene(g, 'Ilustração: painel de conformidade com equipamentos em dia, vencendo e vencidos', 'e')


NR13_SCENES = (sc_nr13_agenda, sc_nr13_laudo, sc_nr13_proxima, sc_nr13_painel)


# ---- devolução com defeito
def sc_dev_volta():
    g = art_raw_carreta()
    g += callout(176, 128, 1, 214, 92)
    g += card(300, 24, 164, 92) + photo(312, 36, 56, 44, True) + photo(374, 36, 56, 44) + txt(312, 100, 'Conferência com fotos', 10.5, N, 800)
    g += chip(24, 24, 'Volta da locação', 'navy')[0] + chip(24, 252, 'Defeito registrado na hora', 'bad', True)[0]
    return scene(g, 'Ilustração: equipamento chegando de carreta, com a avaria marcada e fotografada', 'd')


def art_raw_carreta():
    return f'<g transform="translate(0 56) scale(1.12)">{eq_carreta(eq_tesoura())}</g>'


def sc_dev_bloqueado():
    g = art('articulada', 20, 60, 1.05)
    g += padlock(330, 60, 2.6)
    g += card(300, 176, 164, 96) + txt(316, 200, 'Vitrine comercial', 11, N, 800) + rect(316, 210, 100, 5, S5, 2)
    g += line(312, 232, 452, 232, '#ef4444', 2.5, dash='6 5') + txt(316, 258, 'Equipamento fora da lista', 10.5, BAD_FG, 700)
    g += chip(24, 24, 'Bloqueado para locação', 'bad', True)[0]
    return scene(g, 'Ilustração: equipamento bloqueado, fora da vitrine comercial', 'd')


def sc_dev_os():
    g = art('plataforma', 16, 64, .95)
    g += wrench(240, 30, 2.2, O)
    g += card(290, 104, 176, 166) + txt(306, 128, 'Ordem de serviço', 12, N, 800)
    for i, lab in enumerate(['Peças', 'Mão de obra', 'Custo total']):
        y = 142 + i * 40
        last = i == 2
        g += rect(302, y, 152, 32, '#fff1e4' if last else '#f8fafc', 9, OL if last else '#e4e7ec', 1.2) + txt(314, y + 20, lab, 11, N, 800 if last else 600)
        g += rect(392, y + 12, 50, 7, OD if last else S3, 3, op=.55)
    g += chip(24, 24, 'Reparo com dono e custo', 'org', True)[0]
    return scene(g, 'Ilustração: ordem de serviço com peças, mão de obra e custo total do reparo', 'a')


def sc_dev_liberado():
    g = art('plataforma', 16, 56, 1.0)
    g += check(400, 76, 40)
    g += card(290, 150, 176, 116) + txt(306, 174, 'Vitrine comercial', 11, N, 800)
    g += rect(302, 186, 152, 32, OK_BG, 9) + circ(320, 202, 6, '#16a34a') + txt(334, 206, 'Disponível para locar', 11, OK_FG, 700)
    g += rect(302, 228, 120, 5, S5, 2)
    g += chip(24, 24, 'Liberado', 'ok', True)[0]
    return scene(g, 'Ilustração: equipamento reparado, liberado e disponível para o comercial', 'e')


DEV_SCENES = (sc_dev_volta, sc_dev_bloqueado, sc_dev_os, sc_dev_liberado)


# ------------------------------------------------------------------ hero (520x380, fundo escuro)
def hero(corpo, rotulo):
    gid, pid = uid('hg'), uid('hp')
    return (f'<svg viewBox="0 0 520 380" role="img" aria-label="{esc(rotulo)}"><title>{esc(rotulo)}</title>'
            f'<defs><radialGradient id="{gid}" cx=".5" cy=".6" r=".6"><stop offset="0" stop-color="#f97316" stop-opacity=".16"/><stop offset="1" stop-color="#f97316" stop-opacity="0"/></radialGradient>'
            f'<pattern id="{pid}" width="65" height="65" patternUnits="userSpaceOnUse">'
            '<path d="M0 0H65M0 13H65M0 26H65M0 39H65M0 52H65M0 0V65M13 0V65M26 0V65M39 0V65M52 0V65" stroke="#7fb2e8" stroke-width=".5" opacity=".18" fill="none"/>'
            '<path d="M0 0H65M0 0V65" stroke="#7fb2e8" stroke-width=".9" opacity=".3" fill="none"/></pattern></defs>'
            '<rect width="520" height="380" rx="22" fill="#0a1d38" stroke="#7fb2e8" stroke-opacity=".3"/>'
            f'<rect width="520" height="380" rx="22" fill="url(#{pid})"/><rect width="520" height="380" rx="22" fill="url(#{gid})"/>'
            f'{corners(520, 380, "#d3e8ff")}{corpo}</svg>')


def hero_chip(x, y, label, kind='ok', icon=True):
    return chip(x, y, label, kind, icon)[0]


_HERO = {
    'guindaste': lambda: hero(art('guindaste', 40, 78, 1.28) + hero_chip(310, 36, 'Revisão em dia', 'ok') + hero_chip(30, 336, 'Histórico individual', 'org'), 'Ilustração de um guindaste com carga içada'),
    'munk': lambda: hero(art('munk', 40, 76, 1.26) + hero_chip(300, 40, 'Checklist assinado', 'ok') + hero_chip(34, 336, 'Preventiva programada', 'org'), 'Ilustração de um caminhão munk com a lança articulada'),
    'plataforma': lambda: hero(art('articulada', 236, 84, .84) + art('plataforma', -6, 96, 1.0) + hero_chip(300, 336, 'Fotos e assinatura', 'org') + hero_chip(30, 34, 'Entrega comprovada', 'ok'), 'Ilustração de plataformas elevatórias tesoura e articulada'),
    'empilhadeira': lambda: hero(art('empilhadeira', 60, 82, 1.2) + hero_chip(30, 36, 'Disponível', 'ok') + hero_chip(300, 336, 'Revisão por horímetro', 'org'), 'Ilustração de uma empilhadeira com carga'),
    'gerador': lambda: hero(art('gerador', 52, 74, 1.25) + hero_chip(30, 36, 'Pronto para locar', 'ok') + hero_chip(300, 336, 'Troca de óleo em dia', 'org'), 'Ilustração de um grupo gerador sobre reboque'),
    'nr13': lambda: hero(art('vaso', 0, 112, .92) + art('caldeira', 276, 96, .86) + hero_chip(30, 36, 'Laudo anexado', 'ok') + hero_chip(300, 336, 'Inspeção em dia', 'ok') + hero_chip(30, 336, 'Aviso antes de vencer', 'warn'), 'Ilustração de um vaso de pressão e de uma caldeira'),
    'devolucao': lambda: hero(f'<g transform="translate(4 72) scale(1.34)">{eq_carreta(eq_tesoura())}</g>' + callout(176, 124, 1, 250, 96) + hero_chip(30, 36, 'Avaria registrada', 'bad') + hero_chip(300, 336, 'Bloqueado → liberado', 'org'), 'Ilustração de uma carreta devolvendo um equipamento com defeito'),
    'hub': lambda: hero(art('guindaste', 6, 52, .54) + art('munk', 168, 52, .52) + art('plataforma', 330, 40, .56)
                        + art('empilhadeira', -6, 182, .52) + art('gerador', 172, 176, .56) + art('articulada', 338, 176, .54)
                        + hero_chip(30, 336, 'Frota inteira sob controle', 'org'), 'Ilustração de vários equipamentos de locação'),
}


def _dark(fn):
    def run():
        use_theme('dark')
        try:
            return fn()
        finally:
            use_theme('paper')
    return run


HERO = {k: _dark(v) for k, v in _HERO.items()}
