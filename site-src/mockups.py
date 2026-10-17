"""Gerador de mockups SVG (telas do sistema com dados fictícios) no estilo das figuras `.mock` do site."""
from xml.sax.saxutils import escape as esc

NAVY, ACCENT, INK, SOFT, LINE, SURF = '#0f172a', '#f97316', '#0f172a', '#5b6472', '#e4e7ec', '#f6f7f9'
TONS = {'ok': '#22c55e', 'warn': '#f59e0b', 'bad': '#ef4444', 'info': '#3b82f6', 'mudo': '#94a3b8'}


def _chrome(url, titulo, corpo, aria):
    return (
        f'<svg viewBox="0 0 480 300" role="img" aria-label="{esc(aria)}" xmlns="http://www.w3.org/2000/svg">'
        f'<rect width="480" height="300" rx="10" fill="#fff" stroke="{LINE}"/>'
        f'<rect width="480" height="30" rx="10" fill="{NAVY}"/><rect y="15" width="480" height="15" fill="{NAVY}"/>'
        '<circle cx="16" cy="15" r="4" fill="#ef4444"/><circle cx="30" cy="15" r="4" fill="#f59e0b"/><circle cx="44" cy="15" r="4" fill="#22c55e"/>'
        f'<text x="240" y="19" font-family="Inter" font-size="9" fill="#8a94a8" text-anchor="middle">app.oravel.com.br/{esc(url)}</text>'
        f'<text x="20" y="52" font-family="Manrope" font-size="12" font-weight="800" fill="{INK}">{esc(titulo)}</text>'
        f'{corpo}</svg>'
    )


def _pill(x, y, texto, tom='info', w=None):
    w = w or (len(texto) * 5 + 12)
    return (f'<rect x="{x}" y="{y}" width="{w}" height="14" rx="7" fill="{TONS[tom]}" opacity=".16"/>'
            f'<text x="{x + w / 2}" y="{y + 10}" font-family="Inter" font-size="8" font-weight="700" fill="{TONS[tom]}" text-anchor="middle">{esc(texto)}</text>')


def kanban(url, titulo, colunas):
    n = len(colunas)
    larg = (440 - (n - 1) * 8) / n
    out = []
    for i, (nome, cartoes) in enumerate(colunas):
        x = 20 + i * (larg + 8)
        out.append(f'<rect x="{x:.0f}" y="64" width="{larg:.0f}" height="224" rx="6" fill="{SURF}"/>')
        out.append(f'<text x="{x + 8:.0f}" y="80" font-family="Inter" font-size="8.5" font-weight="700" fill="{SOFT}">{esc(nome.upper())}</text>')
        for j, (t, sub, tom) in enumerate(cartoes[:4]):
            y = 88 + j * 48
            out.append(f'<rect x="{x + 6:.0f}" y="{y}" width="{larg - 12:.0f}" height="42" rx="5" fill="#fff" stroke="{LINE}"/>')
            out.append(f'<rect x="{x + 6:.0f}" y="{y}" width="3" height="42" rx="1.5" fill="{TONS[tom]}"/>')
            out.append(f'<text x="{x + 14:.0f}" y="{y + 16}" font-family="Inter" font-size="8.5" font-weight="700" fill="{INK}">{esc(t)}</text>')
            out.append(f'<text x="{x + 14:.0f}" y="{y + 30}" font-family="Inter" font-size="7.5" fill="{SOFT}">{esc(sub)}</text>')
    return _chrome(url, titulo, ''.join(out), titulo)


def tabela(url, titulo, cabecalho, linhas, kpis=None):
    out = []
    y0 = 64
    if kpis:
        for i, (rot, val) in enumerate(kpis):
            x = 20 + i * 112
            out.append(f'<rect x="{x}" y="{y0}" width="104" height="40" rx="6" fill="{SURF}"/>')
            out.append(f'<text x="{x + 8}" y="{y0 + 15}" font-family="Inter" font-size="7.5" fill="{SOFT}">{esc(rot)}</text>')
            out.append(f'<text x="{x + 8}" y="{y0 + 32}" font-family="Manrope" font-size="14" font-weight="800" fill="{INK}">{esc(val)}</text>')
        y0 += 50
    cols = len(cabecalho)
    larg = 440 / cols
    out.append(f'<rect x="20" y="{y0}" width="440" height="20" rx="4" fill="{NAVY}"/>')
    for i, c in enumerate(cabecalho):
        out.append(f'<text x="{28 + i * larg:.0f}" y="{y0 + 13}" font-family="Inter" font-size="8" font-weight="700" fill="#fff">{esc(c)}</text>')
    for r, linha in enumerate(linhas[:7]):
        y = y0 + 22 + r * 24
        if r % 2 == 0:
            out.append(f'<rect x="20" y="{y}" width="440" height="24" fill="{SURF}"/>')
        for i, cel in enumerate(linha):
            x = 28 + i * larg
            if isinstance(cel, tuple):
                out.append(_pill(x, y + 5, cel[0], cel[1]))
            else:
                out.append(f'<text x="{x:.0f}" y="{y + 15}" font-family="Inter" font-size="8.5" fill="{INK}">{esc(str(cel))}</text>')
    return _chrome(url, titulo, ''.join(out), titulo)


def painel(url, titulo, kpis, barras, legenda):
    out = []
    for i, (rot, val, tom) in enumerate(kpis):
        x = 20 + i * 112
        out.append(f'<rect x="{x}" y="64" width="104" height="48" rx="6" fill="{SURF}"/><rect x="{x}" y="64" width="104" height="3" rx="1.5" fill="{TONS[tom]}"/>')
        out.append(f'<text x="{x + 8}" y="84" font-family="Inter" font-size="7.5" fill="{SOFT}">{esc(rot)}</text>')
        out.append(f'<text x="{x + 8}" y="103" font-family="Manrope" font-size="16" font-weight="800" fill="{INK}">{esc(val)}</text>')
    out.append(f'<rect x="20" y="124" width="440" height="164" rx="6" fill="#fff" stroke="{LINE}"/>')
    out.append(f'<text x="30" y="140" font-family="Inter" font-size="8.5" font-weight="700" fill="{SOFT}">{esc(legenda)}</text>')
    mx = max(v for _, v in barras) or 1
    bw = 400 / len(barras)
    for i, (rot, v) in enumerate(barras):
        h = 110 * v / mx
        x = 36 + i * bw
        out.append(f'<rect x="{x:.0f}" y="{268 - h:.0f}" width="{bw - 14:.0f}" height="{h:.0f}" rx="3" fill="{ACCENT if i == len(barras) - 1 else NAVY}" opacity="{1 if i == len(barras) - 1 else .85}"/>')
        out.append(f'<text x="{x + (bw - 14) / 2:.0f}" y="282" font-family="Inter" font-size="7.5" fill="{SOFT}" text-anchor="middle">{esc(rot)}</text>')
    return _chrome(url, titulo, ''.join(out), titulo)


def ficha(url, titulo, campos, linha_tempo, etiqueta=None):
    out = [f'<rect x="20" y="64" width="210" height="224" rx="6" fill="{SURF}"/>']
    if etiqueta:
        out.append(_pill(30, 72, etiqueta[0], etiqueta[1]))
    for i, (rot, val) in enumerate(campos[:7]):
        y = 104 + i * 26
        out.append(f'<text x="30" y="{y}" font-family="Inter" font-size="7.5" fill="{SOFT}">{esc(rot)}</text>')
        out.append(f'<text x="30" y="{y + 12}" font-family="Inter" font-size="9" font-weight="700" fill="{INK}">{esc(val)}</text>')
    out.append(f'<text x="246" y="76" font-family="Inter" font-size="8.5" font-weight="700" fill="{SOFT}">HISTÓRICO</text>')
    out.append(f'<line x1="256" y1="90" x2="256" y2="{90 + 50 * (len(linha_tempo) - 1)}" stroke="{LINE}" stroke-width="2"/>')
    for i, (quando, texto) in enumerate(linha_tempo[:5]):
        y = 90 + i * 50
        out.append(f'<circle cx="256" cy="{y}" r="5" fill="{ACCENT if i == 0 else NAVY}"/>')
        out.append(f'<text x="270" y="{y - 1}" font-family="Inter" font-size="8" font-weight="700" fill="{INK}">{esc(quando)}</text>')
        out.append(f'<text x="270" y="{y + 12}" font-family="Inter" font-size="8" fill="{SOFT}">{esc(texto)}</text>')
    return _chrome(url, titulo, ''.join(out), titulo)


def documento(url, titulo, linhas, total, selo):
    out = [f'<rect x="90" y="62" width="300" height="228" rx="4" fill="#fff" stroke="{LINE}"/>',
           f'<rect x="90" y="62" width="300" height="26" fill="{SURF}"/>',
           f'<text x="104" y="79" font-family="Manrope" font-size="10" font-weight="800" fill="{INK}">{esc(titulo)}</text>',
           _pill(330, 69, selo[0], selo[1], 52)]
    for i, (desc, valor) in enumerate(linhas[:5]):
        y = 108 + i * 22
        out.append(f'<text x="104" y="{y}" font-family="Inter" font-size="8.5" fill="{INK}">{esc(desc)}</text>')
        out.append(f'<text x="376" y="{y}" font-family="Inter" font-size="8.5" font-weight="700" fill="{INK}" text-anchor="end">{esc(valor)}</text>')
        out.append(f'<line x1="104" y1="{y + 7}" x2="376" y2="{y + 7}" stroke="{LINE}"/>')
    out.append(f'<text x="376" y="228" font-family="Manrope" font-size="12" font-weight="800" fill="{INK}" text-anchor="end">{esc(total)}</text>')
    out.append(f'<rect x="104" y="244" width="120" height="34" rx="4" fill="none" stroke="{LINE}" stroke-dasharray="3 2"/>')
    out.append(f'<path d="M114 266 q8 -18 16 -4 t16 -2 t16 -2" stroke="{NAVY}" stroke-width="1.6" fill="none"/>')
    out.append(f'<text x="164" y="256" font-family="Inter" font-size="7" fill="{SOFT}" text-anchor="middle">Assinatura</text>')
    out.append(f'<rect x="300" y="248" width="76" height="26" rx="5" fill="{ACCENT}"/><text x="338" y="265" font-family="Inter" font-size="9" font-weight="700" fill="#fff" text-anchor="middle">Aceitar</text>')
    return _chrome(url, '', ''.join(out), titulo)


def celular(url, titulo, itens, rodape):
    out = [f'<rect x="150" y="40" width="180" height="252" rx="22" fill="{NAVY}"/>',
           '<rect x="160" y="58" width="160" height="216" rx="8" fill="#fff"/>',
           f'<rect x="160" y="58" width="160" height="26" rx="8" fill="{NAVY}"/><rect x="160" y="70" width="160" height="14" fill="{NAVY}"/>',
           f'<text x="240" y="75" font-family="Inter" font-size="9" font-weight="700" fill="#fff" text-anchor="middle">{esc(titulo)}</text>']
    for i, (txt, estado) in enumerate(itens[:6]):
        y = 96 + i * 28
        cor = TONS.get(estado, TONS['mudo'])
        out.append(f'<rect x="168" y="{y}" width="144" height="22" rx="5" fill="{SURF}"/>')
        out.append(f'<circle cx="180" cy="{y + 11}" r="6" fill="{cor}" opacity=".9"/>')
        if estado == 'ok':
            out.append(f'<path d="M177 {y + 11} l2.5 2.5 l4.5 -5" stroke="#fff" stroke-width="1.6" fill="none"/>')
        out.append(f'<text x="192" y="{y + 14}" font-family="Inter" font-size="8" fill="{INK}">{esc(txt)}</text>')
    out.append(f'<rect x="170" y="252" width="140" height="18" rx="9" fill="{ACCENT}"/><text x="240" y="264" font-family="Inter" font-size="8.5" font-weight="700" fill="#fff" text-anchor="middle">{esc(rodape)}</text>')
    return _chrome(url, '', ''.join(out), titulo)


def mapa(url, titulo, pontos, lista):
    out = [f'<rect x="20" y="62" width="270" height="226" rx="6" fill="#eaf0f6"/>',
           '<path d="M20 200 C90 160 130 230 200 180 S270 120 290 130" stroke="#fff" stroke-width="6" fill="none"/>',
           '<path d="M60 62 C80 120 70 180 110 288" stroke="#fff" stroke-width="5" fill="none"/>']
    for x, y, tom in pontos:
        out.append(f'<circle cx="{x}" cy="{y}" r="8" fill="{TONS[tom]}" opacity=".25"/><circle cx="{x}" cy="{y}" r="4.5" fill="{TONS[tom]}" stroke="#fff" stroke-width="1.5"/>')
    for i, (nome, st, tom) in enumerate(lista[:5]):
        y = 66 + i * 44
        out.append(f'<rect x="300" y="{y}" width="160" height="38" rx="6" fill="{SURF}"/>')
        out.append(f'<text x="310" y="{y + 15}" font-family="Inter" font-size="8.5" font-weight="700" fill="{INK}">{esc(nome)}</text>')
        out.append(_pill(310, y + 20, st, tom))
    return _chrome(url, titulo, ''.join(out), titulo)


def gerar(spec):
    tipo = spec['tipo']
    args = {k: v for k, v in spec.items() if k not in ('tipo', 'legenda', 'legenda_titulo')}
    if tipo == 'painel':
        args['legenda'] = spec['legenda']
    fn = {'kanban': kanban, 'tabela': tabela, 'painel': painel, 'ficha': ficha, 'documento': documento, 'celular': celular, 'mapa': mapa}[tipo]
    def tup(x):
        return tuple(tup(i) for i in x) if isinstance(x, list) else x
    return fn(**{k: tup(v) for k, v in args.items()})


def app_celular(nome, cor, tela, itens):
    """Celular com a tela inicial do app da solução (ícone, nome e a primeira tela), em SVG."""
    out = [f'<svg viewBox="0 0 220 380" role="img" aria-label="App {esc(nome)} no celular" xmlns="http://www.w3.org/2000/svg">',
           f'<rect x="10" y="6" width="200" height="368" rx="28" fill="{NAVY}"/>',
           '<rect x="20" y="22" width="180" height="336" rx="18" fill="#fff"/>',
           f'<rect x="20" y="22" width="180" height="58" rx="18" fill="{cor}"/><rect x="20" y="60" width="180" height="20" fill="{cor}"/>',
           f'<rect x="32" y="36" width="26" height="26" rx="7" fill="#fff" opacity=".22"/><text x="45" y="55" font-family="Manrope" font-size="15" font-weight="800" fill="#fff" text-anchor="middle">O</text>',
           f'<text x="66" y="47" font-family="Manrope" font-size="11" font-weight="800" fill="#fff">{esc(nome)}</text>',
           f'<text x="66" y="61" font-family="Inter" font-size="8.5" fill="#fff" opacity=".85">{esc(tela)}</text>']
    for i, (txt, tom) in enumerate(itens[:4]):
        y = 94 + i * 46
        c = TONS.get(tom, TONS['mudo'])
        out.append(f'<rect x="30" y="{y}" width="160" height="38" rx="8" fill="{SURF}"/><rect x="30" y="{y}" width="4" height="38" rx="2" fill="{c}"/>')
        out.append(f'<text x="44" y="{y + 17}" font-family="Inter" font-size="9" font-weight="700" fill="{INK}">{esc(txt)}</text>')
        out.append(f'<text x="44" y="{y + 30}" font-family="Inter" font-size="7.5" fill="{SOFT}">Toque para abrir</text>')
    out.append(f'<rect x="40" y="304" width="140" height="30" rx="15" fill="{cor}"/><text x="110" y="323" font-family="Inter" font-size="10" font-weight="700" fill="#fff" text-anchor="middle">Adicionar à tela inicial</text>')
    out.append('</svg>')
    return ''.join(out)


# ---------------------------------------------------------------- Academia Oravel (telas de exemplo, dados fictícios)

def _barra(x, y, w, pct, cor=ACCENT):
    return (f'<rect x="{x}" y="{y}" width="{w}" height="5" rx="2.5" fill="{LINE}"/>'
            f'<rect x="{x}" y="{y}" width="{w * pct / 100:.0f}" height="5" rx="2.5" fill="{cor}"/>')


def academia_catalogo():
    cursos = [('Manutenção', '7 aulas', 86, '#ea580c'), ('Comercial', '9 aulas', 55, '#db2777'), ('Suprimentos', '4 aulas', 100, '#7c3aed'),
              ('Logística', '3 aulas', 34, '#0d9488'), ('Ativos e Frota', '4 aulas', 70, '#2563eb'), ('Configurações', '5 aulas', 20, '#0f172a')]
    out = [f'<text x="20" y="72" font-family="Inter" font-size="9" fill="{SOFT}">Cursos do seu plano · 1 certificado disponível</text>']
    for i, (nome, aulas, pct, cor) in enumerate(cursos):
        x, y = 20 + (i % 3) * 148, 84 + (i // 3) * 98
        out.append(f'<rect x="{x}" y="{y}" width="140" height="90" rx="8" fill="#fff" stroke="{LINE}"/><rect x="{x}" y="{y}" width="140" height="30" rx="8" fill="{cor}"/><rect x="{x}" y="{y + 20}" width="140" height="10" fill="{cor}"/>')
        out.append(f'<text x="{x + 10}" y="{y + 20}" font-family="Manrope" font-size="10" font-weight="800" fill="#fff">{esc(nome)}</text>')
        out.append(f'<text x="{x + 10}" y="{y + 50}" font-family="Inter" font-size="8" fill="{SOFT}">{aulas} · {pct}% concluído</text>')
        out.append(_barra(x + 10, y + 58, 120, pct, cor))
        out.append(_pill(x + 10, y + 70, 'Certificado' if pct == 100 else 'Continuar', 'ok' if pct == 100 else 'info', 62))
    return _chrome('academia', 'Academia Oravel', ''.join(out), 'Catálogo de cursos da Academia Oravel')


def academia_aula():
    passos = ['Abra Ativos → Gestão de Ativos', 'Clique em Criar e preencha o patrimônio', 'Anexe as 3 fotos do equipamento', 'Salve e gere o QR Code']
    out = [f'<rect x="20" y="62" width="150" height="226" rx="6" fill="{SURF}"/><text x="30" y="80" font-family="Inter" font-size="8.5" font-weight="700" fill="{SOFT}">AULAS DO CURSO</text>']
    for i, (t, ok) in enumerate([('Cadastrar um ativo', True), ('Grupos de checklist', True), ('QR Code de consulta', False), ('Gestão de frota', False)]):
        y = 90 + i * 30
        out.append(f'<rect x="28" y="{y}" width="134" height="24" rx="5" fill="{"#fff" if ok else SURF}" stroke="{LINE}"/><circle cx="40" cy="{y + 12}" r="5" fill="{TONS["ok"] if ok else TONS["mudo"]}"/>')
        out.append(f'<text x="52" y="{y + 15}" font-family="Inter" font-size="8" fill="{INK}">{esc(t)}</text>')
    out.append(f'<text x="186" y="80" font-family="Manrope" font-size="13" font-weight="800" fill="{INK}">Cadastrar um ativo</text>')
    for i, t in enumerate(passos):
        y = 96 + i * 34
        out.append(f'<circle cx="198" cy="{y + 8}" r="8" fill="{ACCENT}"/><text x="198" y="{y + 11}" font-family="Inter" font-size="9" font-weight="700" fill="#fff" text-anchor="middle">{i + 1}</text>')
        out.append(f'<text x="214" y="{y + 12}" font-family="Inter" font-size="9" fill="{INK}">{esc(t)}</text>')
    out.append(f'<rect x="186" y="236" width="270" height="34" rx="6" fill="{SURF}" stroke="{LINE}"/><text x="198" y="257" font-family="Inter" font-size="8.5" fill="{SOFT}">Dica: o Nº do patrimônio é único dentro da sua empresa.</text>')
    out.append(f'<rect x="346" y="274" width="110" height="18" rx="9" fill="{ACCENT}"/><text x="401" y="286" font-family="Inter" font-size="8.5" font-weight="700" fill="#fff" text-anchor="middle">Marcar como concluída</text>')
    return _chrome('academia/aula', 'Aula · passo a passo', ''.join(out), 'Aula da Academia com passo a passo')


def academia_prova():
    opcoes = ['O estoque cai sozinho e nasce uma solicitação de peças', 'O estoque só muda depois da conferência', 'O sistema bloqueia o lançamento', 'Nada acontece até o fim do mês']
    out = [f'<text x="20" y="72" font-family="Inter" font-size="8.5" font-weight="700" fill="{SOFT}">PERGUNTA 4 DE 12</text>', _barra(20, 80, 440, 33),
           f'<text x="20" y="108" font-family="Manrope" font-size="11" font-weight="800" fill="{INK}">Uma peça fica abaixo do estoque mínimo após o uso na OS.</text>',
           f'<text x="20" y="123" font-family="Manrope" font-size="11" font-weight="800" fill="{INK}">O que acontece?</text>']
    for i, t in enumerate(opcoes):
        y = 136 + i * 34
        sel = i == 0
        out.append(f'<rect x="20" y="{y}" width="440" height="28" rx="6" fill="{"#fff7ed" if sel else "#fff"}" stroke="{ACCENT if sel else LINE}" stroke-width="{1.5 if sel else 1}"/>')
        out.append(f'<circle cx="36" cy="{y + 14}" r="6" fill="none" stroke="{ACCENT if sel else "#cbd5e1"}" stroke-width="1.5"/>')
        if sel:
            out.append(f'<circle cx="36" cy="{y + 14}" r="3" fill="{ACCENT}"/>')
        out.append(f'<text x="52" y="{y + 17}" font-family="Inter" font-size="9" fill="{INK}">{esc(t)}</text>')
    out.append(f'<rect x="370" y="274" width="90" height="18" rx="9" fill="{NAVY}"/><text x="415" y="286" font-family="Inter" font-size="8.5" font-weight="700" fill="#fff" text-anchor="middle">Próxima</text>')
    return _chrome('academia/prova', 'Prova da aula', ''.join(out), 'Prova com perguntas de múltipla escolha')


def academia_certificado():
    out = [f'<rect x="24" y="64" width="432" height="224" rx="8" fill="#fffdf8" stroke="{ACCENT}" stroke-width="2"/><rect x="32" y="72" width="416" height="208" rx="5" fill="none" stroke="#fcd9b6"/>',
           f'<text x="240" y="106" font-family="Manrope" font-size="9" font-weight="800" fill="{ACCENT}" text-anchor="middle" letter-spacing="2">ACADEMIA ORAVEL</text>',
           f'<text x="240" y="140" font-family="Manrope" font-size="20" font-weight="800" fill="{INK}" text-anchor="middle">Certificado de conclusão</text>',
           f'<text x="240" y="170" font-family="Inter" font-size="9.5" fill="{SOFT}" text-anchor="middle">Certificamos que</text>',
           f'<text x="240" y="194" font-family="Manrope" font-size="15" font-weight="800" fill="{INK}" text-anchor="middle">Mariana Souza</text>',
           f'<text x="240" y="216" font-family="Inter" font-size="9.5" fill="{SOFT}" text-anchor="middle">concluiu o curso Manutenção, com aproveitamento de 92%.</text>',
           f'<line x1="170" y1="250" x2="310" y2="250" stroke="{LINE}"/><text x="240" y="264" font-family="Inter" font-size="8" fill="{SOFT}" text-anchor="middle">Código de verificação · OR-7F3A91C2</text>',
           f'<circle cx="420" cy="252" r="16" fill="{ACCENT}" opacity=".15"/><text x="420" y="256" font-family="Manrope" font-size="12" font-weight="800" fill="{ACCENT}" text-anchor="middle">✓</text>']
    return _chrome('academia/certificado', 'Certificado', ''.join(out), 'Certificado de conclusão da Academia Oravel')
