@php
    use App\Support\IntelligenceSite\Exemplo as E;
    $destaque = $ex->destaque();
    $urlPagina = rtrim($site, '/').'/intelligence/';
    $og = $urlPagina.'og-intelligence.png';
    $modelo = 'modelo-planilha-intelligence.xlsx';
    $faq = array_map(fn ($f) => $f['r'] === '__PRECO__'
        ? ['p' => $f['p'], 'r' => 'Primeira leitura grátis. Para acompanhar mês a mês, o Plano Básico custa '.$preco.'/mês. A cobrança só começa '.$dias.' dias após o seu primeiro acesso e você cancela quando quiser.']
        : $f, $t['faq']);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $t['titulo_pagina'] }} | Oravel</title>
<meta name="description" content="{{ $t['descricao_pagina'] }}">
<link rel="canonical" href="{{ $urlPagina }}">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="Oravel">
<meta property="og:title" content="{{ $t['titulo_pagina'] }}">
<meta property="og:description" content="{{ $t['descricao_pagina'] }}">
<meta property="og:url" content="{{ $urlPagina }}">
<meta property="og:image" content="{{ $og }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $t['hero_titulo'] }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $t['titulo_pagina'] }}">
<meta name="twitter:description" content="{{ $t['descricao_pagina'] }}">
<meta name="twitter:image" content="{{ $og }}">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="48x48" href="/favicon-48.png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $t['titulo_pagina'], 'description' => $t['descricao_pagina'], 'url' => $urlPagina, 'inLanguage' => 'pt-BR'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<style>{!! $css !!}</style>
</head>
<body>
<a class="skip" href="#conteudo">Ir para o conteúdo</a>
<header class="top"><div class="wrap"><a class="logo" href="{{ $site }}/" aria-label="Oravel">O<i>r</i>avel</a><small>{{ $t['marca'] }}</small></div></header>

<main id="conteudo">

{{-- 1) HERO --}}
<section class="hero" aria-labelledby="h-hero">
    <div class="wrap grid">
        <div>
            <div class="eyebrow">{{ $t['eyebrow'] }}</div>
            <h1 id="h-hero">{{ $t['hero_titulo'] }}</h1>
            <p class="sub">{{ $t['hero_subtitulo'] }}</p>
            <p class="preco">Primeira leitura grátis. Para acompanhar mês a mês: <b>{{ $preco }}/mês</b>.</p>
            <div class="acoes">
                <x-site.cta pos="topo" :texto="$t['cta']" :app="$app" />
                <a class="link-sec" href="{{ $modelo }}" download data-track="Teste grátis: baixou o modelo (topo)">{{ $t['baixar_modelo'] }}</a>
            </div>
            <p class="micro">{{ $t['microtexto'] }}</p>
        </div>
        <x-site.mock-indice :ex="$ex" :rotulo="$rotulo" />
    </div>
</section>

{{-- 2) O QUE VOCÊ DESCOBRE --}}
<section class="bloco" aria-labelledby="h-descobre">
    <div class="wrap">
        <h2 id="h-descobre">{{ $t['descobre_titulo'] }}</h2>
        <div class="cards">
            <article class="card">
                <h3>Equipamentos parados</h3>
                <p class="ex">Na locadora de exemplo, <b>{{ $ex->parados() }} de {{ $ex->frota() }}</b> equipamentos estavam parados. Isso deixa de entrar <b>{{ E::brl($ex->custoParadoMes()) }} por mês</b>.</p>
                <x-site.mini-parados :ex="$ex" :rotulo="$rotulo" />
            </article>
            <article class="card">
                <h3>Manutenção cara</h3>
                <p class="ex"><b>{{ $ex->acima60() }} equipamentos</b> já custam mais de 60% do valor em manutenção. {{ $destaque['nome'] }}: {{ E::brl($destaque['manutencao']) }} gastos num equipamento de {{ E::brl($destaque['valor']) }} ({{ $destaque['pct'] }}%).</p>
                <x-site.mock-equipamentos :ex="$ex" :rotulo="$rotulo" :linhas="2" />
            </article>
            <article class="card">
                <h3>O que vender, trocar ou reformar</h3>
                <p class="ex">Cada equipamento vem com a situação e o motivo. O plano de 30, 60 e 90 dias diz por onde começar: <b>foco na oficina (nota {{ $ex->oficina('out') }})</b>.</p>
                <x-site.mock :rotulo="$rotulo" aria="Exemplo: lista de decisão e foco do plano">
                    <h4>Lista de decisão</h4>
                    <p><span class="tag bad">Trocar ou vender</span> {{ $destaque['patrimonio'] }}, {{ $destaque['pct'] }}% do valor</p>
                    <div class="foco" style="margin-top:8px"><b>Foco do ciclo:</b> Saúde da Oficina, nota {{ $ex->oficina('ago') }} para {{ $ex->oficina('out') }} ({{ E::delta($ex->oficinaVariacao()) }})</div>
                </x-site.mock>
            </article>
        </div>

        <div class="calc" aria-labelledby="h-calc">
            <div>
                <h3 id="h-calc">Quanto custa deixar equipamento parado?</h3>
                <p class="lead" style="font-size:15px;margin:6px 0 14px">Troque os valores de exemplo pelos da sua locadora.</p>
                <div class="campos">
                    <label for="c-qtd">Equipamentos parados<input id="c-qtd" type="number" min="0" inputmode="numeric" value="{{ $ex->parados() }}"></label>
                    <label for="c-diaria">Diária média (R$)<input id="c-diaria" type="number" min="0" inputmode="numeric" value="{{ $ex->diaria() }}"></label>
                    <label for="c-dias">Dias parados no mês<input id="c-dias" type="number" min="0" max="31" inputmode="numeric" value="{{ $ex->diasParados() }}"></label>
                </div>
            </div>
            <div class="res" aria-live="polite">
                <small>Deixa de entrar por mês</small><b id="c-mes">{{ E::brl($ex->custoParadoMes()) }}</b>
                <small>Por ano</small><b id="c-ano">{{ E::brl($ex->custoParadoAno()) }}</b>
                <small style="font-style:italic">{{ $rotulo }}</small>
            </div>
        </div>
    </div>
</section>

{{-- 3) COMO FUNCIONA --}}
<section class="bloco alt" aria-labelledby="h-como">
    <div class="wrap">
        <h2 id="h-como">{{ $t['como_titulo'] }}</h2>
        <div class="passos">
            @foreach ($t['como_passos'] as $p)
                <div class="passo"><h3>{{ $p['titulo'] }}</h3><p>{{ $p['texto'] }}</p></div>
            @endforeach
        </div>
        <p class="nota-erp">{{ $t['como_nota_erp'] }}</p>
    </div>
</section>

{{-- 4) O QUE VEM NA LEITURA --}}
<section class="bloco" aria-labelledby="h-leitura">
    <div class="wrap">
        <h2 id="h-leitura">{{ $t['leitura_titulo'] }}</h2>
        <div class="car-wrap">
            <div class="carrossel" role="region" aria-label="Telas da leitura" tabindex="0">
                <div class="slide"><x-site.mock-indice :ex="$ex" :rotulo="$rotulo" /><p class="leg"><b>Índice de Saúde</b></p><p>Uma nota de 0 a 100 para a locadora e a evolução mês a mês.</p></div>
                <div class="slide"><x-site.mock-equipamentos :ex="$ex" :rotulo="$rotulo" /><p class="leg"><b>Equipamentos</b></p><p>A situação de cada equipamento, com o motivo.</p></div>
                <div class="slide"><x-site.mock-plano :ex="$ex" :rotulo="$rotulo" /><p class="leg"><b>Plano 30/60/90</b></p><p>O que fazer primeiro, com metas.</p></div>
                <div class="slide"><x-site.mock-pergunte :ex="$ex" :rotulo="$rotulo" /><p class="leg"><b>Pergunte em português</b></p><p>O assistente responde com os seus números e só consulta.</p></div>
            </div>
            <div class="car-nav"><button type="button" data-car="ant" aria-label="Tela anterior">‹</button><button type="button" data-car="prox" aria-label="Próxima tela">›</button></div>
        </div>
    </div>
</section>

{{-- 5) DEPOIMENTOS REAIS: oculta até existir registro aprovado e com autorização de uso (ver Central > Depoimentos do Intelligence). --}}
<section id="depoimentos" class="bloco alt" hidden data-fonte="{{ $fonteDepoimentos }}" aria-label="Depoimentos"><div class="wrap"><div class="deps"></div></div></section>

{{-- 6) PREÇO E CONFIANÇA --}}
<section class="bloco" aria-labelledby="h-preco">
    <div class="wrap">
        <h2 id="h-preco">{{ $t['preco_titulo'] }}</h2>
        <div class="precocard">
            <p class="big">Primeira leitura grátis. Plano Básico: <b>{{ $preco }}/mês</b>.</p>
            <p>A cobrança só começa {{ $dias }} dias após o seu primeiro acesso e você cancela quando quiser. Seus dados são privados e usados só para gerar a sua leitura.</p>
        </div>
    </div>
</section>

{{-- 7) FAQ + CTA FINAL --}}
<section class="bloco alt faq" aria-labelledby="h-faq">
    <div class="wrap">
        <h2 id="h-faq">{{ $t['faq_titulo'] }}</h2>
        @foreach ($faq as $f)
            <details><summary>{{ $f['p'] }}</summary><p>{{ $f['r'] }}</p></details>
        @endforeach
    </div>
</section>

<section class="final" aria-labelledby="h-final">
    <div class="wrap">
        <h2 id="h-final">{{ $t['hero_titulo'] }}</h2>
        <p>{{ $t['hero_subtitulo'] }}</p>
        <x-site.cta pos="final" :texto="$t['cta']" :app="$app" />
        <p class="micro" style="margin-top:12px;font-size:13px;margin-bottom:0">{{ $t['microtexto'] }}</p>
    </div>
</section>

</main>

<footer class="rod"><div class="wrap">
    <nav aria-label="Rodapé"><a href="/termos-de-uso/">Termos de Uso</a><a href="/politica-de-privacidade/">Política de Privacidade</a><a href="{{ $site }}/">Site da Oravel</a></nav>
    <div>{{ $empresa['legal_name'] }} · CNPJ {{ $empresa['cnpj'] }}</div>
</div></footer>

{{-- Botão fixo no rodapé (só no celular) --}}
<div class="fixo"><x-site.cta pos="fixo" :texto="$t['cta']" :app="$app" /></div>

{{-- Formulário (abre pelo CTA): 4 campos, sem sair do site. Envia para o app, que manda um link de confirmação para o e-mail; a conta só nasce depois do clique. --}}
<dialog class="form" id="teste-gratis" aria-labelledby="tg-t">
    <button type="button" class="fechar" data-fechar aria-label="Fechar">×</button>
    <form method="post" action="{{ $app }}/intelligence/teste-gratis">
        <h2 id="tg-t">{{ $t['cta'] }}</h2>
        <p class="micro">{{ $t['microtexto'] }}</p>
        <label class="c" for="f-nome">Seu nome<input id="f-nome" type="text" name="nome" required autocomplete="name" maxlength="120"></label>
        <label class="c" for="f-empresa">Empresa<input id="f-empresa" type="text" name="empresa" required autocomplete="organization" maxlength="120"></label>
        <label class="c" for="f-whats">WhatsApp<input id="f-whats" type="tel" name="whatsapp" required inputmode="tel" autocomplete="tel" placeholder="(19) 99999-9999" maxlength="20"></label>
        <label class="c" for="f-email">E-mail<input id="f-email" type="email" name="email" required autocomplete="email" inputmode="email" placeholder="voce@suaempresa.com.br" maxlength="160"></label>
        <p class="micro" style="margin:-6px 0 0;font-size:13px">Enviamos um link para você confirmar o e-mail e entrar. Sem senha.</p>
        @if (! empty($turnstileKey))
        <div class="cf-turnstile" data-sitekey="{{ $turnstileKey }}" data-callback="ovTsOk" data-expired-callback="ovTsExp" data-error-callback="ovTsExp" data-language="pt-br"></div>
        @endif
        <label class="aceite"><input type="checkbox" name="aceite" value="1" required><span>Li e aceito os <a href="/termos-de-uso/" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="/politica-de-privacidade/" target="_blank" rel="noopener">Política de Privacidade</a> (LGPD).</span></label>
        <div class="hp" aria-hidden="true"><label>Não preencha<input type="text" name="site" tabindex="-1" autocomplete="off"></label></div>
        <button class="btn" type="submit">Continuar</button>
    </form>
</dialog>

<script>{!! $js !!}</script>
<script async src="{{ $app }}/t.js?v=3"></script>
<script src="/protecao.js?v=4" defer></script>
</body>
</html>
