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
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $t['titulo_pagina'], 'description' => $t['descricao_pagina'], 'url' => $urlPagina, 'inLanguage' => 'pt-BR'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
<style>{!! $css !!}</style>
</head>
<body>
<a class="skip" href="#conteudo">Ir para o conteúdo</a>
<header class="top"><div class="wrap"><a class="logo" href="{{ $site }}/" aria-label="Oravel">O<i>r</i>avel</a><small>{{ $t['marca'] }}</small><x-site.cta pos="topo" :texto="$t['cta']" :app="$app" /></div></header>

<main id="conteudo">

{{-- 1) HERO: o cadastro é a peça principal --}}
<section class="hero" aria-labelledby="h-hero">
    <div class="wrap">
        <h1 id="h-hero">{{ $t['hero_titulo'] }}</h1>
        <p class="sub">{{ $t['hero_subtitulo'] }}</p>
        <div class="form-hero" id="cadastro">
            <p class="form-tit">{{ $t['form_titulo'] }} de {{ $dias }} dias</p>
            <x-site.cadastro prefixo="h" :t="$t" :app="$app" :turnstile-key="$turnstileKey" />
            <p class="micro">{{ $t['microtexto'] }}</p>
        </div>
        <ul class="pontos">
            @foreach ($t['hero_pontos'] as $p)
                <li><b>{{ $p['t'] }}</b><span>{{ $p['d'] }}</span></li>
            @endforeach
        </ul>
    </div>
</section>

{{-- 2) O QUE É --}}
<section class="bloco escuro" aria-labelledby="h-oque">
    <div class="wrap">
        <h2 id="h-oque">{{ $t['oque_titulo'] }}</h2>
        <p class="lead">{{ $t['oque_lead'] }}</p>
        <div class="fluxo">
            @foreach ($t['fluxo'] as $f)
                <div class="etapa"><h3>{{ $f['t'] }}</h3><p>{{ $f['d'] }}</p></div>
            @endforeach
        </div>
    </div>
</section>

{{-- 3) O QUE VOCÊ DESCOBRE: as dores de uma locadora, respondidas --}}
<section class="bloco" aria-labelledby="h-descobre">
    <div class="wrap">
        <h2 id="h-descobre">{{ $t['descobre_titulo'] }}</h2>
        <p class="lead">{{ $t['descobre_lead'] }}</p>
        <div class="dores">
            <article class="dor">
                <div class="dor-txt">
                    <h3>Quais equipamentos estão parados, e quanto isso custa?</h3>
                    <p>Na locadora de exemplo, <b>{{ $ex->parados() }} de {{ $ex->frota() }}</b> equipamentos estavam parados. Isso deixa de entrar <b>{{ E::brl($ex->custoParadoMes()) }} por mês</b>.</p>
                </div>
                <div class="dor-vis"><x-site.mini-parados :ex="$ex" :rotulo="$rotulo" /></div>
            </article>
            <article class="dor">
                <div class="dor-txt">
                    <h3>Onde a manutenção está cara demais?</h3>
                    <p><b>{{ $ex->acima60() }} equipamentos</b> já custam mais de 60% do valor em manutenção. {{ $destaque['nome'] }}: {{ E::brl($destaque['manutencao']) }} gastos num equipamento de {{ E::brl($destaque['valor']) }} ({{ $destaque['pct'] }}%).</p>
                </div>
                <div class="dor-vis"><x-site.mock-equipamentos :ex="$ex" :rotulo="$rotulo" :linhas="2" /></div>
            </article>
            <article class="dor">
                <div class="dor-txt">
                    <h3>O que vender, trocar ou reformar?</h3>
                    <p>Cada equipamento vem com a situação e o motivo. O plano de 30, 60 e 90 dias diz por onde começar: <b>foco na oficina (nota {{ $ex->oficina('out') }})</b>.</p>
                </div>
                <div class="dor-vis">
                    <x-site.mock :rotulo="$rotulo" aria="Exemplo: lista de decisão e foco do plano">
                        <h4>Lista de decisão</h4>
                        <p><span class="tag bad">Trocar ou vender</span> {{ $destaque['patrimonio'] }}, {{ $destaque['pct'] }}% do valor</p>
                        <div class="foco" style="margin-top:8px"><b>Foco do ciclo:</b> Saúde da Oficina, nota {{ $ex->oficina('ago') }} para {{ $ex->oficina('out') }} ({{ E::delta($ex->oficinaVariacao()) }})</div>
                    </x-site.mock>
                </div>
            </article>
            <article class="dor">
                <div class="dor-txt">
                    <h3>Quanto do faturamento está em atraso?</h3>
                    <p>O atraso caiu de <b>{{ E::pct($ex->atraso('ago'), 1) }}</b> para <b>{{ E::pct($ex->atraso('out'), 1) }}</b> do faturamento. A meta do próximo ciclo é {{ E::pct($ex->atrasoMeta(), 1) }}.</p>
                </div>
                <div class="dor-vis"><x-site.mini-atraso :ex="$ex" :rotulo="$rotulo" /></div>
            </article>
            <article class="dor">
                <div class="dor-txt">
                    <h3>A oficina dá conta do recado?</h3>
                    <p>A nota da oficina foi de <b>{{ $ex->oficina('ago') }}</b> para <b>{{ $ex->oficina('out') }}</b>. Você vê preventiva contra corretiva, tempo de reparo e defeitos que voltam.</p>
                </div>
                <div class="dor-vis"><x-site.mini-oficina :ex="$ex" :rotulo="$rotulo" /></div>
            </article>
        </div>
    </div>
</section>

{{-- 4) CALCULADORA --}}
<section class="bloco escuro" aria-labelledby="h-calc">
    <div class="wrap">
        <h2 id="h-calc">{{ $t['calc_titulo'] }}</h2>
        <p class="lead">Troque os valores de exemplo pelos da sua locadora e veja a conta na hora.</p>
        <div class="calc">
            <div class="campos">
                <label for="c-qtd">Equipamentos parados<input id="c-qtd" type="number" min="0" inputmode="numeric" value="{{ $ex->parados() }}"></label>
                <label for="c-diaria">Diária média (R$)<input id="c-diaria" type="number" min="0" inputmode="numeric" value="{{ $ex->diaria() }}"></label>
                <label for="c-dias">Dias parados no mês<input id="c-dias" type="number" min="0" max="31" inputmode="numeric" value="{{ $ex->diasParados() }}"></label>
            </div>
            <div class="res" aria-live="polite">
                <small>Deixa de entrar por mês</small><b id="c-mes">{{ E::brl($ex->custoParadoMes()) }}</b>
                <small>Por ano</small><b id="c-ano">{{ E::brl($ex->custoParadoAno()) }}</b>
                <small style="font-style:italic">{{ $rotulo }}</small>
            </div>
        </div>
    </div>
</section>

{{-- 5) O QUE VOCÊ RECEBE --}}
<section class="bloco" aria-labelledby="h-leitura">
    <div class="wrap">
        <h2 id="h-leitura">{{ $t['leitura_titulo'] }}</h2>
        <p class="lead">{{ $t['leitura_lead'] }}</p>
        <ul class="areas">
            @foreach ($t['areas'] as $a)
                <li>
                    <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! match ($a['t']) {
                        'Frota' => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
                        'Finanças' => '<circle cx="12" cy="12" r="8"/><path d="M12 7v10M9.5 9.5c0-1 1-1.7 2.5-1.7s2.5.7 2.5 1.7-1 1.5-2.5 1.8-2.5.8-2.5 1.8 1 1.7 2.5 1.7 2.5-.7 2.5-1.7"/>',
                        'Oficina' => '<path d="M14.5 6.5a4 4 0 0 0-5 5L4 17l3 3 5.5-5.5a4 4 0 0 0 5-5l-2.5 2.5-2-.5-.5-2z"/>',
                        'Comercial' => '<path d="M5 19v-8M12 19V5M19 19v-10"/>',
                        default => '<circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/>',
                    } !!}</svg>
                    <b>{{ $a['t'] }}</b><span>{{ $a['d'] }}</span>
                </li>
            @endforeach
        </ul>
        <div class="car-wrap">
            <div class="carrossel" role="region" aria-label="Telas da leitura" tabindex="0">
                <div class="slide"><x-site.mock-indice :ex="$ex" :rotulo="$rotulo" /><p class="leg">Índice de Saúde</p><p>Uma nota de 0 a 100 para a locadora e a evolução mês a mês.</p></div>
                <div class="slide"><x-site.mock-equipamentos :ex="$ex" :rotulo="$rotulo" /><p class="leg">Equipamentos</p><p>A situação de cada equipamento, com o motivo.</p></div>
                <div class="slide"><x-site.mock-plano :ex="$ex" :rotulo="$rotulo" /><p class="leg">Plano 30/60/90</p><p>O que fazer primeiro, com metas.</p></div>
                <div class="slide"><x-site.mock-pergunte :ex="$ex" :rotulo="$rotulo" /><p class="leg">Pergunte em português</p><p>O assistente responde com os seus números e só consulta.</p></div>
            </div>
            <div class="car-nav"><button type="button" data-car="ant" aria-label="Tela anterior">‹</button><button type="button" data-car="prox" aria-label="Próxima tela">›</button></div>
        </div>
        <p class="em-breve">{{ $t['em_breve'] }}</p>
    </div>
</section>

{{-- 6) COM SISTEMA OU SEM SISTEMA --}}
<section class="bloco alt" aria-labelledby="h-perfis">
    <div class="wrap">
        <h2 id="h-perfis">{{ $t['perfis_titulo'] }}</h2>
        <div class="perfis">
            @foreach ($t['perfis'] as $p)
                <div class="perfil"><h3>{{ $p['t'] }}</h3><p>{{ $p['d'] }}</p></div>
            @endforeach
        </div>
    </div>
</section>

{{-- 7) COMO FUNCIONA --}}
<section class="bloco" aria-labelledby="h-como">
    <div class="wrap">
        <h2 id="h-como">{{ $t['como_titulo'] }}</h2>
        <div class="passos">
            @foreach ($t['como_passos'] as $i => $p)
                <div class="passo"><h3>{{ $p['titulo'] }}</h3><p>{{ $p['texto'] }}</p>@if ($i === 1)<a class="link-modelo" href="{{ $modelo }}" download data-track="Teste grátis: baixou o modelo">{{ $t['baixar_modelo'] }}</a>@endif</div>
            @endforeach
        </div>
        <p class="nota-erp">{{ $t['como_nota_erp'] }}</p>
    </div>
</section>

{{-- 8) DEPOIMENTOS REAIS: oculta até existir registro aprovado e com autorização de uso (ver Central > Depoimentos do Intelligence). --}}
<section id="depoimentos" class="bloco alt" hidden data-fonte="{{ $fonteDepoimentos }}" aria-label="Depoimentos"><div class="wrap"><div class="deps"></div></div></section>

{{-- 9) PREÇO E CONFIANÇA --}}
<section class="bloco escuro" aria-labelledby="h-preco">
    <div class="wrap">
        <h2 id="h-preco">{{ $t['preco_titulo'] }}</h2>
        <div class="precocard">
            <p class="big">Primeira leitura grátis. Plano Básico: <b>{{ $preco }}/mês</b>.</p>
            <p>A cobrança só começa {{ $dias }} dias após o seu primeiro acesso e você cancela quando quiser. Seus dados são privados e usados só para gerar a sua leitura.</p>
        </div>
        <ul class="confianca">
            @foreach ($t['confianca'] as $c)
                <li><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg><b>{{ $c['t'] }}</b><span>{{ $c['d'] }}</span></li>
            @endforeach
        </ul>
    </div>
</section>

{{-- 10) FAQ + CTA FINAL --}}
<section class="bloco faq" aria-labelledby="h-faq">
    <div class="wrap">
        <h2 id="h-faq">{{ $t['faq_titulo'] }}</h2>
        <div class="faq-lista">
            @foreach ($faq as $f)
                <details><summary>{{ $f['p'] }}</summary><p>{{ $f['r'] }}</p></details>
            @endforeach
        </div>
    </div>
</section>

<section class="final" aria-labelledby="h-final">
    <div class="wrap">
        <h2 id="h-final">{{ $t['final_titulo'] }}</h2>
        <p>{{ $t['final_texto'] }}</p>
        <x-site.cta pos="final" :texto="$t['cta']" :app="$app" />
        <p class="micro">{{ $t['microtexto'] }}</p>
    </div>
</section>

</main>

<footer class="rod"><div class="wrap">
    <nav aria-label="Rodapé"><a href="/termos-de-uso/">Termos de Uso</a><a href="/politica-de-privacidade/">Política de Privacidade</a><a href="{{ $site }}/">Site da Oravel</a></nav>
    <div>{{ $empresa['legal_name'] }} · CNPJ {{ $empresa['cnpj'] }}</div>
</div></footer>

{{-- Botão fixo no rodapé (só no celular) --}}
<div class="fixo"><x-site.cta pos="fixo" :texto="$t['cta']" :app="$app" /></div>

{{-- Formulário em pop-up (abre pelos botões depois do hero): mesmo cadastro do hero --}}
<dialog class="form" id="teste-gratis" aria-labelledby="tg-t">
    <button type="button" class="fechar" data-fechar aria-label="Fechar">×</button>
    <div class="dlg-corpo">
        <h2 id="tg-t">{{ $t['cta'] }}</h2>
        <p class="micro">{{ $t['microtexto'] }}</p>
        <x-site.cadastro prefixo="f" :t="$t" :app="$app" :turnstile-key="$turnstileKey" />
    </div>
</dialog>

<script>{!! $js !!}</script>
<script async src="{{ $app }}/t.js?v=3"></script>
<script src="/protecao.js?v=4" defer></script>
</body>
</html>
