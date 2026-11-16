#!/usr/bin/env python3
"""
Landing page SEPARADA do site institucional, com um único objetivo: marcar reunião para o Diagnóstico Operacional
Gratuito para locadoras. Sem menu do site, sem rodapé de navegação, sem widget de WhatsApp: só
  1 problema forte, 3 benefícios, 1 formulário curto, (opcional) 1 vídeo de 90 s e 1 botão.

Publica em <public_html>/diagnostico-gratuito/index.html (noindex: é página de campanha, não de busca; fora do sitemap).

O formulário envia para o MESMO /contato.php do site (mesmo anti-robô, mesmo e-mail, mesmo CRM), por fetch, para a
pessoa ficar na landing e ver o agradecimento ali. Carrega junto origem=lp-diagnostico, utm_*, gclid e a URL de entrada
(os campos que o contato.php já sabe gravar), para a campanha de Ads saber de onde veio cada pedido.

Vídeo: NÃO existe ainda. Sem --video a seção simplesmente não aparece (nada de "em breve" na página pública).
  --video /diagnostico-gratuito/video.mp4 [--poster /diagnostico-gratuito/capa.jpg]

Uso: python3 criar_landing_diagnostico.py <public_html> [--video URL] [--poster URL] [--previa saida.html]
"""
import argparse
import html as H
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import tema as t  # noqa: E402

TURNSTILE_KEY = '0x4AAAAAAEUrIcrw2tIq9KEj'  # a mesma chave pública do /contato.php
CTA = 'Quero um diagnóstico gratuito da minha operação'
TITULO = 'Diagnóstico Operacional Gratuito para locadoras | Oravel'
DESC = ('Descubra quanto a sua locadora perde por mês com equipamento parado. Um consultor que já viveu locadora '
        'mostra onde está o dinheiro e por onde começar, sem você trocar de sistema.')

CSS = """
:root{--ink:#0f172a;--soft:#5b6472;--line:#e4e7ec;--paper:#fff;--surface:#f6f7f9;--accent:#f97316;--accent-ink:#c2410c;
--navy:#0f172a;--navy2:#08142c;--ok:#16a34a;--err:#b42318}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;font-family:Inter,"Segoe UI",system-ui,sans-serif;color:var(--ink);background:var(--paper);line-height:1.5;-webkit-font-smoothing:antialiased}
h1,h2,h3{font-family:Manrope,Inter,sans-serif;font-weight:800;letter-spacing:-.02em;margin:0;text-wrap:balance}
p,ul{margin:0}a{color:inherit}img,video{max-width:100%}
:focus-visible{outline:3px solid var(--accent);outline-offset:2px}
.w{max-width:1120px;margin:0 auto;padding-inline:max(20px,env(safe-area-inset-left,0px)) max(20px,env(safe-area-inset-right,0px))}
.logo{font-family:Manrope,sans-serif;font-weight:900;font-size:22px;letter-spacing:-.01em;color:#fff;display:inline-block}
.logo i{color:var(--accent);font-style:normal}
.hero{background:var(--navy2);color:#fff;padding-block:22px 56px}
.hero .grid{display:grid;gap:34px;margin-top:34px;align-items:start}
@media(min-width:920px){.hero .grid{grid-template-columns:1.1fr .9fr;gap:56px;margin-top:56px}}
.eyebrow{font-family:Manrope,sans-serif;font-size:12.5px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#fdba74}
h1{font-size:clamp(32px,5.4vw,54px);line-height:1.06;margin:14px 0 18px}
h1 em{font-style:normal;color:var(--accent)}
.sub{font-size:clamp(17px,2.1vw,20px);color:#cbd5e1;max-width:52ch}
.sub b{color:#fff}
.card{background:#fff;color:var(--ink);border-radius:20px;padding:26px 24px;box-shadow:0 18px 50px rgba(0,0,0,.35)}
.card h2{font-size:22px;margin-bottom:4px}
.card .lead{color:var(--soft);font-size:14.5px;margin-bottom:16px}
label{display:block;font-weight:600;font-size:14px;margin:0 0 5px}
input[type=text],input[type=email],input[type=tel],select{width:100%;font:inherit;font-size:16px;padding:12px;border:1px solid #c3c9d3;border-radius:10px;background:#fff;color:var(--ink)}
.f{display:grid;gap:13px}
.hp{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
.btn{display:flex;align-items:center;justify-content:center;width:100%;padding:15px 18px;border-radius:12px;border:0;background:var(--accent);color:#0f172a;font:800 17px/1.2 Manrope,Inter,sans-serif;cursor:pointer;text-align:center}
.btn{text-decoration:none}.btn:hover{background:#ea580c}.btn[disabled]{opacity:.65;cursor:wait}
.fine{font-size:12.5px;color:var(--soft)}.fine a{text-decoration:underline}
.msg{border-radius:10px;padding:12px 14px;font-size:14.5px}
.msg.err{background:#fef3f2;border:1px solid #fecdca;color:var(--err)}
.ok-box{text-align:center;padding:10px 4px}
.ok-box .tick{width:52px;height:52px;border-radius:50%;background:#dcfce7;color:var(--ok);display:grid;place-items:center;font-size:28px;font-weight:800;margin:0 auto 12px}
.ok-box h2{margin-bottom:8px}.ok-box p{color:var(--soft)}
.ben{padding-block:56px;background:var(--surface)}
.ben h2{font-size:clamp(24px,3.4vw,34px);margin-bottom:26px;max-width:24ch}
.ben ul{list-style:none;padding:0;display:grid;gap:16px}
@media(min-width:820px){.ben ul{grid-template-columns:repeat(3,1fr);gap:22px}}
.ben li{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px}
.ben h3{font-size:19px;margin-bottom:8px}.ben p{color:var(--soft);font-size:15.5px}
.ben .n{display:inline-grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#fff1e4;color:var(--accent-ink);font:800 16px Manrope,sans-serif;margin-bottom:12px}
.vid{padding-block:50px;background:#fff}
.vid h2{font-size:clamp(22px,3vw,30px);margin-bottom:18px}
.vid video{width:100%;max-width:820px;border-radius:16px;background:#000;display:block}
.fim{padding-block:44px;background:var(--navy2);color:#fff;text-align:center}
.fim h2{font-size:clamp(22px,3vw,30px);margin-bottom:16px;max-width:26ch;margin-inline:auto}
.fim .btn{max-width:520px;margin-inline:auto}
.rod{padding-block:22px;background:var(--navy2);color:#94a3b8;font-size:13px;text-align:center;border-top:1px solid rgba(255,255,255,.1)}
.rod a{text-decoration:underline}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
"""

JS = """
(function(){
  var f=document.getElementById('dg'), q=new URLSearchParams(location.search);
  function put(n,v){var e=f.elements[n];if(e&&v)e.value=String(v).slice(0,200)}
  ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'].forEach(function(k){put(k,q.get(k))});
  ['gclid','gbraid','wbraid'].forEach(function(k){var v=q.get(k);if(v&&/^[A-Za-z0-9_\\-]+$/.test(v))put(k,v)});
  put('landing_url',location.origin+location.pathname+location.search);
  var erro=document.getElementById('erro'), btn=document.getElementById('enviar');
  function falha(m){erro.textContent=m;erro.hidden=false;btn.disabled=false;btn.textContent=btn.getAttribute('data-txt');
    try{if(window.turnstile)window.turnstile.reset()}catch(e){}}
  f.addEventListener('submit',function(e){
    e.preventDefault(); erro.hidden=true;
    var v=function(id){var el=f.elements[id];return el?(el.options?el.options[el.selectedIndex].text:el.value):''};
    f.elements.message.value=['[Diagnóstico Operacional Gratuito - landing]','Equipamentos: '+v('eq'),'Como controla hoje: '+v('sis')].join('\\n');
    put('landing_url',location.origin+location.pathname+location.search);  // de novo no envio: garante a origem da campanha
    if(!f.checkValidity()){f.reportValidity();return}
    btn.disabled=true; btn.setAttribute('data-txt',btn.textContent); btn.textContent='Enviando...';
    fetch('/contato.php',{method:'POST',body:new FormData(f),credentials:'same-origin'})
      .then(function(r){return r.text()})
      .then(function(h){
        if(/class="fmsg ok"/.test(h)){
          document.getElementById('form-area').hidden=true; document.getElementById('obrigado').hidden=false;
          document.getElementById('obrigado').scrollIntoView({block:'center'});
          try{if(window.__otrack)window.__otrack('lead_diagnostico')}catch(x){}
        } else if(/captcha_error|robô/.test(h)){ falha('Confirme a verificação "não sou um robô" e envie de novo.'); }
        else { falha('Não conseguimos enviar agora. Tente de novo ou chame no WhatsApp """ + t.FONE + """.'); }
      })
      .catch(function(){ falha('Sem conexão no momento. Tente de novo ou chame no WhatsApp """ + t.FONE + """.'); });
  });
})();
"""


def pagina(video=None, poster=None, previa=False):
    form = (
        '<div id="form-area"><h2>Peça o seu diagnóstico</h2>'
        '<p class="lead">Quatro campos. Um consultor responde pelo WhatsApp ou e-mail.</p>'
        '<form class="f" id="dg" method="post" action="/contato.php" novalidate>'
        '<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">'
        '<input type="hidden" name="origem" value="lp-diagnostico">'
        + ''.join(f'<input type="hidden" name="{n}" value="">' for n in
                  ('utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'landing_url'))
        + '<textarea name="message" hidden></textarea>'
        '<div><label for="name">Seu nome</label><input type="text" id="name" name="name" autocomplete="name" required></div>'
        '<div><label for="company">Empresa</label><input type="text" id="company" name="company" autocomplete="organization" required></div>'
        '<div><label for="phone">WhatsApp</label><input type="tel" id="phone" name="phone" autocomplete="tel" inputmode="tel" required></div>'
        '<div><label for="email">E-mail</label><input type="email" id="email" name="email" autocomplete="email" required></div>'
        '<div><label for="eq">Quantos equipamentos a locadora tem?</label><select id="eq" name="eq">'
        '<option>Até 20</option><option>De 21 a 50</option><option>De 51 a 100</option><option>Mais de 100</option></select></div>'
        '<div><label for="sis">Como controla hoje?</label><select id="sis" name="sis">'
        '<option>Planilha e papel</option><option>Tenho um ERP</option><option>Sistema só de manutenção</option><option>Não controlo direito</option></select></div>'
        f'<div class="cf-turnstile" data-sitekey="{TURNSTILE_KEY}"></div>'
        '<p class="msg err" id="erro" role="alert" hidden></p>'
        f'<button class="btn" id="enviar" type="submit">{CTA}</button>'
        '<p class="fine">Gratuito e sem compromisso. Usamos seus dados só para responder a este pedido, conforme a '
        '<a href="/politica-de-privacidade/" target="_blank" rel="noopener">Política de Privacidade</a>.</p></form></div>'
        '<div class="ok-box" id="obrigado" hidden tabindex="-1"><div class="tick" aria-hidden="true">&#10003;</div>'
        '<h2>Pedido recebido</h2><p>Um consultor da Oravel vai falar com você em breve para combinar o melhor horário do diagnóstico.</p></div>')

    video_html = ''
    if video:
        pst = f' poster="{H.escape(poster)}"' if poster else ''
        video_html = ('<section class="vid"><div class="w"><h2>Veja em 90 segundos como funciona o diagnóstico</h2>'
                      f'<video controls preload="metadata" playsinline{pst}><source src="{H.escape(video)}" type="video/mp4">'
                      'Seu navegador não reproduz este vídeo.</video></div></section>')

    corpo = (
        '<header class="hero"><div class="w"><span class="logo" aria-label="Oravel">O<i>r</i>avel</span>'
        '<div class="grid"><div>'
        '<div class="eyebrow">Diagnóstico Operacional Gratuito para locadoras</div>'
        '<h1>Quanto a sua locadora perde por mês com <em>equipamento parado?</em></h1>'
        '<p class="sub">A maioria dos donos de locadora não sabe esse número. <b>Numa conversa rápida</b>, um consultor que já viveu '
        'locadora mostra onde está o dinheiro parado e por onde começar, sem você trocar o sistema que usa hoje.</p>'
        f'</div><div class="card" id="pedido">{form}</div></div></div></header>'
        '<main><section class="ben"><div class="w"><h2>O que você leva do diagnóstico</h2><ul>'
        '<li><span class="n">1</span><h3>O número da sua perda</h3><p>Onde a operação perde dinheiro: equipamento parado, '
        'preventiva atrasada, contrato e cobrança.</p></li>'
        '<li><span class="n">2</span><h3>Sem trocar de sistema</h3><p>A Oravel trabalha ao lado do ERP que você já usa. '
        'Nada para migrar e nenhuma parada na operação.</p></li>'
        '<li><span class="n">3</span><h3>Um primeiro passo claro</h3><p>Você sai sabendo por qual problema começar, '
        'em vez de contratar tudo de uma vez.</p></li></ul></div></section>'
        f'{video_html}'
        '<section class="fim"><div class="w"><h2>Descubra onde a sua locadora está perdendo dinheiro</h2>'
        f'<a class="btn" href="#pedido" id="cta-fim">{CTA}</a></div></section></main>'
        f'<footer class="rod"><div class="w">Oravel &middot; CNPJ 65.707.953/0001-30 &middot; {t.EMAIL} &middot; '
        '<a href="/politica-de-privacidade/" target="_blank" rel="noopener">Política de Privacidade</a></div></footer>')

    cab = ('<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
           '<meta name="viewport" content="width=device-width,initial-scale=1">'
           f'<title>{H.escape(TITULO)}</title><meta name="description" content="{H.escape(DESC)}">'
           '<meta name="robots" content="noindex,follow">'
           '<link rel="canonical" href="https://oravel.com.br/diagnostico-gratuito/">'
           f'<meta property="og:type" content="website"><meta property="og:title" content="{H.escape(TITULO)}">'
           f'<meta property="og:description" content="{H.escape(DESC)}">'
           f'{t.FAVICON}{t.FONTES}<style>{CSS}</style></head><body>')
    pe = ('<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>'
          '<script async src="https://app.oravel.com.br/t.js?v=2"></script>')
    return f'{cab}{corpo}<script>{JS}</script>{pe}</body></html>'


def para_previa(pg):
    """Fragmento para artefato: sem scripts externos bloqueados e com o envio desligado."""
    css = re.search(r'<style>(.*?)</style>', pg, re.S).group(1)
    fontes = t.FONTES
    corpo = pg[pg.index('<body>') + 6:pg.index('<script>')]
    js = re.search(r'<script>(.*?)</script>', pg, re.S).group(1)
    js = js.replace("fetch('/contato.php',{method:'POST',body:new FormData(f),credentials:'same-origin'})\n      .then(function(r){return r.text()})",
                    "Promise.resolve('<div class=\"fmsg ok\"></div>')")
    assert 'Promise.resolve' in js
    aviso = ('<div style="background:#0b0f0d;color:#fdba74;font:13px Inter,system-ui,sans-serif;padding:8px 16px;text-align:center">'
             'Prévia, ainda não publicada. Aqui o envio está desligado: o botão só mostra a tela de agradecimento.</div>')
    corpo = re.sub(r'<div class="cf-turnstile"[^>]*></div>', '', corpo)
    return f'<title>Landing Diagnóstico Gratuito</title>\n{fontes}\n<style>{css}</style>\n{aviso}\n{corpo}\n<script>{js}</script>\n'


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument('raiz', nargs='?')
    ap.add_argument('--video')
    ap.add_argument('--poster')
    ap.add_argument('--previa')
    a = ap.parse_args()
    pg = pagina(a.video, a.poster)
    if a.previa:
        Path(a.previa).write_text(para_previa(pg), encoding='utf-8')
        print('prévia:', a.previa)
    if a.raiz:
        d = Path(a.raiz) / 'diagnostico-gratuito'
        d.mkdir(exist_ok=True)
        (d / 'index.html').write_text(pg, encoding='utf-8')
        print('landing gerada em', d / 'index.html')


if __name__ == '__main__':
    main()
