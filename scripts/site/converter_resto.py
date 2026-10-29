#!/usr/bin/env python3
"""
Converte as páginas institucionais e de conteúdo para o padrão novo, a partir do HTML publicado.
Uso: python3 converter_resto.py <html_publicado_dir> <fonte_faq.php> <public_html>
Textos legais (privacidade e termos) são mantidos palavra por palavra.
"""
import html as H
import json
import re
import sys
from pathlib import Path

import tema as t

PUB, FAQ_SRC, RAIZ = Path(sys.argv[1]), Path(sys.argv[2]), Path(sys.argv[3])


def ler(n):
    return (PUB / n).read_text(encoding='utf-8')


def limpo(inner):
    """Mantém só links, negrito, itálico e quebras."""
    inner = re.sub(r'<(?!/?(a|strong|em|b|br)\b)[^>]+>', '', inner)
    inner = re.sub(r'<a\b[^>]*?href="([^"]*)"[^>]*>', lambda m: f'<a href="{m.group(1)}">', inner)
    inner = re.sub(r'<(strong|em|b)\b[^>]*>', r'<\1>', inner)
    return re.sub(r'\s+', ' ', inner).strip()


def blocos(h, ini=None):
    """Lista (tag, html) de h1/h2/h3/p/li dentro do <main> (ou do corpo)."""
    m = re.search(r'<main\b.*?</main>', h, re.S)
    corpo = m.group(0) if m else h[h.find('<body'):]
    corpo = re.sub(r'<(script|style|nav|footer|svg|form|noscript)\b.*?</\1>', ' ', corpo, flags=re.S)
    out = []
    for mm in re.finditer(r'<(h1|h2|h3|h4|p|li)\b[^>]*>(.*?)</\1>', corpo, re.S):
        s = limpo(mm.group(2))
        if re.sub(r'<[^>]+>', '', s).strip():
            out.append((mm.group(1), s))
    return out


def texto_html(bl):
    out, lis = '', []
    for tag, s in bl + [('fim', '')]:
        if tag != 'li' and lis:
            out += '<ul>' + ''.join(f'<li>{x}</li>' for x in lis) + '</ul>'
            lis = []
        if tag == 'li':
            lis.append(s)
        elif tag in ('h2', 'h3', 'h4'):
            out += f'<{tag}>{s}</{tag}>'
        elif tag == 'p':
            out += f'<p>{s}</p>'
    return out


def meta(h):
    ti = re.search(r'<title>(.*?)</title>', h, re.S).group(1).strip()
    de = (re.search(r'<meta name="description" content="(.*?)"', h) or [None, ti])[1]
    return H.unescape(ti), H.unescape(de)


def salvar(rel, titulo, desc, slug, corpo, ld=None):
    arq = RAIZ / rel
    arq.parent.mkdir(parents=True, exist_ok=True)
    arq.write_text(t.pagina(titulo, desc, 'https://oravel.com.br' + slug, corpo, ld), encoding='utf-8')
    print('ok', rel)


CSS_LEGAL = '<style>.legal{max-width:820px}.legal h2{font-size:22px;margin:34px 0 10px}.legal h3{font-size:17px;margin:22px 0 8px}.legal p,.legal li{font-size:16px;color:#3b4454;margin-top:10px}.legal ul{padding-left:22px}.legal a{color:var(--accent-ink)}</style>'


def pagina_texto(arq, rel, slug, eyebrow):
    h = ler(arq)
    ti, de = meta(h)
    bl = blocos(h)
    h1 = next((s for tg, s in bl if tg == 'h1'), ti)
    corpo_bl = [(tg, s) for tg, s in bl if tg != 'h1']
    corpo = (t.hero_pg(eyebrow, re.sub(r'<[^>]+>', '', h1), 'Texto na íntegra, em vigor.', ctas=False) + CSS_LEGAL
             + f'<section class="sec"><div class="wrap"><div class="legal">{texto_html(corpo_bl)}</div></div></section>')
    salvar(rel, ti, de, slug, corpo)


# ---------------------------------------------------------------- FAQ
def faq():
    src = FAQ_SRC.read_text(encoding='utf-8')
    grupos = []
    for g in re.finditer(r'"titulo"\s*=>\s*"(.*?)",\s*"itens"\s*=>\s*\[(.*?)\n\s*\],\s*\]', src, re.S):
        itens = [(H.unescape(q.replace('\\"', '"')), H.unescape(a.replace('\\"', '"')))
                 for q, a in re.findall(r'\["q"\s*=>\s*"(.*?)",\s*"a"\s*=>\s*"(.*?)"\]', g.group(2), re.S)]
        grupos.append((g.group(1), itens))
    corte = ('Quanto custa', 'A Oravel é cara', 'contrato de fidelidade', 'Tem contrato de fidelidade', 'Preciso pagar por algo')
    corpo = t.hero_pg('Perguntas frequentes', 'Tire suas dúvidas sobre a Oravel.', 'O que a plataforma resolve, como é a implantação, como funciona a migração e o suporte.', ctas=False)
    todos = []
    for i, (tit, itens) in enumerate(grupos):
        itens = [(q, a) for q, a in itens if not any(c.lower() in q.lower() for c in corte)]
        if not itens:
            continue
        todos += itens
        tit = 'Implantação e suporte' if tit.startswith('Preço') else tit
        corpo += t.secao('Perguntas frequentes', tit, None, t.faq(itens), alt=i % 2 == 1)
    corpo += t.cta_final('Não achou a sua dúvida?', 'Fale com a gente pelo WhatsApp ou agende uma demonstração. Respondemos em horário comercial.')
    salvar('faq.php', 'Perguntas Frequentes | Oravel', 'Tire suas dúvidas sobre o que a Oravel resolve, implantação, migração de sistema e suporte.', '/faq.php', corpo, t.faq_ld(todos, 'Perguntas frequentes | Oravel'))


# ---------------------------------------------------------------- Central de conhecimento
def central():
    cards = ''.join(f'<a class="card" href="{u}">{t.ic("hs")}<h3>{ti}</h3><p>{rs}</p></a>' for u, ti, rs in [
        ('/central-de-conhecimento/horimetro.php', 'Como funciona o apontamento de horímetro', 'As várias formas de registrar, as conferências automáticas que evitam erro no histórico, e como isso avisa quando a manutenção preventiva vence.'),
        ('/central-de-conhecimento/manutencao-preventiva-equipamentos-locacao/', 'Como controlar a manutenção preventiva de equipamentos de locação', 'Passo a passo: plano por equipamento, leitura de uso, aviso de vencimento e ordem de serviço para não revisar a máquina só depois que ela quebra.')])
    corpo = (t.hero_pg('Central de conhecimento', 'Guias para a manutenção da sua locadora.', 'Artigos e passo a passo sobre como cada parte da Oravel funciona no dia a dia da sua operação.', ctas=False)
             + t.secao('Artigos', 'Comece por aqui', None, f'<div class="grid3">{cards}</div>') + t.cta_final())
    salvar('central-de-conhecimento/index.php', 'Central de conhecimento | Oravel', 'Artigos e guias sobre como cada parte da Oravel funciona no dia a dia da sua operação.', '/central-de-conhecimento/', corpo)


def artigo(arq, rel, slug):
    h = ler(arq)
    ti, de = meta(h)
    bl = blocos(h)
    h1 = next((s for tg, s in bl if tg == 'h1'), ti)
    resto = [(tg, s) for tg, s in bl if tg != 'h1']
    resposta = next((s for tg, s in resto if tg == 'p'), de)
    # remove a resposta e o bloco de FAQ (vira acordeão)
    corpo_bl, faq_itens = [], []
    ld_faq = re.search(r'"@type":\s*"FAQPage".*?\]\s*\}', h, re.S)
    for m in re.finditer(r'<script type="application/ld\+json">(.*?)</script>', h, re.S):
        try:
            j = json.loads(m.group(1))
        except Exception:
            continue
        for it in (j if isinstance(j, list) else [j]):
            if it.get('@type') == 'FAQPage':
                faq_itens = [(q['name'], q['acceptedAnswer']['text']) for q in it['mainEntity']]
    pular_faq = False
    for tg, s in resto:
        if tg == 'h2' and re.search(r'perguntas', s, re.I):
            pular_faq = True
            continue
        if tg == 'h2':
            pular_faq = False
        if pular_faq:
            continue
        if tg == 'p' and s == resposta and not corpo_bl:
            continue
        corpo_bl.append((tg, s))
    art_ld = [json.loads(m.group(1)) for m in re.finditer(r'<script type="application/ld\+json">(.*?)</script>', h, re.S)]
    corpo = (t.hero_pg('Central de conhecimento', re.sub(r'<[^>]+>', '', h1), re.sub(r'<[^>]+>', '', resposta), ctas=False) + CSS_LEGAL
             + f'<section class="sec"><div class="wrap"><div class="legal">{texto_html(corpo_bl)}</div></div></section>'
             + (t.secao('Perguntas frequentes', 'Perguntas frequentes', None, t.faq(faq_itens), alt=True) if faq_itens else '')
             + t.cta_final())
    arqs = RAIZ / rel
    arqs.parent.mkdir(parents=True, exist_ok=True)
    pg = t.pagina(ti, de, 'https://oravel.com.br' + slug, corpo, None)
    for j in art_ld:
        pg = pg.replace('</head>', '<script type="application/ld+json">' + json.dumps(j, ensure_ascii=False) + '</script></head>', 1)
    arqs.write_text(pg, encoding='utf-8')
    print('ok', rel)


# ---------------------------------------------------------------- Novidades
def novidades():
    h = ler('novidades_php.html')
    bl = blocos(h)
    h1 = next((s for tg, s in bl if tg == 'h1'), 'Novidades')
    sub = next((s for tg, s in bl if tg == 'p'), '')
    cards, atual = [], None
    for tg, s in bl:
        if tg in ('h2', 'h3'):
            atual = [s, '']
            cards.append(atual)
        elif tg == 'p' and atual is not None and not atual[1]:
            atual[1] = s
    cards = [c for c in cards if c[1]]
    corpo = (t.hero_pg('Novidades', re.sub(r'<[^>]+>', '', h1.replace('<br>', ' ')), re.sub(r'<[^>]+>', '', sub), ctas=False)
             + t.secao('O que há de novo', 'Atualizações da plataforma', None, '<div class="grid3">' + ''.join(
                 f'<div class="card">{t.ic(["os", "es", "hs", "co", "lo", "av"][i % 6])}<h3>{a}</h3><p>{b}</p></div>' for i, (a, b) in enumerate(cards)) + '</div>')
             + t.cta_final())
    salvar('novidades.php', 'Novidades | Oravel', 'Veja o que há de novo na Oravel: orçamentos com aprovação online, compras, mapa de equipamentos e mais.', '/novidades.php', corpo)


# ---------------------------------------------------------------- Sobre
def sobre():
    h = ler('sobre_.html')
    bl = blocos(h)
    h1 = next((s for tg, s in bl if tg == 'h1'), 'A tecnologia que nasceu da cicatriz, não da planilha')
    ps = [s for tg, s in bl if tg == 'p']
    vals = []
    for i, (tg, s) in enumerate(bl):
        if tg == 'h3' and i + 1 < len(bl) and bl[i + 1][0] == 'p' and len(vals) < 3:
            vals.append((s, bl[i + 1][1]))
    desc_vals = {b for _, b in vals}
    sub = 'Software feito por quem já esteve do outro lado do balcão: na locação, na manutenção e no campo.'
    historia = [s for s in ps if len(s) > 60 and s not in desc_vals and not s.startswith('Conheça as soluções') and s != sub][:7]
    corpo = (t.hero_pg('Nossa história', re.sub(r'<[^>]+>', '', h1), re.sub(r'<[^>]+>', '', sub), ctas=False) + CSS_LEGAL
             + f'<section class="sec"><div class="wrap"><div class="legal">{"".join(f"<p>{p}</p>" for p in historia)}</div></div></section>'
             + (t.secao('Nossos valores', 'No que acreditamos', None, '<div class="grid3">' + ''.join(f'<div class="card">{t.ic(["pm", "hs", "ck"][i])}<h3>{a}</h3><p>{b}</p></div>' for i, (a, b) in enumerate(vals)) + '</div>', alt=True) if vals else '')
             + t.cta_final('Pronto para tirar sua operação da planilha?', 'Conheça os recursos da Oravel ou agende uma demonstração com o cenário real da sua operação.'))
    salvar('sobre/index.html', 'Sobre a Oravel | A tecnologia que nasceu da operação', 'Software feito por quem já esteve do outro lado do balcão: na locação, na manutenção e no campo. Conheça a história da Oravel.', '/sobre/', corpo)


# ---------------------------------------------------------------- Inteligência artificial (sem a leitura de OS em papel)
def ia():
    itens = [('hs', 'Manutenção que se antecipa', 'Aponta o que está atrasado, o que quebrou depois da preventiva e onde o tempo entre falhas está caindo.'),
             ('al', 'Retrabalho em corretivas', 'Mostra quantas vezes você voltou ao mesmo equipamento e o motivo mais comum.'),
             ('av', 'Diagnóstico de avarias', 'Ajuda a descrever local, tipo, gravidade e causa provável para o dossiê.'),
             ('es', 'Saúde do estoque', 'Destaca materiais críticos, quantos dias faltam para acabar e itens parados.'),
             ('lo', 'Rotas do dia', 'Resume as rotas por veículo, com distância e custo calculados pelo sistema.'),
             ('co', 'Risco de perda de leads', 'No CRM, avalia quais negociações estão em risco e o que fazer a seguir.'),
             ('ck', 'Avaliação de propostas', 'Revisa coerência e clareza e estima a chance de fechamento antes do envio.')]
    corpo = (t.hero_pg('Inteligência artificial no sistema', 'Menos achismo. Mais decisão com dados.',
                       'Os números vêm do próprio sistema, calculados com regra e sem invenção. A inteligência artificial lê esses números, destaca o que importa e diz o que fazer primeiro.', ctas=False)
             + t.secao('Como funciona', 'Três princípios', None, '<div class="grid3">' + ''.join(f'<div class="card">{t.ic(i)}<h3>{a}</h3><p>{b}</p></div>' for i, a, b in [
                 ('hs', 'Números calculados pelo sistema', 'Nada de dado inventado: tudo vem do que a sua equipe registra.'), ('pm', 'A IA só interpreta e prioriza', 'Ela destaca o que importa e sugere o que fazer primeiro.'),
                 ('ck', 'Dados isolados por empresa', 'Os dados da sua empresa ficam separados dos das demais.')]) + '</div>', alt=True)
             + t.secao('Onde ela ajuda', 'Oito formas de apoio à decisão', None, '<div class="grid4">' + ''.join(f'<div class="card">{t.ic(i)}<h3>{a}</h3><p>{b}</p></div>' for i, a, b in itens) + '</div>')
             + t.cta_final())
    salvar('inteligencia-artificial/index.html', 'Inteligência artificial no sistema | Oravel', 'Menos achismo, mais decisão com dados: a IA da Oravel interpreta os números do sistema e diz o que fazer primeiro.', '/inteligencia-artificial/', corpo)


# ---------------------------------------------------------------- Academia
def academia():
    corpo = (t.hero_pg('Academia Oravel', 'Aprendizado que transforma a sua operação.',
                       'Sua equipe aprende a usar o sistema dentro do próprio sistema: aulas curtas, provas e certificado, organizadas por solução.', ctas=False)
             + t.secao('O que a Academia oferece', 'Aprender fazendo, no próprio sistema', None, '<div class="grid4">' + ''.join(f'<div class="card">{t.ic(i)}<h3>{a}</h3><p>{b}</p></div>' for i, a, b in [
                 ('pm', 'Cursos do seu plano', 'Cada solução abre só os cursos que você contratou: nada de conteúdo que não serve para a sua operação.'),
                 ('os', 'Aulas passo a passo', 'Telas reais do sistema, caminhos de menu e dicas de quem usa no dia a dia.'),
                 ('ck', 'Provas e certificado', 'Prova a cada aula e certificado de conclusão para a sua equipe.'),
                 ('co', 'Configure sozinho', 'Treinamentos de WhatsApp, e-mail e cobrança automática, sem depender de ninguém.')]) + '</div>', alt=True)
             + t.secao('App no celular', 'Cada solução tem o seu próprio app', 'Instale pelo navegador, sem loja de aplicativos. Gestor, técnico e colaborador entram no mesmo app e veem só o que o perfil permite.',
                       '<div class="split rev"><div class="split-t"><ul class="ticks"><li>Abre direto na tela que você mais usa</li><li>Instala pelo navegador</li><li>Cada perfil vê só o que pode</li></ul></div>' f'<div>{t.mk_celular()}</div></div>')
             + t.cta_final('A Academia vem com a sua solução', 'Teste a Oravel por 30 dias, com treinamento incluso.'))
    salvar('academia/index.html', 'Academia Oravel | Aulas, provas e certificado', 'Aulas curtas, provas e certificado dentro do próprio sistema, organizadas por solução. Conheça a Academia Oravel.', '/academia/', corpo)


faq()
central()
artigo('central-de-conhecimento_horimetro_php.html', 'central-de-conhecimento/horimetro.php', '/central-de-conhecimento/horimetro.php')
artigo('central-de-conhecimento_manutencao-preventiva-equipamentos-locacao_.html', 'central-de-conhecimento/manutencao-preventiva-equipamentos-locacao/index.php',
       '/central-de-conhecimento/manutencao-preventiva-equipamentos-locacao/')
novidades()
sobre()
ia()
academia()
pagina_texto('politica-de-privacidade_.html', 'politica-de-privacidade/index.html', '/politica-de-privacidade/', 'Legal')
pagina_texto('termos-de-uso_.html', 'termos-de-uso/index.html', '/termos-de-uso/', 'Legal')
