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
def eq_empilhadeira():
    g = shadow(170, 150)
    g += path('M26 172 V128 Q26 108 46 108 H118 V140 H205 V172 Z', O)
    g += rect(26, 150, 14, 22, OD, 4)
    g += ''.join(path(f'M{26+i*9} 172 l6 -10 h5 l-6 10z', YL, op=.9) for i in range(1))
    g += rect(66, 70, 10, 38, S1, 4) + rect(70, 92, 38, 16, S1, 5)
    g += person(90, 94, '#1d4ed8', .9)
    g += line(150, 140, 160, 108, S1, 4) + f'<ellipse cx="160" cy="106" rx="12" ry="4" fill="none" stroke="{S1}" stroke-width="3"/>'
    g += rect(112, 46, 5, 96, N, 2) + rect(198, 46, 5, 96, N, 2)
    g += rect(106, 40, 104, 8, N, 3) + ''.join(line(120 + i * 22, 44, 120 + i * 22, 44, S4, 1) for i in range(1))
    g += ''.join(rect(122 + i * 22, 42, 3, 5, S3, 1) for i in range(4))
    g += circ(158, 34, 5, YL) + rect(154, 36, 8, 4, S2, 1)
    g += wheel(64, 160, 17) + wheel(176, 158, 24)
    g += rect(214, 24, 7, 156, S1, 2) + rect(232, 24, 7, 156, S1, 2) + rect(214, 24, 25, 5, N, 2)
    g += rect(214, 92, 25, 4, N, 1) + rect(214, 168, 25, 5, N, 1) + rect(222, 66, 9, 112, S2, 2)
    g += line(226, 30, 226, 94, S3, 1.5, dash='3 3')
    g += rect(240, 92, 8, 84, N, 2) + ''.join(line(240, 100 + i * 14, 248, 100 + i * 14, S3, 1) for i in range(5))
    g += path('M240 172 H312 L316 178 H240 Z', S1)
    g += rect(256, 126, 52, 46, '#d6a76b', 3, '#a8793e', 1.5) + line(256, 149, 308, 149, '#a8793e', 1.5) + line(282, 126, 282, 172, '#a8793e', 1.5)
    return g


def eq_gerador():
    g = shadow(150, 150)
    g += rect(28, 150, 252, 12, N, 3)
    g += path('M8 162 L32 150 V162 Z', S1) + circ(10, 160, 5, 'none', S2, 2) + rect(38, 162, 4, 16, S2, 1) + rect(32, 176, 16, 4, S1, 1)
    g += rect(36, 56, 240, 12, OD, 6) + rect(40, 62, 232, 90, O, 10)
    for i in range(7):
        g += rect(54, 76 + i * 9, 92, 4.5, '#7c2d12', 2, op=.85)
    g += rect(158, 76, 104, 68, 'none', 6, OD, 2) + rect(244, 102, 6, 18, N, 2)
    g += rect(170, 86, 52, 30, N, 4) + rect(175, 91, 42, 13, '#22c55e', 2, op=.9) + line(179, 97, 205, 97, '#064e3b', 2)
    g += circ(231, 93, 4, YL) + circ(231, 105, 4, '#ef4444')
    g += path('M200 120 l-11 17 h8 l-4 14 l15 -21 h-9 l5 -10z', W)
    g += rect(242, 24, 10, 36, S1, 2) + rect(238, 20, 18, 6, S2, 3)
    g += circ(250, 12, 6, S3, op=.45) + circ(258, 4, 4, S3, op=.3)
    g += rect(264, 112, 14, 34, N, 3) + circ(271, 122, 3, YL) + circ(271, 132, 3, YL) + circ(271, 142, 3, YL)
    g += path('M170 152 a20 20 0 0 1 40 0 Z', N) + wheel(190, 166, 17)
    return g


def eq_tesoura():
    g = shadow(170, 125)
    g += rect(70, 160, 190, 16, O, 5) + rect(70, 168, 190, 8, OD, 4)
    n, h, y = 3, 30, 160
    for i in range(n):
        y0, y1 = y - h * i, y - h * (i + 1)
        g += line(112, y0, 218, y1, S1, 8) + line(218, y0, 112, y1, S1, 8)
        g += circ(165, (y0 + y1) / 2, 5, S4, S1, 2)
    g += rect(100, 66, 14, 10, S2, 2) + rect(212, 66, 14, 10, S2, 2)
    g += wheel(98, 176, 11) + wheel(232, 176, 11)
    g += rect(78, 60, 176, 10, S1, 3) + rect(250, 62, 40, 6, S2, 2) + rect(80, 54, 172, 6, OD, 2)
    g += ''.join(rect(x, 24, 4, 36, N, 1) for x in (84, 166, 246))
    g += rect(80, 22, 172, 4, O, 2) + rect(80, 38, 172, 3, O, 2)
    g += rect(122, 44, 20, 12, N, 3) + circ(128, 50, 2.5, '#22c55e') + circ(136, 50, 2.5, '#ef4444')
    g += person(200, 56, YL, .95)
    return g


def eq_articulada():
    g = shadow(150, 125)
    g += rect(48, 134, 40, 26, OD, 7) + rect(60, 156, 132, 20, O, 6)
    g += wheel(92, 172, 13) + wheel(166, 172, 13)
    g += rect(96, 128, 64, 30, O, 7) + rect(104, 120, 20, 10, S2, 3)
    g += line(142, 136, 214, 60, OD, 17) + line(142, 136, 214, 60, O, 12)
    g += line(154, 146, 198, 92, S3, 4)
    g += circ(214, 60, 10, S1) + circ(214, 60, 4, S4)
    g += line(214, 60, 272, 42, OD, 14) + line(214, 60, 272, 42, O, 9)
    g += line(222, 74, 258, 56, S3, 3.5)
    g += rect(258, 38, 48, 30, '#e2e8f0', 4, N, 3) + line(258, 48, 306, 48, N, 2) + line(258, 58, 306, 58, N, 2)
    g += rect(278, 30, 18, 12, YL, 3) + circ(287, 24, 6, '#f1c9a5') + path('M280.5 22 A7 7 0 0 1 293.5 22 Z', W)
    return g


def eq_guindaste():
    g = shadow(150, 150)
    g += rect(24, 130, 240, 28, O, 6)
    g += path('M222 130 V102 Q222 92 232 92 H258 L278 118 V130 Z', O) + path('M232 99 H254 L267 118 H232 Z', GL)
    g += circ(274, 124, 4, YL) + rect(30, 150, 232, 6, OD, 2)
    g += rect(60, 104, 118, 28, OD, 6) + rect(66, 98, 106, 14, O, 6)
    g += rect(134, 82, 42, 28, O, 5) + rect(142, 88, 26, 16, GL, 3)
    g += rect(52, 100, 18, 30, S1, 4)
    for x in (92, 132, 214, 252):
        g += wheel(x, 162, 18)
    g += rect(56, 160, 8, 22, S1, 2) + rect(50, 180, 20, 5, N, 2) + rect(226, 160, 8, 22, S1, 2) + rect(220, 180, 20, 5, N, 2)
    px, py = 150, 96
    tx, ty = 50, 14
    g += line(112, 108, 74, 62, S2, 5)
    def at(f):
        return px + (tx - px) * f, py + (ty - py) * f
    a, b, c, d = at(0), at(.46), at(.42), at(.78)
    e, f_ = at(.74), at(1)
    g += seg(*a, *b, 24, O, OD) + seg(*c, *d, 17, OL, OD) + seg(*e, *f_, 11, S3, S1)
    g += circ(px, py, 8, S1) + circ(px, py, 3, S4)
    g += line(f_[0], f_[1], f_[0], 92, N, 2)
    g += rect(f_[0] - 6, 88, 12, 14, S1, 3) + path(f'M{f_[0]} 102 v6 a6 6 0 1 1 -6 -3', 'none', S1, 3)
    g += line(f_[0], 102, f_[0] - 22, 124, N, 1.5) + line(f_[0], 102, f_[0] + 22, 124, N, 1.5)
    g += rect(f_[0] - 30, 124, 60, 26, '#94a3b8', 3, S2, 2) + line(f_[0] - 30, 137, f_[0] + 30, 137, S2, 1.5)
    return g


def eq_munk():
    g = shadow(145, 150)
    g += rect(18, 134, 238, 14, N, 3)
    g += rect(24, 116, 150, 20, S2, 3) + rect(24, 102, 150, 6, S1, 2)
    for i in range(6):
        g += circ(40 + i * 22, 110, 9, S3, S2, 2) + circ(40 + i * 22, 110, 5, S5)
    g += path('M184 134 V92 Q184 80 196 80 H232 L260 106 V134 Z', O) + path('M194 87 H228 L246 106 H194 Z', GL)
    g += line(222, 106, 222, 134, OD, 2) + rect(252, 124, 12, 10, N, 2) + circ(254, 116, 4, YL)
    g += rect(158, 106, 28, 12, S1, 3) + rect(164, 60, 16, 48, O, 3) + circ(172, 62, 7, S1)
    g += seg(172, 66, 114, 16, 14, O, OD) + circ(114, 16, 7, S1) + circ(114, 16, 3, S4)
    g += seg(114, 16, 54, 56, 11, O, OD) + seg(54, 56, 34, 70, 7, S3, S1)
    g += line(166, 90, 124, 38, S2, 4) + line(120, 30, 70, 50, S2, 3.5)
    g += line(34, 70, 34, 92, N, 2) + rect(28, 90, 12, 8, S1, 2)
    g += line(34, 98, 20, 106, N, 1.5) + line(34, 98, 48, 106, N, 1.5)
    g += rect(12, 106, 44, 9, S3, 3, S2, 1.5) + circ(18, 110, 3, S5) + circ(50, 110, 3, S5)
    g += rect(112, 146, 40, 6, S1, 2) + rect(144, 150, 6, 32, S2, 2) + rect(138, 180, 18, 5, N, 2)
    g += wheel(58, 162, 17) + wheel(90, 162, 17) + wheel(224, 162, 17)
    return g


def eq_vaso():
    g = shadow(160, 135)
    g += path('M86 140 L74 182 H118 L112 140 Z', S1) + path('M208 140 L204 182 H248 L236 140 Z', S1)
    g += rect(44, 64, 232, 82, S4, 41)
    g += rect(62, 72, 196, 14, W, 7, op=.55) + rect(58, 122, 204, 14, S3, 7, op=.6)
    g += line(94, 66, 94, 144, S2, 2) + line(226, 66, 226, 144, S2, 2)
    g += rect(156, 38, 8, 28, S1, 2) + rect(146, 36, 28, 6, S2, 2)
    g += circ(160, 22, 17, W, S1, 4)
    g += path('M149 31 A14 14 0 0 1 171 31', 'none', '#22c55e', 3)
    g += path('M168 14 A14 14 0 0 1 174 24', 'none', '#ef4444', 3)
    g += line(160, 22, 168, 13, '#ef4444', 2) + circ(160, 22, 2.5, N)
    g += rect(222, 42, 8, 24, S1, 2) + rect(214, 32, 24, 12, '#ef4444', 3) + rect(222, 26, 8, 6, S2, 2)
    g += rect(150, 146, 10, 14, S1, 2) + circ(155, 163, 6, S2, S1, 2)
    g += rect(112, 94, 44, 28, S5, 2, S2, 1.5) + line(118, 102, 148, 102, S3, 2) + line(118, 109, 140, 109, S3, 2) + line(118, 116, 144, 116, S3, 2)
    g += path('M252 70 l14 0 l-4 16 l-10 -4z', O)
    return g


def eq_caldeira():
    g = shadow(160, 120)
    g += rect(96, 170, 128, 12, S1, 3)
    g += rect(104, 56, 112, 116, S4, 16) + path('M104 70 Q160 30 216 70', S4)
    g += rect(118, 64, 14, 100, W, 7, op=.5) + rect(190, 70, 18, 96, S3, 8, op=.55)
    g += rect(150, 6, 20, 50, S1, 2) + rect(144, 2, 32, 8, N, 3)
    g += circ(160, 126, 24, S2, S1, 4) + circ(160, 126, 14, S1) + circ(160, 126, 5, S4)
    g += rect(60, 124, 40, 32, O, 5) + path('M52 140 q-6 -10 2 -18 q2 10 10 8 z', '#fbbf24') + rect(98, 136, 8, 8, S1, 2)
    g += circ(228, 78, 14, W, S1, 3) + line(228, 78, 234, 70, '#ef4444', 2) + path('M219 84 A11 11 0 0 1 226 66', 'none', '#22c55e', 2.5)
    g += rect(228, 104, 7, 44, GL, 2, S1, 1.5) + rect(229, 124, 5, 24, '#38bdf8', 1)
    g += line(216, 100, 228, 100, S2, 3) + line(216, 150, 228, 150, S2, 3)
    return g


def eq_carreta(carga=None):
    g = shadow(160, 160)
    g += path('M16 158 H236 V148 H16 Z', N) + rect(120, 150, 116, 6, S1, 2)
    g += path('M236 158 V112 Q236 98 250 98 H292 L316 130 V158 Z', O) + path('M246 106 H288 L304 130 H246 Z', GL)
    g += line(262, 130, 262, 158, OD, 2) + rect(306, 148, 12, 10, N, 2) + circ(312, 138, 4, YL)
    g += rect(228, 148, 12, 8, S1, 2)
    g += wheel(264, 164, 18) + wheel(60, 164, 16) + wheel(96, 164, 16) + wheel(132, 164, 16)
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
    'a': ('#fff7ed', '#ffedd5'), 'b': ('#eff6ff', '#dbeafe'), 'c': ('#f1f5f9', '#e2e8f0'),
    'd': ('#fef2f2', '#fee2e2'), 'e': ('#f0fdf4', '#dcfce7'),
}


def scene(corpo, legenda, bg='a', w=480, h=300):
    gid, pid = uid('bg'), uid('dt')
    c0, c1 = BGS[bg]
    return (f'<figure class="mock"><svg viewBox="0 0 {w} {h}" role="img" aria-label="{esc(legenda)}"><title>{esc(legenda)}</title>'
            f'<defs><linearGradient id="{gid}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{c0}"/><stop offset="1" stop-color="{c1}"/></linearGradient>'
            f'<pattern id="{pid}" width="22" height="22" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.2" fill="#0f172a" opacity=".07"/></pattern></defs>'
            f'<rect width="{w}" height="{h}" rx="18" fill="url(#{gid})"/><rect width="{w}" height="{h}" rx="18" fill="url(#{pid})"/>'
            f'{corpo}</svg><figcaption>{esc(legenda)}</figcaption></figure>')


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
    g += rect(284, 148, 160, 6, S5, 3) + rect(284, 148, 160 * c['horas'][2], 6, O, 3)
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
        g += chip(166, y + 5, ['Em curso', 'Feita', 'Aberta'][i], st)[0]
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
        g += pin(x, y, str(i + 1), '#ef4444' if i == 0 else '#f59e0b')
    g += card(300, 24, 164, 98) + photo(312, 36, 56, 44, True) + photo(374, 36, 56, 44, False)
    g += txt(312, 98, 'Avaria registrada', 11, N, 800) + rect(312, 106, 100, 4, S5, 2)
    g += chip(26, 24, 'Com fotos e data', 'bad', True)[0]
    g += card(26, 236, 266, 46)
    g += circ(46, 259, 7, '#ef4444') + txt(60, 263, c['pecas'][0][0], 11, N, 700) + circ(190, 259, 7, '#f59e0b') + txt(204, 263, c['pecas'][1][0], 11, N, 700)
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
    g += pin(164, 104, '!', '#ef4444')
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
            f'<defs><radialGradient id="{gid}" cx=".5" cy=".55" r=".6"><stop offset="0" stop-color="#f97316" stop-opacity=".35"/><stop offset="1" stop-color="#f97316" stop-opacity="0"/></radialGradient>'
            f'<pattern id="{pid}" width="26" height="26" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1.3" fill="#fff" opacity=".12"/></pattern></defs>'
            f'<rect width="520" height="380" rx="26" fill="#0b1630" stroke="#ffffff" stroke-opacity=".12"/><rect width="520" height="380" rx="26" fill="url(#{pid})"/>'
            f'<rect width="520" height="380" rx="26" fill="url(#{gid})"/>'
            f'<rect x="20" y="318" width="480" height="3" rx="1.5" fill="#fff" opacity=".12"/>{corpo}</svg>')


def hero_chip(x, y, label, kind='ok', icon=True):
    return chip(x, y, label, kind, icon)[0]


HERO = {
    'guindaste': lambda: hero(art('guindaste', 40, 78, 1.28) + hero_chip(310, 36, 'Revisão em dia', 'ok') + hero_chip(30, 336, 'Histórico individual', 'org'), 'Ilustração de um guindaste com carga içada'),
    'munk': lambda: hero(art('munk', 40, 76, 1.26) + hero_chip(300, 40, 'Checklist assinado', 'ok') + hero_chip(34, 336, 'Preventiva programada', 'org'), 'Ilustração de um caminhão munk com a lança articulada'),
    'plataforma': lambda: hero(art('articulada', 236, 84, .84) + art('plataforma', -6, 96, 1.0) + hero_chip(300, 336, 'Fotos e assinatura', 'org') + hero_chip(30, 34, 'Entrega comprovada', 'ok'), 'Ilustração de plataformas elevatórias tesoura e articulada'),
    'empilhadeira': lambda: hero(art('empilhadeira', 60, 82, 1.2) + hero_chip(30, 36, 'Disponível', 'ok') + hero_chip(300, 336, 'Revisão por horímetro', 'org'), 'Ilustração de uma empilhadeira com carga'),
    'gerador': lambda: hero(art('gerador', 52, 74, 1.25) + hero_chip(30, 36, 'Pronto para locar', 'ok') + hero_chip(300, 336, 'Troca de óleo em dia', 'org'), 'Ilustração de um grupo gerador sobre reboque'),
    'nr13': lambda: hero(art('vaso', 0, 112, .92) + art('caldeira', 276, 96, .86) + hero_chip(30, 36, 'Laudo anexado', 'ok') + hero_chip(300, 336, 'Inspeção em dia', 'ok') + hero_chip(30, 336, 'Aviso antes de vencer', 'warn'), 'Ilustração de um vaso de pressão e de uma caldeira'),
    'devolucao': lambda: hero(f'<g transform="translate(4 72) scale(1.34)">{eq_carreta(eq_tesoura())}</g>' + pin(176, 124, '!', '#ef4444') + hero_chip(30, 36, 'Avaria registrada', 'bad') + hero_chip(300, 336, 'Bloqueado → liberado', 'org'), 'Ilustração de uma carreta devolvendo um equipamento com defeito'),
    'hub': lambda: hero(art('guindaste', 6, 52, .54) + art('munk', 168, 52, .52) + art('plataforma', 330, 40, .56)
                        + art('empilhadeira', -6, 182, .52) + art('gerador', 172, 176, .56) + art('articulada', 338, 176, .54)
                        + hero_chip(30, 336, 'Frota inteira sob controle', 'org'), 'Ilustração de vários equipamentos de locação'),
}
