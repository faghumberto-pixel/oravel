import re
import shutil
import subprocess
import sys
import unittest
import xml.etree.ElementTree as ET
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import dados  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent / 'public_html'
PAGINAS_PACOTE = {p['pagina']: p['codigo'] for p in dados.PACOTES}
PAGINAS_PACOTE['/software-montagem-industrial/'] = 'servicos'
TEXTO_EXT = ('.html', '.php')


def paginas():
    for p in sorted(RAIZ.rglob('*')):
        if p.suffix in TEXTO_EXT and p.is_file() and 'PHPMailer' not in p.parts and '.bak' not in p.name:
            yield p


def ler(rel):
    return (RAIZ / rel).read_text(encoding='utf-8')


def pagina_de(url):
    return RAIZ / (url.strip('/') + '/index.html' if url.endswith('/') else url.strip('/'))


class TestMenuERodape(unittest.TestCase):
    def test_menu_padrao_identico_nas_paginas_inav(self):
        menus = {}
        for p in paginas():
            t = p.read_text(encoding='utf-8')
            m = re.search(r'<nav class="inav" data-site="nav".*?</nav>', t, re.S)
            if m:
                menus[str(p.relative_to(RAIZ))] = re.sub(r' aria-current="page"', '', m.group(0))
        self.assertGreaterEqual(len(menus), 14)
        self.assertEqual(len(set(menus.values())), 1, 'menu diferente entre páginas')

    def test_menu_tem_solucoes_planos_e_sem_frentes_segmentos_produtos(self):
        menu = re.search(r'<nav class="inav" data-site="nav".*?</nav>', ler('wms/index.html'), re.S).group(0)
        for p in dados.PACOTES:
            self.assertIn(f'<a href="{p["pagina"]}"', menu)
        for texto in ('Soluções', 'Planos', 'Sobre', 'Contato'):
            self.assertIn(texto, menu)
        for proibido in ('Frentes', 'Segmentos', 'Produtos', 'Módulos', '#frentes'):
            self.assertNotIn(proibido, menu)

    def test_nenhuma_pagina_mantem_frentes_segmentos_ou_sobre_nos(self):
        permitido = {'lead-segmento.php', 'erp-new.html', 'pequenas-locadoras.html', 'solucoes.php', 'sobre-nos.html', 'novidades.php'}
        for p in paginas():
            if p.name in permitido:
                continue
            t = p.read_text(encoding='utf-8')
            for padrao in (r'#frentes', r'Nossas frentes', r'>\s*Frentes\s*<', r'/segmentos', r'>\s*Segmentos\s*<', r'/sobre-nos'):
                self.assertIsNone(re.search(padrao, t), f'{p.relative_to(RAIZ)} contém {padrao}')

    def test_home_sem_frentes_no_menu_nem_na_faixa_preta(self):
        t = ler('index.html')
        self.assertNotIn('topbar-seg"', t.split('<style')[-1] if False else t[t.index('<body'):])
        corpo = t[t.index('<body'):]
        self.assertNotIn('Frentes', corpo)
        self.assertIn('data-site="solucoes"', corpo)
        self.assertIn('Como contratar', corpo)
        self.assertIn('topbar-wa', corpo, 'o WhatsApp da faixa preta fica')

    def test_rodape_padrao_com_cnpj_e_solucoes(self):
        for rel in ('wms/index.html', 'solucoes/index.html', 'planos/index.html', 'sobre/index.html', 'contato.php'):
            t = ler(rel)
            self.assertIn('data-site="footer"', t, rel)
            self.assertIn('CNPJ 65.707.953/0001-30', t, rel)


class TestPacotes(unittest.TestCase):
    def test_cada_pagina_de_pacote_tem_cta_correto_e_mockups(self):
        for url, codigo in PAGINAS_PACOTE.items():
            t = pagina_de(url).read_text(encoding='utf-8')
            self.assertIn('<!-- site:pacote -->', t, url)
            self.assertGreaterEqual(t.count('<figure><svg'), 4, f'{url}: poucos mockups')
            if dados.venda_online(codigo):
                self.assertIn(f'https://app.oravel.com.br/assinar/{codigo}', t, url)
            else:
                self.assertNotIn('/assinar/locadora', t, 'ERP Locadoras não tem venda online')
                self.assertIn('Falar com um especialista', t, url)

    def test_locadora_sem_preco_nem_assinar_em_nenhum_lugar(self):
        for rel in ('solucoes/index.html', 'planos/index.html', 'index.html', 'inc/nav-fragment.html'):
            self.assertNotIn('/assinar/locadora', ler(rel), rel)
        planos = ler('planos/index.html')
        bloco = re.search(r'<article class="plan" id="locadora">.*?</article>', planos, re.S).group(0)
        self.assertIn('Falar com um especialista', bloco)
        self.assertIn('/contato.php?pacote=locadora', bloco)

    def test_hub_e_planos_tem_todos_os_pacotes(self):
        for rel in ('solucoes/index.html', 'planos/index.html'):
            t = ler(rel)
            for p in dados.PACOTES:
                self.assertIn(f'id="{p["codigo"]}"', t, rel)
            for p in dados.PACOTES:
                if p['codigo'] != 'locadora':
                    self.assertIn(f'https://app.oravel.com.br/assinar/{p["codigo"]}', t, rel)

    def test_planos_tem_comparativo_sem_valores(self):
        t = ler('planos/index.html')
        self.assertIn('<table>', t)
        self.assertEqual(t.count('<tr>'), len(dados.MATRIZ) + 1)

    def test_montagem_aparece_como_vertical_de_servicos(self):
        t = ler('software-montagem-industrial/index.html')
        self.assertIn('Soluções</a> › <a href="/software-gestao-de-servicos/">Serviços</a>', t)
        self.assertIn('assinar/servicos', t)

    def test_sem_teste_gratis_nas_paginas_de_pacote(self):
        for url in PAGINAS_PACOTE:
            self.assertNotIn('Teste Grátis', pagina_de(url).read_text(encoding='utf-8'), url)

    def test_site_sem_precos(self):
        for rel in ('solucoes/index.html', 'planos/index.html', 'index.html', 'sobre/index.html'):
            fora_dos_mockups = re.sub(r'<svg.*?</svg>|<figcaption>.*?</figcaption>', '', ler(rel), flags=re.S)   # valores dentro das telas são dados fictícios do sistema
            self.assertNotRegex(fora_dos_mockups, r'R\$\s?\d', rel)


class TestMockups(unittest.TestCase):
    def test_todos_os_svgs_sao_xml_valido(self):
        total = 0
        for p in paginas():
            for svg in re.findall(r'<figure><svg.*?</svg>', p.read_text(encoding='utf-8'), re.S):
                ET.fromstring(svg[len('<figure>'):])
                total += 1
        self.assertGreaterEqual(total, 28)


class TestLinksERedirects(unittest.TestCase):
    def regras(self):
        return re.findall(r'RewriteRule \^(.+?)/\?\$ (\S+) \[R=301', ler('.htaccess'))

    def test_redirects_declarados_no_htaccess(self):
        regras = dict(self.regras())
        for chave, destino in dados.REDIRECTS.items():
            self.assertEqual(regras.get(chave), f'https://oravel.com.br{destino}', chave)

    def test_redirects_apontam_para_paginas_que_existem(self):
        for destino in dados.REDIRECTS.values():
            self.assertTrue(pagina_de(destino).exists(), destino)

    def test_links_internos_resolvem(self):
        # Existem só no servidor (não estão no repositório) ou são legado fora do menu atual.
        sem_arquivo_local = {'/contato.php', '/lp.html', '/gestao-frota.html', '/para-quem-nao-tem-sistema.php', '/para-quem-tem-sistema.php', '/proposta/', '/index.php'}
        quebrados = []
        for p in paginas():
            if p.name in ('erp-new.html', 'pequenas-locadoras.html', 'solucoes.php', 'novidades.php', 'faq.php', 'sobre-nos.html', 'erp.html'):
                continue
            for href in re.findall(r'href="([^"]+)"', p.read_text(encoding='utf-8')):
                href = href.replace('https://oravel.com.br', '')
                if not href.startswith('/') or href.startswith('//'):
                    continue
                caminho = href.split('#')[0].split('?')[0]
                if p.name == 'footer.php':
                    continue
                if caminho in ('/', '') or caminho in sem_arquivo_local or caminho.startswith(('/assets/', '/nova/', '/images/', '/inc/')) or re.search(r'\.(css|js|png|jpe?g|webp|svg|ico|json|xml|mp4|webm|pdf|txt)$', caminho):
                    continue
                cand = [RAIZ / caminho.strip('/'), RAIZ / (caminho.strip('/') + '.html'), RAIZ / (caminho.strip('/') + '.php'), RAIZ / caminho.strip('/') / 'index.html', RAIZ / caminho.strip('/') / 'index.php']
                if not any(c.exists() for c in cand):
                    quebrados.append(f'{p.relative_to(RAIZ)} -> {href}')
        self.assertEqual(sorted(set(quebrados)), [])

    def test_modulos_antigos_nao_existem_mais(self):
        for nome in ('crm', 'frota', 'wms', 'os', 'recebimentos', 'rh', 'analytics'):
            self.assertFalse((RAIZ / 'solucoes' / f'{nome}.html').exists(), nome)
        self.assertFalse((RAIZ / 'segmentos' / 'index.html').exists())


class TestContatoESobre(unittest.TestCase):
    def test_contato_sem_banner_azul_e_com_hero_padrao(self):
        t = ler('contato.php')
        self.assertNotIn('#1e40af', t)
        self.assertNotIn('#ef4444', t[: t.index('<body')])
        self.assertIn('linear-gradient(180deg, #eef1f5', t)
        self.assertIn('data-site="nav"', t)
        self.assertNotIn('grátis por 14 dias', t)
        self.assertIn("'pacote-locadora'", t)
        self.assertIn('t.js', t, 'rastreamento preservado')

    @unittest.skipUnless(shutil.which('php'), 'php indisponível')
    def test_contato_php_sem_erro_de_sintaxe(self):
        r = subprocess.run(['php', '-l', str(RAIZ / 'contato.php')], capture_output=True, text=True)
        self.assertEqual(r.returncode, 0, r.stdout + r.stderr)

    def test_sobre_unica_no_padrao_sem_banner_laranja(self):
        t = ler('sobre/index.html')
        self.assertIn('class="hero"', t)
        self.assertNotIn('rgba(249, 115, 22, 0.85)', t)
        self.assertNotIn('manifesto-hero', t)
        self.assertIn('Poppins' not in t and 'Manrope', t)
        self.assertIn('t.js', t)
        self.assertIn('estratégia de sobrevivência', t)

    def test_sobre_nos_redireciona_para_sobre(self):
        self.assertIn(r'RewriteRule ^sobre-nos(\.html)?/?$ https://oravel.com.br/sobre/ [R=301,L]', ler('.htaccess'))


class TestPrecosEApp(unittest.TestCase):
    def test_nenhum_valor_em_reais_nas_paginas_de_preco(self):
        for rel in ['planos/index.html', 'solucoes/index.html'] + [p['pagina'].strip('/') + '/index.html' for p in dados.PACOTES]:
            sem_telas = re.sub(r'<svg.*?</svg>|<figcaption>.*?</figcaption>', '', ler(rel), flags=re.S)  # as telas de exemplo trazem valores fictícios
            self.assertNotIn('R$', sem_telas, rel)

    def test_paginas_de_pacote_tem_bloco_de_preco_app_e_script(self):
        for p in dados.PACOTES:
            t = ler(p['pagina'].strip('/') + '/index.html')
            self.assertIn('id="app"', t, p['codigo'])
            self.assertIn('/inc/precos.js', t, p['codigo'])
            self.assertEqual('data-preco="%s"' % p['codigo'] in t, dados.venda_online(p['codigo']), p['codigo'])

    def test_script_le_a_api_publica_e_nao_usa_innerhtml(self):
        js = ler('inc/precos.js')
        self.assertIn('/api/public/planos', js)
        self.assertNotIn('innerHTML', js)

    def test_planos_lista_app_e_academia_no_comparativo(self):
        t = ler('planos/index.html')
        self.assertIn('App instalável próprio', t)
        self.assertIn('Academia Oravel', t)


class TestCabecalhoUnico(unittest.TestCase):
    def test_faixa_preta_e_menu_iguais_da_home_em_todas_as_paginas(self):
        menus = set()
        for p in paginas():
            t = p.read_text(encoding='utf-8')
            m = re.search(r'<nav class="inav" data-site="nav".*?</nav>', t, re.S)
            if m:
                self.assertIn('class="tb"', t, p)
                self.assertIn('Falar com um Especialista', m.group(0), p)
                menus.add(re.sub(r' aria-current="page"', '', m.group(0)))
        self.assertEqual(len(menus), 1)
        for rel in ('nova/enterprise/index.php', 'nova/enterprise/solucoes/index.php', 'nova/enterprise/novidades/index.php', 'proposta/index.php'):
            m = re.search(r'<nav class="inav" data-site="nav".*?</nav>', ler(rel), re.S)
            self.assertIsNotNone(m, rel)
            self.assertEqual(re.sub(r' aria-current="page"', '', m.group(0)), next(iter(menus)), rel)
            self.assertNotIn('<nav class="nav">', ler(rel), rel)
        self.assertIn('class="tb"', ler('inc/nav-fragment.html'))
        self.assertIn('Veja os planos', ler('index.html'))


class TestRodapeUnico(unittest.TestCase):
    def test_todos_os_rodapes_sao_o_padrao_sem_menu_antigo(self):
        padrao = None
        for rel in ['index.html', 'gestao-locadoras/index.html', 'inc/footer.php', 'proposta/index.php', 'nova/enterprise/index.php', 'contato.php', 'planos/index.html', 'sobre/index.html']:
            t = ler(rel)
            m = re.search(r'<footer class="ifoot" data-site="footer">.*?</footer>', t, re.S)
            self.assertIsNotNone(m, rel)
            self.assertNotIn('ifoot-links', t, rel)
            self.assertEqual(len(re.findall(r'<footer', t)), 1, rel)
            padrao = padrao or m.group(0)
            self.assertEqual(m.group(0), padrao, rel)
        self.assertNotIn('Perguntas Frequentes', padrao)


class TestIdempotencia(unittest.TestCase):
    def test_rodar_de_novo_nao_muda_nada(self):
        antes = {str(p): p.read_bytes() for p in RAIZ.rglob('*') if p.is_file()}
        subprocess.run([sys.executable, str(Path(__file__).parent / 'aplicar.py')], check=True, capture_output=True)
        depois = {str(p): p.read_bytes() for p in RAIZ.rglob('*') if p.is_file()}
        self.assertEqual([k for k in antes if antes[k] != depois.get(k)], [])


if __name__ == '__main__':
    unittest.main()
