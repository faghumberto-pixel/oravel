#!/usr/bin/env python3
"""Monta as páginas legais do site (Política de Privacidade e Termos de Uso).

  python3 docs/site-legal/build.py              # PREVIEW: faixa RASCUNHO, noindex, campos vazios destacados
  python3 docs/site-legal/build.py --publish    # grava public_html/<slug>/index.html; RECUSA se faltar dado

Os dados vêm de dados-empresa.json. Trechos condicionais no texto (<!--IF:FLAG--> ... <!--ELSE--> ...
<!--ENDIF-->) dependem de FLAGS: mantenha-os iguais ao que o site realmente faz.
"""
import argparse, html, json, re, sys
from datetime import date
from pathlib import Path

HERE = Path(__file__).parent
ROOT = HERE.parent.parent
CSS = (HERE / 'base.css').read_text() + '\n' + (HERE / 'legal.css').read_text()
PAGES = [
    ('politica-de-privacidade', 'Política de Privacidade',
     'Como a Oravel trata dados pessoais no site e na plataforma, em conformidade com a LGPD.'),
    ('termos-de-uso', 'Termos de Uso',
     'Condições de uso da plataforma Oravel por empresas clientes.'),
]
MENU = [('Locação de Equipamentos', '/erp-cmms/'), ('Manutenção Industrial', '/software-manutencao-industrial/'),
        ('Montagem Industrial', '/software-montagem-industrial/'), ('Logística e Transportes', '/software-gestao-frota-logistica/'),
        ('Gestão de Serviços', '/software-gestao-de-servicos/'), ('Distribuidor e Atacadista', '/wms/'),
        ('Qualquer Segmento', '/crm/')]
PH = re.compile(r'\[\[([A-Z_]+)\]\]')


def conditionals(text, flags):
    def repl(m):
        on = bool(flags.get(m.group(1)))
        body = m.group(2)
        yes, _, no = body.partition('<!--ELSE-->')
        return yes if on else no
    return re.sub(r'<!--IF:([A-Z_]+)-->(.*?)<!--ENDIF-->', repl, text, flags=re.S)


def fill(text, data, preview, missing):
    def repl(m):
        key = m.group(1)
        val = (data.get(key) or '').strip()
        if val:
            return html.escape(val)
        missing.add(key)
        return '<mark class="ph">[[%s]]</mark>' % key
    return PH.sub(repl, text)


def page(slug, title, desc, body, data, preview, missing):
    menu = ''.join('<a href="%s">%s</a>' % (h, html.escape(l)) for l, h in MENU)
    banner = ('<div class="draft-banner">RASCUNHO para revisão jurídica. Não publicar sem preencher os campos '
              'em destaque e sem revisão de um advogado.</div>') if preview else ''
    robots = 'noindex,nofollow' if preview else 'index,follow'
    when = fill('[[DATA_VIGENCIA]]', data, preview, missing)
    co = fill('[[RAZAO_SOCIAL]] · CNPJ [[CNPJ]] · [[ENDERECO]]', data, preview, missing)
    body = fill(conditionals(body, data.get('FLAGS', {})), data, preview, missing)
    return f'''<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{title} | Oravel</title>
<meta name="description" content="{html.escape(desc)}">
<meta name="robots" content="{robots}">
<link rel="canonical" href="https://oravel.com.br/{slug}/">
<link rel="icon" type="image/svg+xml" href="/nova/assets/favicon.svg?v=6" />
<link rel="icon" type="image/png" sizes="32x32" href="/nova/assets/favicon-32.png?v=6" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
{CSS}</style>
</head>
<body>
<a class="skip" href="#conteudo">Ir para o conteúdo</a>
{banner}
<nav class="inav" aria-label="Principal">
  <div class="inav-wrap">
    <a href="/" class="inav-logo"><span class="inav-logo-mark" aria-hidden="true">O</span>Oravel</a>
    <div class="inav-links">
      <a href="/">Home</a>
      <a href="/#frentes">Produtos</a>
      <div class="inav-dd"><a href="/segmentos/" aria-haspopup="true">Segmentos</a><div class="inav-dd-menu">{menu}</div></div>
      <a href="/sobre-nos">Sobre</a>
      <a href="/contato.php">Contato</a>
    </div>
    <a href="/contato.php" class="inav-cta">Fale conosco</a>
  </div>
</nav>
<main id="conteudo" class="legal">
  <h1>{title}</h1>
  <p class="meta">Última atualização: {when}</p>
{body}
</main>
<footer class="ifoot">
  <div class="ifoot-wrap">
    <div><div class="ifoot-copy">© {date.today().year} Oravel</div><div class="ifoot-co">{co}</div></div>
    <div class="ifoot-links">
      <a href="/">Home</a>
      <a href="/segmentos/">Segmentos</a>
      <a href="/politica-de-privacidade/">Política de Privacidade</a>
      <a href="/termos-de-uso/">Termos de Uso</a>
      <a href="/contato.php">Contato</a>
    </div>
  </div>
</footer>
</body>
</html>
'''


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('--publish', action='store_true', help='grava em public_html/ e recusa se faltar dado')
    ap.add_argument('--out', help='pasta de saída (padrão: docs/site-legal/preview ou public_html)')
    ap.add_argument('--data', help='outro JSON de dados (padrão: dados-empresa.json)')
    a = ap.parse_args()
    data = json.loads(Path(a.data or HERE / 'dados-empresa.json').read_text())
    flags = data.get('FLAGS', {})
    if not flags.get('TRIAL'):
        data['PRAZO_TRIAL'] = data.get('PRAZO_TRIAL') or '-'   # trecho de teste fica fora quando TRIAL=false
    out_root = Path(a.out) if a.out else (ROOT / 'public_html' if a.publish else HERE / 'preview')
    rendered, all_missing = {}, set()
    for slug, title, desc in PAGES:
        missing = set()
        body = (HERE / f'{slug}.body.html').read_text()
        rendered[slug] = page(slug, title, desc, body, data, not a.publish, missing)
        all_missing |= missing
    if a.publish and all_missing:
        print('RECUSADO: faltam dados em dados-empresa.json ->', ', '.join(sorted(all_missing)), file=sys.stderr)
        sys.exit(1)
    if a.publish and re.search(r'\[\[[A-Z_]+\]\]|<!--(IF|ELSE|ENDIF)', ''.join(rendered.values())):
        print('RECUSADO: sobrou marcador no HTML final', file=sys.stderr)
        sys.exit(1)
    for slug, content in rendered.items():
        d = out_root / slug
        d.mkdir(parents=True, exist_ok=True)
        (d / 'index.html').write_text(content)
        print('ok', d / 'index.html')
    if not a.publish:
        print('campos em branco (destacados no preview):', ', '.join(sorted(all_missing)) or 'nenhum')


if __name__ == '__main__':
    main()
