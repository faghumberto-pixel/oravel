#!/usr/bin/env python3
"""
Monta a PRÉVIA navegável do novo posicionamento (home + calculadora + diagnóstico) em UM arquivo, para publicar como
artefato privado antes de mexer no site de verdade. Roda depois de `adequar_posicionamento.py` ter sido aplicado numa
cópia da pasta public_html.

O arquivo sai como fragmento (sem <html>/<head>/<body>), no formato que o artefato espera:
título, CSS do site embutido e o conteúdo. O que a prévia muda em relação ao site real:
  - links entre as 3 páginas viram abas (#inicio, #calculadora, #diagnostico); os demais abrem o site no ar;
  - o formulário do diagnóstico NÃO envia (mostra um aviso), para não gerar lead de teste;
  - scripts externos fora da lista permitida do artefato (medição, proteção, anti-robô) são removidos.

Uso: python3 montar_previa_posicionamento.py <public_html_copia> <saida.html>
"""
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import adequar_posicionamento as a  # noqa: E402

RAIZ, SAIDA = Path(sys.argv[1]), Path(sys.argv[2])

CSS_PREVIA = """
#pv-bar{background:#0b0f0d;color:#e5e7eb;padding-block:10px;padding-left:max(16px,env(safe-area-inset-left,0px));padding-right:max(16px,env(safe-area-inset-right,0px));
display:flex;flex-wrap:wrap;gap:8px 18px;align-items:center;font:13px/1.3 Inter,system-ui,sans-serif}
#pv-bar b{color:#fdba74}
#pv-bar nav{display:flex;gap:6px;flex-wrap:wrap;margin-left:auto}
#pv-bar a{color:#e5e7eb;text-decoration:none;padding:6px 12px;border-radius:999px;border:1px solid rgba(255,255,255,.22)}
#pv-bar a[aria-current="page"]{background:#f97316;border-color:#f97316;color:#1a0d05;font-weight:700}
#pv-envio{margin:10px 0 0;padding:12px 14px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa;color:#7c2d12;font-size:14px}
"""

ROTEIRO = """
(function(){
  var PG=['inicio','calculadora','diagnostico'];
  function mostrar(){
    var h=(location.hash||'').slice(1);
    if(PG.indexOf(h)<0){ if(h&&document.getElementById(h))return; h='inicio'; }
    document.querySelectorAll('[data-pg]').forEach(function(d){d.hidden=d.getAttribute('data-pg')!==h});
    document.querySelectorAll('#pv-bar nav a').forEach(function(l){l.setAttribute('aria-current',l.getAttribute('data-t')===h?'page':'false')});
    if(h==='diagnostico'&&window.__initDiag)window.__initDiag();
    window.scrollTo(0,0);
  }
  window.addEventListener('hashchange',mostrar);
  document.addEventListener('click',function(e){
    var l=e.target.closest&&e.target.closest('a[href^="/"]'); if(!l)return;
    var href=l.getAttribute('href'), p=href.split('?')[0].replace(/#.*/,'');
    var alvo={'/':'inicio','/calculadora/':'calculadora','/diagnostico/':'diagnostico'}[p];
    e.preventDefault();
    if(alvo){
      window.__pq=href.indexOf('?')>0?href.slice(href.indexOf('?')):'';
      if(location.hash==='#'+alvo)mostrar(); else location.hash='#'+alvo;
    } else { window.open('https://oravel.com.br'+href,'_blank','noopener'); }
  },true);
  var f=document.getElementById('dg-form');
  if(f)f.addEventListener('submit',function(e){
    e.preventDefault();
    var n=document.getElementById('pv-envio');
    if(!n){n=document.createElement('p');n.id='pv-envio';f.parentNode.appendChild(n)}
    n.textContent='Prévia: o envio está desligado. No site, este formulário envia o pedido para o time comercial da Oravel.';
  });
  mostrar();
})();
"""


def main_de(html):
    m = re.search(r'<main id="conteudo">(.*?)</main>', html, re.S)
    assert m, 'main não encontrado'
    return m.group(1)


def decodificar_cfemail(h):
    """A Cloudflare embaralha e-mails ao servir a página (data-cfemail); na prévia mostramos o endereço de verdade."""
    k = int(h[:2], 16)
    return ''.join(chr(int(h[i:i + 2], 16) ^ k) for i in range(2, len(h), 2))


def sem_scripts_externos(html):
    """Fora da lista do artefato: medição, proteção, anti-robô e o que a Cloudflare injeta na página servida."""
    html = re.sub(r'<(a|span)[^>]*data-cfemail="([0-9a-f]+)"[^>]*>.*?</\1>', lambda m: decodificar_cfemail(m.group(2)), html, flags=re.S)
    html = re.sub(r'<script[^>]*>[^<]*challenge-platform[^<]*</script>', '', html)
    html = re.sub(r'<script[^>]*data-cfasync="false"[^>]*></script>', '', html)
    html = re.sub(r'<script[^>]*src="https://app\.oravel\.com\.br/[^"]*"[^>]*></script>', '', html)
    html = re.sub(r'<script[^>]*src="/protecao\.js[^"]*"[^>]*></script>', '', html)
    html = re.sub(r'<script[^>]*src="https://challenges\.cloudflare\.com/[^"]*"[^>]*></script>', '', html)
    html = re.sub(r'<script[^>]*src="/cdn-cgi/[^"]*"[^>]*></script>', '', html)
    html = html.replace('<script src="https://cdn.tailwindcss.com"></script>', '<script src="https://cdn.tailwindcss.com/3.4.17"></script>')
    return html


home = (RAIZ / 'index.html').read_text(encoding='utf-8')
calc = (RAIZ / 'calculadora' / 'index.html').read_text(encoding='utf-8')
diag = (RAIZ / 'diagnostico' / 'index.html').read_text(encoding='utf-8')

head = home[:home.index('</head>')]
css = ''.join(re.findall(r'<style[^>]*>(.*?)</style>', head, re.S)) + a.CSS_NOVAS + CSS_PREVIA
fontes = ''.join(re.findall(r'<link[^>]*fonts\.(?:googleapis|gstatic)\.com[^>]*>', head))

corpo = home[home.index('<body'):]
corpo = corpo[corpo.index('>') + 1:]
corpo = corpo.replace('</body>', '').replace('</html>', '')

# o diagnóstico lê ?eq=&perda= da URL; na prévia o valor vem do clique na calculadora (window.__pq)
js_diag_previa = (a.JS_DIAG.replace('(function(){', 'window.__initDiag=function(){', 1)
                  .replace('location.search', '(window.__pq||"")')
                  .replace("var nota=document.getElementById('nota-calc');", "var nota=document.getElementById('nota-calc');nota.hidden=true;"))
js_diag_previa = js_diag_previa.rstrip()
assert js_diag_previa.endswith('})();')
js_diag_previa = js_diag_previa[:-len('})();')] + '};'

m_home, m_calc, m_diag = main_de(home), main_de(calc), main_de(diag)
assert f'<script>{a.JS_DIAG}</script>' in m_diag
m_diag = m_diag.replace(f'<script>{a.JS_DIAG}</script>', f'<script>{js_diag_previa}</script>', 1)

paginas = (
    f'<div data-pg="inicio"><main id="conteudo">{m_home}</main></div>'
    f'<div data-pg="calculadora" hidden><main id="conteudo-calc">{m_calc}</main></div>'
    f'<div data-pg="diagnostico" hidden><main id="conteudo-diag">{m_diag}</main></div>')
corpo = re.sub(r'<main id="conteudo">.*?</main>', lambda _: paginas, corpo, count=1, flags=re.S)

barra = ('<div id="pv-bar"><span><b>Prévia, ainda não publicada.</b> Novo posicionamento do site da Oravel.</span>'
         '<nav aria-label="Páginas da prévia"><a href="#inicio" data-t="inicio">Home</a>'
         '<a href="#calculadora" data-t="calculadora">Calculadora</a><a href="#diagnostico" data-t="diagnostico">Diagnóstico</a></nav></div>')

saida = (f'<title>Prévia Novo Posicionamento</title>\n{fontes}\n<style>{css}</style>\n{barra}\n{sem_scripts_externos(corpo)}\n'
         f'<script>{ROTEIRO}</script>\n')
SAIDA.write_text(saida, encoding='utf-8')
print(f'prévia gerada: {SAIDA} ({len(saida) / 1024:.0f} KB)')
