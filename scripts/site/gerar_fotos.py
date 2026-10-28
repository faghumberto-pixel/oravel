#!/usr/bin/env python3
"""
Recorta e otimiza as fotos de /home/oravel/fotos-site para as páginas de equipamentos.
Saída: public_html/img/equip/<nome>-<k>.jpg (cenas 960x600) e <nome>-capa.jpg (topo).
Coordenadas dos recortes: caixas (x0,y0,x1,y1) na imagem de referência `ref` (largura exibida).
"""
import sys
from pathlib import Path
from PIL import Image

SRC = Path('/home/oravel/fotos-site')
OUT = Path(sys.argv[1])

# nome: (arquivo, largura_de_referencia, capa, [cenas])
FOTOS = {
    'guindaste': ('foto1.jpg', 2000, (160, 20, 1760, 1060), [(780, 540, 1480, 980), (800, 616, 1600, 1116), (200, 250, 800, 625), (780, 780, 1180, 1030), (0, 640, 500, 952)]),
    'munk': ('foto2.jpg', 2000, (150, 20, 1750, 1060), [(250, 300, 1050, 800), (1000, 560, 1800, 1060), (1150, 150, 1800, 555), (600, 60, 1200, 435), (1500, 560, 2000, 872)]),
    'empilhadeira': ('foto3.jpg', 2000, (400, 20, 1900, 1010), [(1000, 20, 1700, 460), (600, 330, 1300, 770), (560, 700, 1260, 1116), (1400, 300, 2000, 675), (1300, 560, 1800, 872)]),
    'gerador': ('foto4.jpg', 2000, (300, 20, 1900, 1020), [(800, 230, 1200, 480), (400, 570, 1000, 945), (1000, 20, 1600, 395), (1000, 190, 1500, 503), (1500, 300, 2000, 612)]),
    'devolucao': ('foto5.jpg', 2000, (150, 20, 1850, 1100), [(600, 520, 1300, 960), (200, 280, 1100, 842), (1200, 600, 1700, 912), (1100, 150, 1850, 619), (800, 760, 1500, 1116)]),
    'nr13': ('foto6.jpg', 1932, (180, 300, 1900, 1480), [(1300, 700, 1900, 1075), (500, 500, 1300, 1000), (0, 100, 800, 600), (1450, 1050, 1932, 1350), (0, 1100, 600, 1475)]),
}


def salvar(im, box, ref, destino, tam):
    f = im.width / ref
    x0, y0, x1, y1 = (int(v * f) for v in box)
    c = im.crop((x0, y0, min(x1, im.width), min(y1, im.height)))
    c = c.resize(tam, Image.LANCZOS) if c.width >= tam[0] else c.resize(tam, Image.BICUBIC)
    c.save(destino, 'JPEG', quality=80, optimize=True, progressive=True)


for nome, (arq, ref, capa, cenas) in FOTOS.items():
    im = Image.open(SRC / arq).convert('RGB')
    salvar(im, capa, ref, OUT / f'{nome}-capa.jpg', (1040, 760))
    for k, box in enumerate(cenas, 1):
        salvar(im, box, ref, OUT / f'{nome}-{k}.jpg', (960, 600))

# recortes das fotos dentro da tela do sistema (foto7), sem nomes de cliente nem marcas
t = Image.open(SRC / 'foto7.png').convert('RGB')
f = t.width / 1400
for nome, box in {'painel': (221, 302, 587, 432), 'esteira': (611, 302, 977, 432)}.items():
    c = t.crop(tuple(int(v * f) for v in box))
    c.save(OUT / f'{nome}.jpg', 'JPEG', quality=82, optimize=True)
print(sorted(p.name for p in OUT.iterdir()))

# plataforma: sem foto do equipamento; usa quadros do vídeo institucional (equipe.jpg e frota-poster.jpg)
# (recortes plataforma-1..5 gerados a partir de public_html/img/equip/equipe.jpg e frota-poster.jpg)

# ---- páginas de processos: recortes das fotos já existentes (capa 1040x760, cenas 960x600)
PROC = {
    'equipes': [('equipe.jpg', 900, (0, 60, 700, 571), [(240, 100, 700, 388), (560, 110, 900, 322), (300, 230, 640, 442), (0, 0, 420, 262)])],
    'cobranca': [('frota-poster.jpg', 900, (0, 0, 900, 657), [(100, 180, 500, 430), (480, 80, 900, 342), (0, 100, 440, 375), (300, 120, 780, 420)])],
    'logistica': [('foto5.jpg', 2000, (1000, 0, 2000, 730), [(1000, 100, 1900, 662), (1400, 300, 2000, 675), (0, 0, 800, 500), (1150, 250, 1700, 594)])],
    'estoque': [('foto3.jpg', 2000, (0, 0, 1100, 803), [(1400, 0, 2000, 375), (1300, 560, 1800, 872), (0, 500, 600, 875), (150, 500, 700, 844)])],
    'manutencao': [('foto5.jpg', 2000, (600, 150, 1600, 880), [(700, 400, 1400, 837), (200, 280, 1100, 842), (1200, 600, 1700, 912), (800, 0, 1500, 437)])],
}
for nome, ((arq, ref, capa, cenas),) in PROC.items():
    base = SRC / arq if (SRC / arq).exists() else OUT / arq
    im = Image.open(base).convert('RGB')
    salvar(im, capa, ref, OUT / f'proc-{nome}-capa.jpg', (1040, 760))
    for k, box in enumerate(cenas, 1):
        salvar(im, box, ref, OUT / f'proc-{nome}-{k}.jpg', (960, 600))
